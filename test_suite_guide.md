# `local_geniai` Core Engine & API Integration Test Suite Guide

This document details the automated integration test suite for the `local_geniai` Moodle plugin. The test suite validates core state machine execution, scenario loading, session management, and Moodle 5.2 web service API compliance.

---

## 1. Overview of the Test Suite

The test suite consists of 17 individual test assertions grouped across 4 core component categories:
1. **Scenario Loader Component**
2. **Bot Engine & Session Lifecycle Component**
3. **History Web Service API Component**
4. **Chat Web Service API Component**

The test suite runs automatically on the remote server as part of the GitHub Actions deployment workflow (`.github/workflows/deploy.yml`). If any test fails, deployment halts immediately to protect production integrity.

---

## 2. Test Cases Breakdown

### Category 1: Scenario Loader Component

| Test Assertion | Function Tested | Description & Expected Result |
| :--- | :--- | :--- |
| `Scenario loader loads Anna` | `scenario_loader::load('anna', $cmid)` | Loads Anna Charles scenario profile (`parent_confrontation_01`). Verifies that `get_id()` returns `'anna'`. |
| `Anna persona metadata is populated` | `$anna->get_persona()` | Confirms persona array contains required metadata fields (`name`, `backstory`, `communication_style`, `initial_mood`). |
| `Anna START state exists via get_state()` | `$anna->get_state('START')` | Validates that state lookup via `get_state('START')` returns the starting dialogue node. |
| `Anna START state exists via get_state_node()` | `$anna->get_state_node('START')` | Validates backwards-compatibility method alias `get_state_node('START')`. |
| `Scenario loader loads Brianna` | `scenario_loader::load('brianna', $cmid)` | Loads Brianna Mitchell scenario profile (`brianna_apraxia_01`). Verifies `get_id()` returns `'brianna'`. |
| `Scenario loader loads Cathy` | `scenario_loader::load('cathy', $cmid)` | Loads Cathy Fratner scenario profile (`cathy_downsyndrome_01`). Verifies `get_id()` returns `'cathy'`. |

---

### Category 2: Bot Engine & Session Lifecycle Component

| Test Assertion | Function Tested | Description & Expected Result |
| :--- | :--- | :--- |
| `Bot Engine initializes session to START` | `bot_engine->__construct()` | Instantiates state machine coordinator and verifies `$sessionrecord->current_state` is set to `'START'`. |
| `Bot Engine initializes scenario to Anna` | `bot_engine->get_session_record()` | Verifies `$sessionrecord->scenariocode` matches `'anna'`. |
| `Reset session sets current_state back to START` | `bot_engine->reset_session()` | Deletes existing message records, clears analytics, and resets state graph to `'START'`. |
| `Reset session re-seeds opening prompt into messages` | `bot_engine->get_messages()` | Confirms initial parent dialogue prompt is re-inserted into `local_geniai_messages` table upon reset. |

---

### Category 3: History Web Service API Component

| Test Assertion | Function Tested | Description & Expected Result |
| :--- | :--- | :--- |
| `History API clear returns result=true string` | `api::history_api($courseid, 'clear')` | Invokes clear history action and verifies return array contains string `"result" => "true"` conforming to Moodle 5.2 `PARAM_TEXT` schema. |
| `History API clear returns initial prompt array` | `json_decode($clearRes['content'])` | Verifies cleared chat history payload contains an initial system role message array. |
| `History API history returns result=true string` | `api::history_api($courseid, 'history')` | Invokes conversation history fetch and verifies return array contains string `"result" => "true"`. |

---

### Category 4: Chat Web Service API Component

| Test Assertion | Function Tested | Description & Expected Result |
| :--- | :--- | :--- |
| `Chat API persona switch returns result=true string` | `api::chat_api('$$persona=brianna$$', $courseid)` | Sends special persona command `$$persona=brianna$$` and verifies `"result" => "true"` response string. |
| `Chat API persona switch returns opening prompt HTML` | `$personaRes['content']` | Confirms persona switch returns opening parent dialogue prompt formatted as HTML. |
| `Chat API process turn returns result=true string` | `api::chat_api($message, $courseid)` | Sends student message turn to `chat_api` and verifies `"result" => "true"` response string. |
| `Chat API process turn returns bot dialogue response HTML` | `$turnRes['content']` | Confirms active response strategy evaluates student input and returns parent's dialogue response formatted as HTML. |

---

## 3. How to Run Tests Manually

To run the integration test suite manually on the server via SSH:

```bash
ssh llslim-aac-learn@18.210.251.16 "php /tmp/run_tests.php"
```

Expected Output:
```
=========================================
RUNNING LOCAL_GENIAI INTEGRATION TEST SUITE
=========================================
 [PASS] Scenario loader loads Anna
 [PASS] Anna persona metadata is populated
 [PASS] Anna START state exists via get_state()
 [PASS] Anna START state exists via get_state_node()
 [PASS] Scenario loader loads Brianna
 [PASS] Scenario loader loads Cathy
 [PASS] Bot Engine initializes session to START
 [PASS] Bot Engine initializes scenario to Anna
 [PASS] Reset session sets current_state back to START
 [PASS] Reset session re-seeds opening prompt into messages
 [PASS] History API clear returns result=true string
 [PASS] History API clear returns initial prompt array
 [PASS] History API history returns result=true string
 [PASS] Chat API persona switch returns result=true string
 [PASS] Chat API persona switch returns opening prompt HTML
 [PASS] Chat API process turn returns result=true string
 [PASS] Chat API process turn returns bot dialogue response HTML

=========================================
TEST SUMMARY: 17 Passed, 0 Failed
=========================================
```
