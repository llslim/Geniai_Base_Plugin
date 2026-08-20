# Product Requirement Document (PRD)
## Feature: AI-Driven Interactive Scenario Builder

---

## 1. Introduction & Objectives

### 1.1. Background
The AACURA chatbot (`local_aacuracore` + `mod_aacurachat`) currently requires scenario authors to hand-write JSON scenario files (persona, states, prompt template) and upload them via the Scenario Builder or module filepicker. This is a technical, error-prone process that non-technical instructors find difficult.

This feature introduces an **AI-driven interactive scenario builder**: a button on the chat interface (visible when edit mode is on) that switches the chatbot into an **interviewer role**. The AI interactively interviews the author to gather scenario requirements, then generates a complete, valid scenario JSON that can be exported as a `.json` file.

### 1.2. Objectives
* **Lower the barrier** to scenario creation by replacing hand-written JSON with a guided, conversational interview.
* **Reuse the existing LLM infrastructure** (prompt templates, `prompt_renderer`, scenario schema) to generate valid scenario JSON.
* **Provide a one-click export** of the generated scenario as a downloadable `.json` file, compatible with the existing Scenario Builder upload and module filepicker.
* **Gate the feature** behind edit mode / manage capability so only authorized authors see the builder button.

---

## 2. Current Implementation (As-Is)

### 2.1. Chat interface
- The chat UI is rendered by `mod/aacurachat/templates/chat.mustache` and driven by `local/aacuracore/amd/src/chat.js`.
- The header panel contains a persona dropdown, "Generate PDF", and "Clear/Restart Chat" buttons.
- `manage_capability` is passed to the template (from `view.php` via `has_capability("local/aacuracore:manage", $context)`), but is **not currently used** to gate any UI element.

### 2.2. Scenario schema
A scenario JSON contains:
- `scenario_id` (string)
- `prompt_template` (optional string with `{{placeholder}}` tokens)
- `persona` (name, child_preferred_pronoun, backstory, initial_mood, communication_style)
- `learning_objectives` (array)
- `states` (directed graph: START, EXPLORATION, ESCALATION, CONFUSION, RESOLUTION, FAIL_STATE, each with `bot_prompt`, an optional `rubric` array of per-state scoring criteria, and optional `expected_criteria`)

### 2.3. LLM prompt infrastructure
- `local_aacuracore\prompt_renderer` handles placeholder substitution.
- `local_aacuracore\api::chat_completions()` sends messages to the configured LLM provider.
- Scenario JSON is validated/loaded by `scenario_loader::from_array()`.

---

## 3. Functional Requirements

### 3.1. Builder Entry Point (Edit Mode Button)
* **FR-1:** Add a **"AI Scenario Builder"** button to the chat interface header panel.
* **FR-2:** The button is **only visible when edit mode is on** (i.e., when the user has `local/aacuracore:manage` capability and Moodle edit mode is enabled).
* **FR-3:** Clicking the button switches the chatbot into **interviewer mode** and starts a guided scenario-building conversation.

### 3.2. Interviewer Mode
* **FR-4:** In interviewer mode, the chatbot takes on the role of a **scenario interviewer** (not a parent persona).
* **FR-5:** The interviewer asks the author a structured sequence of questions to gather:
  - Scenario ID / code
  - Parent persona name
  - Child's diagnosis / context (backstory)
  - Child preferred pronoun
  - Initial mood
  - Communication style
  - Learning objectives
  - Dialogue states and their prompts / validation criteria
  - **Per-state rubric criteria** (what a trainee must do to earn a point in each state)
  - (Optional) custom prompt template
* **FR-6:** The interviewer should ask one question at a time and confirm each answer before moving on, allowing the author to correct answers.
* **FR-7:** The author can type free-form answers; the AI parses and structures them into the scenario schema.

### 3.3. Scenario Generation
* **FR-8:** After the interview completes, the AI generates a **complete, valid scenario JSON** conforming to the existing schema (including `prompt_template` with placeholders).
* **FR-9:** The generated JSON must be **validated** against the scenario schema before being offered for export (reuse `scenario_loader::from_array()` or a validation routine).
* **FR-10:** If validation fails, the AI should present the errors and allow the author to fix them interactively.

### 3.4. Export
* **FR-11:** Provide an **"Export JSON"** action that downloads the generated scenario as a `.json` file (named `{scenario_id}.json`).
* **FR-12:** The exported file must be directly uploadable via the existing Scenario Builder (`scenario_builder.php`) and the module filepicker (`mod_form.php`).
* **FR-13:** Optionally, offer a "Register to Site Registry" action that saves the scenario to `local_aacuracore_custom_scenarios` directly.

### 3.5. Exit / Cancel
* **FR-14:** The author can **exit interviewer mode** at any time and return to normal parent-persona chat.
* **FR-15:** Exiting should not corrupt the active session; the normal chat state is preserved.

---

## 4. Technical & Architectural Requirements

### 4.1. New/Modified Components

| Component | Type | Description |
| :--- | :--- | :--- |
| `mod/aacurachat/templates/chat.mustache` | Modified | Add "AI Scenario Builder" button (gated by edit mode). |
| `local/aacuracore/amd/src/chat.js` | Modified | Handle builder button click, interviewer-mode message routing, and export. |
| `local/aacuracore/classes/api.php` | Modified (or new) | Add a scenario-builder chat endpoint / method. |
| `local/aacuracore/classes/scenario_builder_ai.php` | New (recommended) | Orchestrates the interviewer conversation and JSON generation. |
| `local/aacuracore/classes/prompt_renderer.php` | Reused | Render the interviewer system prompt. |
| `local/aacuracore/classes/scenario/scenario_loader.php` | Reused | Validate generated JSON via `from_array()`. |

### 4.2. Interviewer System Prompt
A dedicated prompt template (reusing `prompt_renderer`) instructs the LLM to act as a scenario interviewer. It should:
- Ask one question at a time.
- Parse answers into the scenario schema.
- Confirm each field before proceeding.
- Produce a final JSON object when all fields are gathered.

### 4.3. State Machine for the Interview
The interview itself can be modeled as a small state machine:
```
GREETING -> ASK_SCENARIO_ID -> ASK_PERSONA_NAME -> ASK_BACKSTORY ->
ASK_PRONOUN -> ASK_MOOD -> ASK_COMM_STYLE -> ASK_ROLE -> ASK_OBJECTIVES ->
ASK_START_PROMPT -> ASK_RUBRIC -> ASK_TEMPLATE -> CONFIRM -> GENERATE -> EXPORT
```
Each state maps to a question; the AI advances states based on the author's answers.

### 4.4. JSON Generation & Validation
- The AI returns a JSON object (or a JSON string embedded in its response).
- The system extracts and `json_decode`s it, then validates via `scenario_loader::from_array()`.
- Per-state rubric criteria gathered during the interview are emitted as a `rubric` array on each state node; a non-array rubric value (e.g. a comma/newline string) is normalized to an array during validation.
- On success, the JSON is stored in a JS variable for export.
- On failure, error messages are fed back to the AI for correction.

---

## 5. User Stories

* **US-1:** As an **instructor**, I want to click a button in the chat (in edit mode) and be guided through creating a scenario by answering questions, so I don't have to write JSON by hand.
* **US-2:** As an **instructor**, I want to export the finished scenario as a `.json` file, so I can upload it to the Scenario Builder or a module instance.
* **US-3:** As an **instructor**, I want to correct my answers during the interview, so the final scenario matches my intent.
* **US-4:** As a **site admin**, I want the builder button only visible in edit mode, so regular students don't see it.

---

## 6. Acceptance Criteria

* **AC-1:** The "AI Scenario Builder" button appears in the chat header only when edit mode is on (manage capability + edit mode).
* **AC-2:** Clicking the button starts an interviewer-mode conversation.
* **AC-3:** The interviewer asks questions one at a time and confirms answers.
* **AC-4:** The AI generates a valid scenario JSON conforming to the existing schema.
* **AC-5:** The generated JSON passes validation via `scenario_loader::from_array()`.
* **AC-6:** The "Export JSON" action downloads `{scenario_id}.json`.
* **AC-7:** The exported file uploads successfully via the Scenario Builder and module filepicker.
* **AC-8:** The author can exit interviewer mode and return to normal chat without corrupting the session.

---

## 7. Out of Scope

* Editing existing scenarios via the AI builder (v1 focuses on creating new scenarios).
* Multi-scenario batch generation.
* Direct database registration without export (optional FR-13, can be deferred).
* Localization of the interviewer prompt (admin-authored content).

---

## 8. Release & Versioning

* **Version Type:** Minor Feature (`1.x.0`) per tagging policy.
* **Version Bump:** `$plugin->version` incremented, `$plugin->release` updated.
* **Documentation:** Update `version_history.md` / `release_history.md`.

---

## 9. Open Questions

* Should the AI builder also support **editing** an existing scenario (loaded from the registry) in v1, or strictly new scenarios?
* Should the generated scenario be **auto-registered** to the site registry, or only exported as a file?
* How should the interviewer handle free-form vs. structured answers (e.g., multiple-choice for mood/pronoun vs. free text for backstory)?
* Should the interview support **saving progress** and resuming later?
