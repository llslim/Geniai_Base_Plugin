# Product Requirement Document (PRD)
## Feature: Configurable LLM Prompt Template Embedded in Scenario JSON

---

## 1. Introduction & Objectives

### 1.1. Background
The AACURA chatbot (`local_aacuracore`) generates parent-persona responses and evaluates teacher inputs by sending prompts to an external LLM. Currently, the **system prompt template is hardcoded** in PHP source code (`classes/strategy/generative_ai_api_strategy.php` and `classes/strategy/core_ai_provider_strategy.php`).

Site administrators cannot adjust the persona instructions, tone, or behavioral rules without editing plugin code. Furthermore, the persona backstory and communication rules are split across two concerns: the **scenario JSON** defines *what* the persona is, while the **hardcoded PHP** defines *how* the persona should behave at the LLM level. These should be one cohesive document.

### 1.2. Objectives
* **Embed the LLM system prompt template** inside the scenario JSON document alongside persona metadata and state definitions, making each scenario fully self-contained.
* **Allow per-activity-instance configuration** via the module settings form (`mod_aacurachat`), so each chat activity can focus on a separate topic with its own scenario + prompt file.
* **Support placeholders** in the template that are dynamically substituted with fields from the same scenario JSON at runtime.
* **Preserve backward compatibility** — preloaded scenarios (anna, brianna, etc.) get a default template that reproduces the current behavior.

---

## 2. Current Implementation (As-Is)

### 2.1. Where the prompt is hardcoded
The parent-persona system prompt is constructed inline in two places:

| File | Method | Purpose |
| :--- | :--- | :--- |
| `local/aacuracore/classes/strategy/generative_ai_api_strategy.php` | `generate_response()` | Builds the parent persona system instruction for direct REST API calls. |
| `local/aacuracore/classes/strategy/core_ai_provider_strategy.php` | `generate_response()` | Builds the same system instruction for Moodle Core AI calls. |

### 2.2. Current hardcoded template (duplicated in both files)
```text
Your name is {persona.name}. Backstory:
{persona.backstory}

Child Preferred Pronoun: {persona.child_preferred_pronoun}
Your communication style is: {persona.communication_style}

Current dialogue state requirement:
You are in the '{statekey}' state of the conversation.
On this turn, you must convey the following core concern: "{stateprompt}"

CRITICAL RULES:
- Stay strictly in character as the parent.
- Always refer to your child using their preferred pronoun ({pronoun}).
  Do NOT substitute incorrect gender pronouns.
- NEVER start your response with 'I understand', 'I understand your concern',
  'I understand your concerns', 'That makes sense', 'I see', or 'Thank you'.
- NEVER validate or praise the teacher's explanation.
- Jump straight into your emotional reaction or concern in character as the
  parent in 2-4 concise sentences.
```

### 2.3. How scenario JSON files are currently loaded
Scenarios are loaded by `local_aacuracore\scenario\scenario_loader` in this priority order:

1. **Custom DB table** (`local_aacuracore_custom_scenarios`) — uploaded via the Scenario Builder UI.
2. **Local JSON file** (`local/aacuracore/scenarios/{id}.json`) — for preloaded profiles.
3. **Static PHP fallback** — hardcoded persona definitions.

For activity instances, the module form (`mod/aacurachat/mod_form.php`) allows uploading a `.json` file via a `filepicker` element when the scenario is set to "Activity File Upload".

---

## 3. Proposed Scenario JSON Schema (with embedded prompt template)

A single JSON document now contains everything: persona, states, learning objectives, **and** the LLM prompt template.

### 3.1. New `prompt_template` field

```json
{
  "scenario_id": "anna",
  "prompt_template": "Your name is {{persona_name}}. Backstory:\n{{backstory}}\n\nChild Preferred Pronoun: {{pronoun}}\nYour communication style is: {{communication_style}}\n\nCurrent dialogue state requirement:\nYou are in the '{{statekey}}' state of the conversation.\nOn this turn, you must convey the following core concern: \"{{stateprompt}}\"\n\nCRITICAL RULES:\n- Stay strictly in character as the parent.\n- Always refer to your child using their preferred pronoun ({{pronoun}}). Do NOT substitute incorrect gender pronouns.\n- NEVER start your response with 'I understand', 'I understand your concern', 'I understand your concerns', 'That makes sense', 'I see', or 'Thank you'.\n- NEVER validate or praise the teacher's explanation.\n- Jump straight into your emotional reaction or concern in character as the parent in 2-4 concise sentences.",
  "persona": {
    "name": "Anna Charles (Parent)",
    "child_preferred_pronoun": "she/her",
    "backstory": "Your name is Anna Charles...",
    "initial_mood": "overwhelmed",
    "communication_style": "Frustrated, defensive..."
  },
  "learning_objectives": ["active_listening", "empathy_check", "note_permission"],
  "states": {
    "START": {
      "bot_prompt": "Thank you for meeting with me...",
      "expected_criteria": {
        "validation_type": "empathy_check",
        "pass_route": "EXPLORATION",
        "fail_route": "ESCALATION"
      }
    }
  }
}
```

### 3.2. Template field is optional
- If `prompt_template` is **present** in the JSON, it is used at runtime with placeholder substitution.
- If `prompt_template` is **absent**, the system falls back to the **default hardcoded template** (current behavior).
- This means existing scenario files remain fully backward-compatible — a scenario author can adopt the feature incrementally by adding the field.

### 3.3. Placeholder syntax and supported values
Placeholder syntax: `{{placeholder_name}}` (double curly braces).

| Placeholder | Source | Example |
| :--- | :--- | :--- |
| `{{persona_name}}` | `persona.name` | `Anna Charles (Parent)` |
| `{{backstory}}` | `persona.backstory` | Long narrative string |
| `{{pronoun}}` | `persona.child_preferred_pronoun` | `she/her` |
| `{{communication_style}}` | `persona.communication_style` | `Frustrated, defensive...` |
| `{{initial_mood}}` | `persona.initial_mood` | `overwhelmed` |
| `{{statekey}}` | Current dialogue state key | `EXPLORATION` |
| `{{stateprompt}}` | Current state `bot_prompt` | `Well, yes, I suppose...` |
| `{{scenario_id}}` | `scenario_id` | `anna` |
| `{{learning_objectives}}` | `learning_objectives` (comma-joined) | `active_listening, empathy_check` |

---

## 4. Functional Requirements

### 4.1. Scenario JSON Schema Update
* **FR-1:** Add an **optional `prompt_template`** top-level field to the scenario JSON schema.
* **FR-2:** The field is a string containing the LLM system prompt template with `{{placeholder}}` tokens.
* **FR-3:** If `prompt_template` is present and non-empty, it is used at runtime. If absent or empty, the system falls back to the current hardcoded default.

### 4.2. Module Settings Integration (per-activity-instance)
* **FR-4:** The `mod_aacurachat` activity settings form (`mod_form.php`) already supports uploading a `.json` scenario file via the `scenariofile` filepicker. This remains the primary entry point for custom scenarios.
* **FR-5:** When a `.json` file is uploaded and contains a `prompt_template` field, the template is parsed and stored as part of the scenario definition.
* **FR-6:** No new settings fields on the global plugin settings page are required for the prompt template. The scenario JSON is the single source of truth.
* **FR-7:** Preloaded scenarios (anna, brianna, cathy, mary) should have their corresponding `.json` files updated to include the `prompt_template` field.

### 4.3. Runtime Substitution
* **FR-8:** Both `generative_ai_api_strategy::generate_response()` and `core_ai_provider_strategy::generate_response()` must read the `prompt_template` from the scenario definition and perform placeholder substitution.
* **FR-9:** A central helper (a new method on `scenario_definition` or a dedicated `prompt_renderer` utility class) performs the substitution, eliminating code duplication.
* **FR-10:** `scenario_definition` must be updated to expose `get_prompt_template()` and `render_prompt_template(string $statekey): string`.

### 4.4. Backward Compatibility
* **FR-11:** Existing scenario JSON files **without** `prompt_template` must continue to work — the system falls back to the hardcoded default.
* **FR-12:** The preloaded JSON files in `local/aacuracore/scenarios/` must be updated to include the `prompt_template` field so the feature is immediately usable.
* **FR-13:** The `scenario_loader::from_array()` method must gracefully handle the absence of the `prompt_template` key.

---

## 5. Technical Architecture

### 5.1. Modified Components

| Component | Change | 
| :--- | :--- |
| `local/aacuracore/classes/scenario/scenario_definition.php` | Add `prompt_template` property, getter, and `render_prompt_template(string $statekey)` helper. |
| `local/aacuracore/classes/scenario/scenario_loader.php` | Update `from_array()` to parse `prompt_template` from JSON. Update static profile helpers to include embedded template. |
| `local/aacuracore/classes/strategy/generative_ai_api_strategy.php` | Replace hardcoded system instruction with call to `$scenario->render_prompt_template()`. |
| `local/aacuracore/classes/strategy/core_ai_provider_strategy.php` | Same change as above. |
| `local/aacuracore/scenarios/` | Update all 4 preloaded `.json` files to include `prompt_template`. |
| `mod/aacurachat/db/install.xml` | No change needed — the filepicker already handles JSON upload. |

### 5.2. Recommended: Central `prompt_renderer` Utility

```php
namespace local_aacuracore;

class prompt_renderer {
    /**
     * Substitute placeholders in a template string with scenario values.
     *
     * @param string $template    The raw template with {{placeholder}} tokens.
     * @param scenario_definition $scenario  The active scenario.
     * @param string $statekey    The current dialogue state key.
     * @return string
     */
    public static function render(string $template, scenario_definition $scenario, string $statekey): string {
        $persona = $scenario->get_persona();
        $node = $scenario->get_state_node($statekey);
        $stateprompt = $node['bot_prompt'] ?? '';

        $replacements = [
            '{{persona_name}}'        => $persona['name'] ?? '',
            '{{backstory}}'           => $persona['backstory'] ?? '',
            '{{pronoun}}'             => $persona['child_preferred_pronoun'] ?? 'he/him',
            '{{communication_style}}' => $persona['communication_style'] ?? '',
            '{{initial_mood}}'        => $persona['initial_mood'] ?? '',
            '{{statekey}}'            => $statekey,
            '{{stateprompt}}'         => $stateprompt,
            '{{scenario_id}}'         => $scenario->get_id(),
            '{{learning_objectives}}' => implode(', ', $scenario->get_learning_objectives()),
        ];

        return strtr($template, $replacements);
    }
}
```

### 5.3. Integration in both strategy classes

Before (hardcoded):
```php
$systeminstruction = "Your name is " . $persona['name'] . ". Backstory:\n" . ...;
```

After (configurable):
```php
$template = $scenario->get_prompt_template();
if (empty($template)) {
    $template = get_config('local_aacuracore', 'default_prompt_template') ?: self::DEFAULT_TEMPLATE;
}
$systeminstruction = \local_aacuracore\prompt_renderer::render($template, $scenario, $statekey);
```

---

## 6. User Stories

* **US-1:** As a **trainer**, I want each chat activity instance to have its own scenario JSON with a custom LLM prompt, so students practice different communication topics.
* **US-2:** As a **scenario author**, I want the persona definition and the LLM behavior instructions in one JSON file, so I can version-control and share complete scenarios.
* **US-3:** As a **site administrator**, I don't need to touch the global plugin settings to adjust promp behavior — the perf activity JSON handles it.
* **US-4:** As a **developer**, I want the prompt logic centralized so there isn't duplicated hardcoded code.

---

## 7. Acceptance Criteria

* **AC-1:** A scenario JSON with a `prompt_template` field correctly drives the parent persona response generation with placeholder substitution.
* **AC-2:** All 4 preloaded scenarios (anna, brianna, cathhy, mary) get updated `.json` files with the embedded `prompt_template`.
* **AC-3:** Uploading a custom`.json` file with a `prompt_template` on the module settings form works correctly.
* **AC-4:** Removing the `prompt_template` field from a scenario JSON falls back to the default hardcoded template withou error.
* **AC-5:** Both strategy classes produce identical responses given the same template and scenario.
* **AC-6:** All existing tests (PHPUnit + scenario crawler) still pass.

---

## 8. Out of Scope

* Extracting the `evaluate_input()` validation prompt and `generate_rubric_feedback()` prompt — these remain hardcoded and can be addressed in a future iteration.
* A module-level setting form field to edit the template directly (the template is embedded in the JSON file, which is uploaded via the existing filepicker).
* Per-state prompt templates (the template is scenario-wide; the `{{statekey}}` and `{{stateprompt}}` placeholders provide state-specific context).

---

## 9. Release & Versioning

* **Version Type:** Minor Feature (`1.x.0`) per tagging policy.
* **Version Bump:** `$plugin->version` → `2026081003`, `$plugin->release` → `2.4.3`.
* **Tag:** `v1.5.0-dev` (next minor version after v1.4.0).
* **Documentation:** Update `version_history.md` / `release_history.md`.

---

## 10. Open Questions

* Should the `prompt_template` also support per-state overrides at the JSON level (e.g., a `prompt_template` field inside a state node that takes precedence over the top-level one)?
* When uploading a JSON via the module filepicker, should the system validate that placeholders in the template match the scenario's actual fields, or just leave unknown placeholders as-is?
* Should the filepicker on the module form support multiple JSON uploads (e.g., a "scenario pack") or is single-file upload sufficient for v1?
