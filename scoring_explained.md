# AACURA Chatbot Scoring & Evaluation Logic

This document details how the **AACURA** chatbot training simulator calculates student grades, logs criteria analytics, compiles rubric feedback, and synchronizes scores with the Moodle Gradebook.

---

## 📊 Core Scoring Architecture

The simulator grades student performance out of **10.00 points**. 

Rather than awarding points incrementally, the system uses a **deductive criteria system** based on simulated conversation turns and active checks:

1. **Perfect Baseline**: Every conversation starts with a baseline score of **10.00**.
2. **Turn Evaluations**: At key dialogue states, the student's message input is evaluated using the active evaluation strategy (regex pattern matching or AI models).
3. **Analytics Logging**: The result of each check is stored as a record in the `local_aacuracore_analytics` table:
   * **`1.00` (PASS)**: The student successfully demonstrated the rubric-aligned communication move.
   * **`0.00` (FAIL)**: The student missed an opportunity or violated a guideline (e.g. used unexplained jargon).
4. **Final Scoring Formula**: Upon reaching any terminal completion state (`RESOLUTION` or `FAIL_STATE`), the final grade is calculated by subtracting 1 point for each failed check:

$$\text{Final Grade} = \max(0,\, 10.00 - \text{Missed Checks Count})$$

---

## 🔍 Validation Checks & Deductions

The evaluation engine supports four primary criteria checks aligned with the **LAFF (Listen, Ask, Focus, Find)** communication framework:

| Metric Type | Framework Step | Evaluation Rule | Deduction Condition |
| :--- | :--- | :--- | :--- |
| `empathy_check` | **Step 1: Listen** | Validates that the student starts the greeting with a clear statement of empathy. | A `0.00` is logged if no empathetic statement is detected in Turn 1. |
| `clarification_check` | **Step 2: Ask** | Verifies that the student asks clarifying questions and requests permission to take notes. | A `0.00` is logged if the student fails to ask clarifying questions or note permission. |
| `de_escalation_check` | **Step 3: Focus** | Checks that the student de-escalates the parent's concern instead of jumping directly to administrative escalation. | A `0.00` is logged if the student responds defensively or dismisses the parent. |
| `jargon_check` | **Step 4: Find** | Inspects the input to ensure that explanations are simple and free of unexplained jargon. | A `0.00` is logged if any restricted terminology (e.g. "SLP", "AAC", "IEP") is used without definition. |

> [!NOTE]
> If a student's conversation path completely bypasses a check (e.g., they successfully resolve the issue on the main path without triggering the parent's concern), the bypassed check is **not** evaluated, and no deduction occurs.

---

## 🛠️ Concrete Example: The Failed Path

For instance, during a failed simulation path (e.g. using `cli/test_steve_gradebook_fail.php`):

1. **Turn 1**: The student replies, *"Whatever. This is what we have."*
   * **Result**: Empathy check fails. Table `local_aacuracore_analytics` logs `empathy_check = 0.00`.
2. **Turn 2**: The student replies, *"I don't care, talk to the principal."*
   * **Result**: De-escalation check fails. Table `local_aacuracore_analytics` logs `de_escalation_check = 0.00`.
3. **Completion**: The simulation terminates at `FAIL_STATE`.
   * **Grade Calculation**:
     $$\text{Failed Checks Count} = 2 \quad (\text{empathy\_check} \text{ and } \text{de\_escalation\_check})$$
     $$\text{Final Score} = 10.00 - 2 = 8.00$$

---

## 💾 Storage & Gradebook Synchronization

Once the final score is calculated:

1. **Evaluation Persistence**: The final score and strategy-generated HTML feedback are written to the `local_aacuracore_evaluations` table:
   * `sessionid`: The active student chat session ID.
   * `score`: The final calculated score (e.g. `8.00` or `10.00`).
   * `feedback`: The formatted HTML rubric checklist displaying earned points and missed opportunities.
2. **Moodle Gradebook Synchronization**: The engine calls `aacurachat_grade_item_update()`, writing the raw grade directly to the student's grade record (`mdl_grade_grades.finalgrade`) associated with the active `mod_aacurachat` activity module instance.
