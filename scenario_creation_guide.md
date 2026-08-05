# EDURA Preloaded Scenarios & Custom Scenario Creation Guide

This document details the 4 preloaded parent roleplay scenarios in EDURA and provides a step-by-step technical guide for instructors to build and upload custom scenario JSON profiles.

---

## 1. Preloaded Parent Personas Matrix

| Scenario Code | Persona Name | Child Diagnosis & Context | Initial Mood & Style | Pedagogical Objectives |
| :--- | :--- | :--- | :--- | :--- |
| `anna` | **Anna Charles** | Mother of **Sarah** (4yo, Autism). Sarah just started pre-K, uses an iPad communication app. Single mother working 2 jobs. | **Overwhelmed, defensive, guilt-ridden.** Uses blunt vocabulary. | `active_listening`<br>`empathy_check`<br>`note_permission` |
| `brianna` | **Brianna Mitchell** | Mother of **Wesley** (8yo, Severe Apraxia). Speech is difficult to understand. Uses handheld AAC device; feeling socially isolated. | **Anxious, worried.** Speaks rapidly about Wesley's isolation. | `de_escalation`<br>`active_listening`<br>`jargon_free_explanation` |
| `cathy` | **Cathy Fratner** | Mother of **Charlie** (2yo, Down Syndrome). Early intervention iPad user. Husband skeptical of AAC hindering natural speech. | **Confused, doubtful.** Eager to learn, lost with tech. | `clarification_check`<br>`jargon_free_explanation`<br>`empathy_check` |
| `mary` | **Mary** | Mother of a **non-verbal 6-year-old child**. Overwhelmed and defensive regarding school accommodations. | **Defensive, blunt.** Highly emotional and protective. | `active_listening`<br>`de_escalation` |

---

## 2. Dynamic Scenario JSON Schema Structure

Scenarios are defined as directed graphs using standardized JSON files. Below is the blueprint schema required by `scenario_loader.php`:

```json
{
  "scenario_id": "custom_parent_01",
  "persona": {
    "name": "Parent Full Name",
    "backstory": "Detailed parent background, child diagnosis, therapy history, and emotional concerns.",
    "initial_mood": "defensive | anxious | confused | overwhelmed",
    "communication_style": "Communication style and tone description."
  },
  "learning_objectives": [
    "active_listening",
    "de_escalation",
    "jargon_free_explanation"
  ],
  "states": {
    "START": {
      "bot_prompt": "Opening parent dialogue line when the chat begins.",
      "expected_criteria": {
        "validation_type": "empathy_check",
        "pass_route": "EXPLORATION",
        "fail_route": "ESCALATION"
      }
    },
    "EXPLORATION": {
      "bot_prompt": "Parent response when the student demonstrates empathy.",
      "expected_criteria": {
        "validation_type": "jargon_check",
        "pass_route": "RESOLUTION",
        "fail_route": "CONFUSION"
      }
    },
    "ESCALATION": {
      "bot_prompt": "Parent escalation line if student fails empathy check.",
      "expected_criteria": {
        "validation_type": "de_escalation_check",
        "pass_route": "EXPLORATION",
        "fail_route": "FAIL_STATE"
      }
    },
    "CONFUSION": {
      "bot_prompt": "Parent confusion line when student uses heavy clinical jargon.",
      "expected_criteria": {
        "validation_type": "clarification_check",
        "pass_route": "EXPLORATION",
        "fail_route": "ESCALATION"
      }
    },
    "RESOLUTION": {
      "bot_prompt": "Successful resolution line.",
      "expected_criteria": null
    },
    "FAIL_STATE": {
      "bot_prompt": "Session termination line following unmanaged escalation.",
      "expected_criteria": null
    }
  }
}
```

---

## 3. Example Scenario Files

Reference JSON files are pre-packaged in the repository under `local/geniai/examples/`:
- `local/geniai/examples/scenario_anna.json`
- `local/geniai/examples/scenario_brianna.json`
- `local/geniai/examples/scenario_cathy.json`
- `local/geniai/examples/scenario_mary.json`

---

## 4. How to Create & Upload a Custom Scenario

Instructors can upload custom scenarios directly to any Moodle `mod_geniai` activity without touching backend code:

1. **Create the JSON file** following the schema above or editing one of the example templates in `local/geniai/examples/`.
2. **Log into Moodle** as an Instructor or Administrator.
3. **Turn Editing On** and navigate to your course's EDURA Chatbot Activity module.
4. **Edit Activity Settings**:
   - Scroll down to the **Custom Scenario Profile** upload section.
   - Drag & drop your `.json` file into the file picker.
5. **Save and Display**: The EDURA engine will automatically validate the uploaded JSON structure and instantiate your custom directed graph for students.
