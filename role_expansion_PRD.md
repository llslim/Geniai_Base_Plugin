# Product Requirement Document (PRD)
## Feature: Role Expansion — Generalized Bot Persona Roles

---

## 1. Introduction & Objectives

### 1.1. Background
The AACURA chatbot currently simulates a single role configuration:
- **Student** → Speech-Language Pathology (SLP) trainee / future therapist
- **Bot** → Parent of a child with AAC/communication needs
- **Evaluation criteria** → Pedagogical checks tuned to parent-teacher interactions (empathy_check, jargon_check, etc.)

This is hardcoded throughout the codebase — in the persona schema (which assumes `child_preferred_pronoun`, a parent backstory about a child), the evaluation validation types (parent-teacher focused), the prompt template defaults (NEVER validate or praise the teacher), the scenario states, and the persona dropdown label ("Parent Persona").

Future SLPs need to practice communicating with **doctors, device manufacturers, AAC Users, school administrators, IEP teams, other therapists, and insurance representatives** — each requiring different communication strategies, evaluation criteria, and bot behaviors.

### 1.2. Objectives
* **Generalize the persona schema** so any role can be defined (not just parent).
* **Generalize the evaluation criteria** so validation types are configurable per scenario (not hardcoded parent-teacher checks).
* **Generalize the prompt template defaults** so the hardcoded rules (e.g., "NEVER validate the teacher") are removed from the default template and replaced with role-appropriate defaults.
* **Generalize the chat UI** so the persona label reflects the actual role (not just "Parent Persona").
* **Maintain the LAFF "Don't Cry" framework as a universal pedagogical foundation** across **every** role interaction — the training goals (Listen & Empathize, Ask & Focus, Find First Steps; Criticize-React-Yack rules) remain constant whether the trainee is speaking with a parent, doctor, manufacturer, AAC User, or administrator.
* **Maintain backward compatibility** — existing parent scenarios continue to work unchanged.

---

## 2. Current Architecture (As-Is) — Hardcoded Assumptions

### 2.1. Persona schema assumes parent
The `persona` object in every scenario JSON requires:
- `child_preferred_pronoun` (irrelevant for doctor, manufacturer, admin roles)
- `backstory` structured around a child's diagnosis
- `name` conventionally includes `(Parent)`

### 2.2. Default prompt template assumes teacher-as-student
The `DEFAULT_TEMPLATE` in `prompt_renderer` hardcodes:
```
- NEVER start your response with 'I understand'... (teacher interaction rules)
- NEVER validate or praise the teacher's explanation.
```
These rules are specific to parent-teacher roleplays and nonsensical for a doctor, administrator, or AAC User persona.

### 2.3. Validation types assume parent-teacher interaction
Hardcoded in `generative_ai_api_strategy::evaluate_input()`:
- `empathy_check` — "Determine if the teacher expressed genuine empathy..."
- `jargon_check` — "Scan the teacher's message for unexplained clinical jargon..."
- `de_escalation_check` — "Evaluate if the teacher spoke respectfully..."
- `clarification_check` — "Evaluate if the teacher clearly explained..."

### 2.4. Chat UI labels assume parent
- The persona dropdown renders `personas_options` with no role prefix.
- The message display shows "You (Teacher)" vs "Parent Persona".
- The learning objectives reference parent-teaching scenarios.

### 2.5. Current scenario fields do not support role metadata
No field exists for:
- Role type (parent, doctor, manufacturer, admin, AAC User, etc.)
- Role-specific traits (formality level, power dynamic, technical expertise)
- Relationship to the trainee (client, supervisor, colleague, patient)

---

## 2.6. LAFF "Don't Cry" is the Pedagogical Anchor (Universal Across Roles)
While the *persona* roles expand, the **LAFF "Don't Cry"** communication framework remains the **constant pedagogical foundation** for evaluation. It is not parent-specific — it is a universal model for **interprofessional and client-centered communication** that should govern how the trainee interacts with **every** role:

### The LAFF Principles (Applied to Any Role)
| Principle | Original (Parent) | Generalized (Any Role) |
| :--- | :--- | :--- |
| **L — Listen, Empathize & Validate** | Validate parent's frustration | Validate the other party's perspective, concern, or expertise before advancing the agenda |
| **A — Ask Open-Ended Questions** | Ask about home routines | Ask open questions to understand the party's context, constraints, and priorities |
| **F — Focus on the Issues** | Focus on functional communication goals | Keep the conversation centered on practical, patient-centered priorities |
| **F — Find First Steps** | Partner on home action steps | Partner on concrete, mutually-agreed next actions appropriate to the role context |

### The "Don't Cry" Rules (Always Prohibited)
| Rule | Definition |
| :--- | :--- |
| **C — Criticize / Compare** | Never criticize the party's prior efforts or compare to others |
| **R — React / Escalation** | Avoid becoming defensive when the party expresses frustration, skepticism, or resistance |
| **Y — Yack / Jargon** | Avoid unexplained clinical/acronym-heavy language — always explain in plain, accessible terms |

### What This Means for the Role Expansion
* The **LAFF principles** are baked into the **default prompt template** and **default validation guideline** regardless of role type.
* Scenario authors may **surface** LAFF checks for any role (e.g., `empathy_check`, `jargon_check`, `de_escalation_check`) and may add **role-specific** validation types that complement (never replace) the LAFF core.
* The **default templates** must present LAFF rules generically (e.g., "Use plain language, do not use unexplained jargon") rather than parent-specific phrasing ("NEVER validate the teacher").
* The bot **may** respond in a role-appropriate voice (formal doctor, sales-oriented manufacturer), but the trainee is always evaluated against LAFF-aligned communication competence.

---

## 3. Proposed Schema Expansion

### 3.1. New `role` field in persona

```json
{
  "scenario_id": "anna",
  "prompt_template": "Your name is {{persona_name}}...",
  "persona": {
    "name": "Anna Charles (Parent)",
    "role": {
      "type": "parent",
      "display_label": "Parent Persona",
      "relationship_to_trainee": "Client (parent of AAC user)",
      "formality_level": "informal",
      "power_dynamic": "peer",
      "technical_expertise": "low"
    },
    "child_preferred_pronoun": "she/her",
    "backstory": "Your name is Anna Charles...",
    "initial_mood": "overwhelmed",
    "communication_style": "Frustrated, defensive..."
  },
  "learning_objectives": [...],
  "states": {...}
}
```

### 3.2. Example: Doctor role

```json
{
  "scenario_id": "doctor_consult",
  "persona": {
    "name": "Dr. Sarah Chen (Developmental Pediatrician)",
    "role": {
      "type": "doctor",
      "display_label": "Physician",
      "relationship_to_trainee": "Referring provider",
      "formality_level": "formal",
      "power_dynamic": "hierarchical_superior",
      "technical_expertise": "high"
    },
    "backstory": "You are a developmental pediatrician with 15 years of experience...",
    "initial_mood": "professional",
    "communication_style": "Precise, evidence-based, occasionally impatient with vague questions."
  },
  "learning_objectives": [
    "concise_history_taking",
    "medical_jargon_management",
    "interprofessional_collaboration"
  ],
  "states": {
    "START": {
      "bot_prompt": "I've reviewed the referral. Tell me about your concerns with this patient's communication development.",
      "expected_criteria": {
        "validation_type": "history_clarity_check",
        "pass_route": "EXPLORATION",
        "fail_route": "ESCALATION"
      }
    },
    "EXPLORATION": {
      "bot_prompt": "I see. And have you ruled out hearing loss as a contributing factor? What assessments have you completed?",
      "expected_criteria": {
        "validation_type": "clinical_reasoning_check",
        "pass_route": "RESOLUTION",
        "fail_route": "CONFUSION"
      }
    },
    "ESCALATION": {
      "bot_prompt": "Look, I need specific clinical data to make a recommendation. Vague descriptions aren't helpful.",
      "expected_criteria": {
        "validation_type": "de_escalation_check",
        "pass_route": "EXPLORATION",
        "fail_route": "FAIL_STATE"
      }
    },
    "CONFUSION": {
      "bot_prompt": "I'm not following your diagnostic reasoning. Can you walk me through your assessment protocol step by step?",
      "expected_criteria": {
        "validation_type": "clarification_check",
        "pass_route": "EXPLORATION",
        "fail_route": "ESCALATION"
      }
    },
    "RESOLUTION": {
      "bot_prompt": "That's the information I needed. I'll incorporate your findings into my assessment.",
      "expected_criteria": null
    },
    "FAIL_STATE": {
      "bot_prompt": "This consultation is concluded. Please gather the requested data before scheduling a follow-up.",
      "expected_criteria": null
    }
  }
}
```

### 3.3. Example: Device Manufacturer Representative

```json
{
  "scenario_id": "manufacturer_sales",
  "persona": {
    "name": "James Wilson (Regional AAC Specialist, TalkTech Inc.)",
    "role": {
      "type": "manufacturer_rep",
      "display_label": "Device Manufacturer Rep",
      "relationship_to_trainee": "Vendor",
      "formality_level": "professional",
      "power_dynamic": "peer_to_peer",
      "technical_expertise": "high"
    },
    "backstory": "You are a regional AAC specialist for TalkTech Inc...",
    "initial_mood": "helpful",
    "communication_style": "Sales-oriented, enthusiastic about product features, uses brand-specific terminology."
  },
  "learning_objectives": [
    "vendor_evaluation",
    "product_needs_assessment",
    "sales_literacy"
  ],
  "states": {
    "START": {
      "bot_prompt": "Thanks for meeting with me! I'd love to show you what our new SGD can do for your clients.",
      "expected_criteria": {
        "validation_type": "needs_assessment_check",
        "pass_route": "EXPLORATION",
        "fail_route": "ESCALATION"
      }
    }
  }
}
```

### 3.4. Example: AAC User (Self-Advocate)

```json
{
  "scenario_id": "aac_user_advocate",
  "persona": {
    "name": "Maya Johnson (AAC User & Self-Advocate)",
    "role": {
      "type": "aac_user",
      "display_label": "AAC User / Self-Advocate",
      "relationship_to_trainee": "Consumer / Client",
      "formality_level": "informal",
      "power_dynamic": "peer",
      "technical_experience": "experiential"
    },
    "backstory": "You are a 28-year-old AAC user who has used a speech-generating device since age 10...",
    "initial_mood": "assertive",
    "communication_style": "Direct, occasionally frustrated by assumptions, values autonomy."
  },
  "learning_objectives": [
    "patient_centered_care",
    "autonomy_respect",
    "active_listening"
  ],
  "states": {...}
}
```

### 3.5. `role` field is optional (backward compatible)
- If `role` is present, the schema uses its values for UI labels and prompt template context.
- If `role` is **absent**, the system assumes the legacy parent role defaults (backward compatible).
- The `child_preferred_pronoun` field remains optional — it's only relevant for parent roles.

---

## 4. Functional Requirements

### 4.1. Schema Changes
* **FR-1:** Add an **optional `role` object** to the persona JSON schema with the following fields:
  - `type` (string) — e.g., `parent`, `doctor`, `manufacturer_rep`, `aac_user`, `school_admin`, `iep_coordinator`, `insurance_rep`, `other_therapist`
  - `display_label` (string) — e.g., "Physician", "Device Manufacturer Rep"
  - `relationship_to_trainee` (string) — e.g., "Referring provider", "Vendor", "Consumer"
  - `formality_level` (enum: `formal`, `professional`, `informal`)
  - `power_dynamic` (enum: `hierarchical_superior`, `peer`, `hierarchical_subordinate`)
  - `technical_expertise` (enum: `high`, `medium`, `low`, `experiential`)
* **FR-2:** Add a new getter `get_role(): ?array` to `scenario_definition`.
* **FR-3:** Update `scenario_loader::from_array()` to parse the optional `role` object.

### 4.2. Default Prompt Template Update
* **FR-4:** The `DEFAULT_TEMPLATE` in `prompt_renderer` must be updated to use **role-conditional rules** instead of parent-specific hardcodes.
* **FR-5:** The `role` metadata (`{{role_type}}`, `{{formality_level}}`, `{{power_dynamic}}`, `{{technical_expertise}}`) should be added as available placeholders.
* **FR-6:** Existing parent scenarios that omit `role` must continue to work with the legacy behavior.

### 4.3. Evaluation Criteria (Validation Types)
* **FR-7:** The `evaluate_input()` method must support **role-appropriate validation types**.
* **FR-8:** Add a configurable `validation_types` field to the scenario JSON (or keep as free-text in `expected_criteria.validation_type`) so scenario authors can define their own validation types that the LLM evaluates.
* **FR-9:** Remove the hardcoded switch-case in `evaluate_input()` and send the validation type + guideline as part of the LLM prompt.

### 4.4. Chat UI Updates
* **FR-10:** The persona dropdown label must use `role.display_label` when available, falling back to "Parent Persona".
* **FR-11:** The message sender label should use the role display label (e.g., "Physician" instead of "Parent Persona").
* **FR-12:** The student/trainee label should be configurable per scenario (e.g., "You (Clinician)" instead of hardcoded "You (Teacher)").

### 4.5. Universal LAFF "Don't Cry" Foundation
* **FR-13:** The **LAFF "Don't Cry"** framework must remain the universal pedagogical foundation applied to **every** role interaction — parents, doctors, manufacturers, AAC Users, admins, IEP teams, and insurance reps.
* **FR-14:** The **default prompt template** must present LAFF rules in a **role-generic** form (e.g., "Use plain language; explain any unavoidable jargon") rather than parent-specific phrasing ("NEVER validate the teacher"), so the rules hold for any role.
* **FR-15:** The **default validation guidelines** for `empathy_check`, `jargon_check`, `de_escalation_check`, and `clarification_check` must be framed generically so they apply to any role's communication, not just parent interactions.
* **FR-16:** Scenario authors **may** add role-specific validation types, but these **complement** (never replace) the LAFF core checks. The LAFF-aligned checks (`empathy_check`, `jargon_check`, `de_escalation_check`, `clarification_check`) remain available for all scenarios.

### 4.6. Backward Compatibility
* **FR-17:** All existing parent scenarios (anna, brianna, cathy, mary) must continue to work without any field changes.
* **FR-18:** The default template must fall back to the current parent-behavior rules when no `role` is specified (preserving the original LAFF-aligned parent experience).
* **FR-19:** The scenario builder must allow creating scenarios with new role types (the role expansion should be compatible with the AI builder from issue #7).

---

## 5. Technical Architecture

### 5.1. Modified Components

| Component | Change |
| :--- | :--- |
| `scenario_definition.php` | Add `role` property + `get_role()` getter. Add `{{role_type}}`, `{{formality_level}}`, etc. as available placeholder sources. |
| `scenario_loader.php` | Parse `persona.role` from JSON. |
| `prompt_renderer.php` | Update `DEFAULT_TEMPLATE` to use role-conditional rules. Add role placeholders to substitution map. |
| `generative_ai_api_strategy.php` | Remove hardcoded switch-case in `evaluate_input()`. Send validation_type + guideline generically via LLM prompt. |
| `mod/aacurachat/templates/chat.mustache` | Use `role.display_label` for persona labels. |
| `mod/aacurachat/view.php` | Pass role metadata to template. |
| `local/aacuracore/amd/src/chat.js` | Update "Parent Persona" label to dynamic role label. |
| `scenarios/*.json` | Add `role` object to all 4 preloaded JSONs (parent role defaults). |
| `scenario_builder.html` | Add `role` fields to the builder form. |

### 5.2. Placeholder Expansion

Add to `prompt_renderer::$replacements`:
```php
'{{role_type}}'             => $role['type'] ?? 'parent',
'{{role_display_label}}'    => $role['display_label'] ?? 'Parent Persona',
'{{relationship_to_trainee}}' => $role['relationship_to_trainee'] ?? '',
'{{formality_level}}'       => $role['formality_level'] ?? 'informal',
'{{power_dynamic}}'         => $role['power_dynamic'] ?? 'peer',
'{{technical_expertise}}'   => $role['technical_expertise'] ?? 'low',
```

### 5.3. Default Template Conditional Logic

```php
// Inside render() or as a DEFAULT_TEMPLATE builder:
if ($role['type'] !== 'parent') {
    // Use a more generic default template suited to the role
}
```

---

## 6. User Stories

* **US-1:** As an **SLP trainee**, I want to practice communicating with different professionals (doctors, manufacturers, admins), so I'm prepared for real interprofessional collaboration.
* **US-2:** As an **instructor**, I want to create scenarios with any role (not just parents), so my students can practice a wider range of clinical interactions.
* **US-3:** As a **scenario author**, I want to define custom validation types for my scenarios, so the evaluation criteria match the interaction context.
* **US-4:** As a **site administrator**, I want existing parent scenarios to continue working, so the upgrade doesn't break my curriculum.

---

## 7. Acceptance Criteria

* **AC-1:** A scenario with a `persona.role` object correctly renders role-specific labels in the chat UI.
* **AC-2:** The `DEFAULT_TEMPLATE` produces appropriate behavior for non-parent roles (doctor, manufacturer, admin, AAC User).
* **AC-3:** Custom validation types defined in `expected_criteria.validation_type` are passed to the LLM generically (no hardcoded switch-case).
* **AC-4:** All 4 preloaded parent scenarios work unchanged with no `role` field.
* **AC-5:** The new `{{role_*}}` placeholders resolve correctly in prompt templates.
* **AC-6:** All existing PHPUnit tests + scenario crawler still pass.
* **AC-7:** **LAFF "Don't Cry"** rules (Listen/Empathize, Ask, Focus, Find First Steps; no Criticize/React/Yack) are present in the default prompt template and default evaluation guidelines for **all** role types — parent, doctor, manufacturer, AAC User, admin, IEP team, and insurance rep.
* **AC-8:** Scenario authors can apply the core LAFF checks (`empathy_check`, `jargon_check`, `de_escalation_check`, `clarification_check`) to **any** role scenario, with appropriate role-appropriate validation guidelines.

---

## 8. Out of Scope

* Full multi-party conversation (e.g., student + doctor + parent in one session) — each scenario is one-on-one.
* Role-specific UI theming (different colors/icons per role type) — purely informational text labels.
* Dynamic role switching mid-session — role is set per scenario.

---

## 9. Release & Versioning

* **Version Type:** Minor Feature (`1.x.0`).
* **Version Bump:** `$plugin->version` incremented, `$plugin->release` updated.
* **Documentation:** Update `version_history.md` / `release_history.md`.

---

## 10. Open Questions

* Should the `role` object support custom key-value pairs for future extensibility (e.g., `"custom_attributes": {"license_required": true}`)?
* Should the trainee label be configurable per scenario or per installation (e.g., "Clinician" vs "Teacher" vs "Therapist")?
* Should the validation type guidelines be definable inside the scenario JSON alongside `validation_type` (e.g., `"validation_guideline": "Evaluate if the trainee gathered specific clinical data..."`)?
* How should the scenario builder (issue #7) handle role selection — a dropdown of predefined roles, or free-text entry?