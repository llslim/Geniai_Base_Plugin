# Technical Implementation Guide: How Validation Checks Are Performed in EDURA

This document explains in detail how EDURA evaluates student text messages against validation checks (`validation_type`). It covers the **Strategy Pattern architecture**, the **Generative AI (LLM) Strategy**, the **Pattern Matcher (Regex) Strategy**, and fallback execution paths.

---

## 1. Architectural Overview & Strategy Selection

When a student sends a message, the central state coordinator ([bot_engine.php](file:///d:/Antigravity1x_backup/windows-projects/AAC-RERC%20Chatbot/local/aacura_core/classes/bot_engine.php)) retrieves the current active dialogue node and inspects its `validation_type` rule.

```
                      ┌────────────────────────────────────────────────────────┐
                      │                   Student Text Input                   │
                      └──────────────────────────┬─────────────────────────────┘
                                                 │
                                                 ▼
                      ┌────────────────────────────────────────────────────────┐
                      │              Bot Engine (State Machine)                │
                      │       Retrieves active node validation_type           │
                      └──────────────────────────┬─────────────────────────────┘
                                                 │
                             ┌───────────────────┴───────────────────┐
                             │                                       │
                             ▼                                       ▼
            ┌─────────────────────────────────┐     ┌─────────────────────────────────┐
            │   Generative AI (LLM) Strategy   │     │    Regex Matcher Strategy       │
            │   (OpenAI / LLM API Wrapper)    │     │    (Pattern / Keyword Filter)   │
            └────────────────┬────────────────┘     └────────────────┬────────────────┘
                             │                                       │
                             └───────────────────┬───────────────────┘
                                                 │
                                                 ▼
                                ┌─────────────────────────────────┐
                                │       Boolean Result (1 / 0)    │
                                │   PASS -> pass_route            │
                                │   FAIL -> fail_route            │
                                └─────────────────────────────────┘
```

The system selects between two evaluation strategies based on Moodle's administrative configuration (`local_aacura_core` settings):
1. **Generative AI Strategy (`generative_ai_api_strategy.php`)**: Uses OpenAI LLM zero-shot prompt evaluation.
2. **Regex Matcher Strategy (`regex_matcher_strategy.php`)**: Uses deterministic pattern and keyword matching (used directly in offline/standalone mode or as an emergency fallback if the LLM API times out).

---

## 2. Generative AI (LLM) Check Execution

When configured to use Generative AI, EDURA constructs a targeted **evaluative zero-shot system prompt** sent to the LLM API endpoint.

### System Prompt Template
```json
[
  {
    "role": "system",
    "content": "You are an expert pedagogical evaluator. Your job is to analyze a teacher's message during a simulated parent-teacher roleplay meeting.\n\nPedagogical Guideline to evaluate: {GUIDELINE_PROMPT}\n\nRespond with only 'yes' (if they passed/met the criteria) or 'no' (if they failed/missed the opportunity)."
  },
  {
    "role": "user",
    "content": "Teacher's statement to evaluate: \"{STUDENT_TEXT_INPUT}\""
  }
]
```

### Detailed Evaluation Guidelines by Check Type

#### 1. `empathy_check` (Listen & Empathize)
* **LLM Guideline Directive**:
  > *"Determine if the teacher expressed genuine empathy, active listening, or validated the parent's feelings/frustration. The teacher must sound supportive and understanding, not defensive or purely technical."*
* **Evaluation Rule**: The LLM scans for supportive tone, emotional validation phrases, and acknowledgment of parent burden.
* **Return**: `'yes'` $\rightarrow$ `PASS` (1.00 score log) | `'no'` $\rightarrow$ `FAIL` (0.00 score log).

#### 2. `jargon_check` (Don't Yack Jargon)
* **LLM Guideline Directive**:
  > *"Scan the teacher's message for unexplained clinical jargon, acronyms, or professional abbreviations (e.g. 'AAC', 'SGD', 'IEP', 'SLP', 'apraxia'). If jargon was used, the teacher MUST have explained or introduced its definition in a parent-friendly way. If they used unexplained terms, they fail. If no jargon was used at all, they pass."*
* **Evaluation Rule**: 
  - If **NO jargon** is present $\rightarrow$ `'yes'` (PASS).
  - If jargon is present **AND explained** $\rightarrow$ `'yes'` (PASS).
  - If jargon is present **WITHOUT explanation** $\rightarrow$ `'no'` (FAIL).

#### 3. `de_escalation_check` (Don't React)
* **LLM Guideline Directive**:
  > *"Evaluate if the teacher spoke respectfully, apologized or validated the parent's concern, and actively attempted to de-escalate the confrontation. Defensive, dismissive, or passive-aggressive responses fail."*
* **Evaluation Rule**: Evaluates emotional posture during confrontation. Defensiveness or escalation yields `'no'`.

#### 4. `clarification_check` (Ask & Focus)
* **LLM Guideline Directive**:
  > *"Evaluate if the teacher clearly explained the technology/processes or politely asked clarifying questions to address the parent's confusion without overwhelming them with clinical abbreviations."*
* **Evaluation Rule**: Evaluates clarity, willingness to re-explain concepts, and focus on functional home implementation.

---

## 3. Pattern Matcher (Regex) Check Execution

In offline mode, or when the LLM API is unavailable, EDURA evaluates student text using fast, deterministic pattern matching ([regex_matcher_strategy.php](file:///d:/Antigravity1x_backup/windows-projects/AAC-RERC%20Chatbot/local/aacura_core/classes/strategy/regex_matcher_strategy.php)).

### Detailed Pattern Execution Logic

#### 1. `empathy_check` Regex Logic
- **Execution Algorithm**: Sanitizes input string to lowercase and searches for empathetic root substrings:
  ```php
  $patterns = ['sorry', 'understand', 'frustrat', 'guilt', 'help', 'support', 'hear you', 'appreciate', 'know it\'s hard'];
  ```
- **Result**: Returns `true` if any pattern string matches; otherwise `false`.

#### 2. `jargon_check` Regex Logic
- **Execution Algorithm**:
  1. Scans for known SLP clinical jargon terms using word boundaries:
     ```php
     $jargonterms = ['aac', 'sgd', 'iep', 'apraxia', 'speech-generating device'];
     ```
  2. If a jargon term is detected, it checks if explanatory context words exist in the same input turn:
     ```php
     $explanationwords = ['stand', 'mean', 'explain', 'device', 'which is', 'is a', 'program', 'refer to'];
     ```
- **Result**:
  - Used jargon + explanation words $\rightarrow$ `true` (PASS)
  - Used jargon + NO explanation words $\rightarrow$ `false` (FAIL)
  - No jargon used $\rightarrow$ `true` (PASS)

#### 3. `de_escalation_check` Regex Logic
- **Execution Algorithm**: Scans for calming/de-escalating root vocabulary:
  ```php
  $calmpatterns = ['calm', 'apologiz', 'please', 'together', 'team', 'listen', 'help', 'collaborat', 'partner'];
  ```
- **Result**: Returns `true` if any calm pattern string matches; otherwise `false`.

#### 4. `clarification_check` Regex Logic
- **Execution Algorithm**: Scans for clarifying and explanatory vocabulary:
  ```php
  $claritypatterns = ['example', 'mean', 'explain', 'tell', 'show', 'detail', 'instance'];
  ```
- **Result**: Returns `true` if any clarity pattern string matches; otherwise `false`.

---

## 4. Summary Matrix of Check Execution

| Validation Check | LLM Prompt Criteria | Regex Fallback Keywords | Pass Route Action | Fail Route Action |
| :--- | :--- | :--- | :--- | :--- |
| **`empathy_check`** | Validates parent feelings without being defensive | `sorry`, `understand`, `frustrat`, `support`, `hear you` | Transition to `EXPLORATION` | Transition to `ESCALATION` |
| **`jargon_check`** | Rejects unexplained clinical terms (`AAC`, `SGD`, `IEP`) | `aac`, `sgd`, `iep` required to be paired with `mean`, `explain`, `refer to` | Transition to `RESOLUTION` | Transition to `CONFUSION` |
| **`de_escalation_check`** | De-escalates emotional confrontational parent | `apologiz`, `please`, `together`, `team`, `partner` | Transition to `EXPLORATION` | Transition to `FAIL_STATE` |
| **`clarification_check`** | Explores home routines without technical barriers | `example`, `mean`, `explain`, `tell`, `show` | Transition to `EXPLORATION` | Transition to `ESCALATION` |
