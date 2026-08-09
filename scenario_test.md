# AACURA Chatbot Scenario & Dialogue Graph Verification Testing

This document details the automated and manual testing suites for validating **AACURA Chatbot Scenarios** (`anna`, `brianna`, `cathy`, `mary`) and their dialogue state machine configurations.

---

## 1. PHPUnit Scenario Validation Tests

Moodle unit tests validate core code in isolation. The scenarios unit test suite validates that all JSON-based scenario files are structurally sound and have correct transition paths.

* **Test Class**: `local_aacuracore\scenarios_test`
* **Test File**: [local/aacuracore/tests/scenarios_test.php](tests/scenarios_test.php)

### Test Cases Covered:
1. **`test_scenarios_loading`**:
   Loads every default scenario (`anna`, `brianna`, `cathy`, `mary`) using the `scenario_loader` and asserts that:
   - The scenario loader parses and instantiates the `scenario_definition` class.
   - Required persona metadata (`name`, `backstory`, `communication_style`) is fully populated.
   - Learning objectives are defined.
2. **`test_scenario_state_transitions`**:
   Validates the dialogue state graph for each scenario, ensuring that:
   - A `START` state exists.
   - Every state's `bot_prompt` exists.
   - If `expected_criteria` is defined, the target transitions (`pass_route` and `fail_route`) point to existing states in the configuration, preventing conversational dead-ends.

### Running Unit Tests:
Run the tests locally in your devcontainer or on the staging server:
```bash
vendor/bin/phpunit local/aacuracore/tests/scenarios_test.php
```

---

## 2. CLI Dialogue Graph Crawler & Diagnostic Tool

The custom CLI diagnostic tool runs directly against the live Moodle bootstrap and database. It crawls every conversational path from `START` to its terminal state, verifying logic, loops, and provider integrations.

* **CLI Script**: [local/aacuracore/cli/aacuradebug_scenario.php](cli/aacuradebug_scenario.php)

### Available CLI Options:
* **`--scenario=<name|all>`** (default: runs crawls for all scenarios if no other options are passed):
  - Run crawls for a specific scenario:
    ```bash
    php cli/aacuradebug_scenario.php --scenario=anna
    ```
  - Run crawls for all scenarios:
    ```bash
    php cli/aacuradebug_scenario.php --scenario=all
    ```
* **`--enable-debug`**:
  Toggles Moodle's database settings to turn ON developer-level debugging on the staging server.
  ```bash
  php cli/aacuradebug_scenario.php --enable-debug
  ```
* **`--disable-debug`**:
  Toggles database settings to turn OFF developer debugging (reverting Moodle to production state).
  ```bash
  php cli/aacuradebug_scenario.php --disable-debug
  ```
* **`--check-configs`**:
  Validates active AI provider registrations, placements, and model configurations (e.g. Gemini).
  ```bash
  php cli/aacuradebug_scenario.php --check-configs
  ```
* **`--phpunit`**:
  Automatically runs PHPUnit for the scenario tests:
  ```bash
  php cli/aacuradebug_scenario.php --phpunit
  ```
* **`--all`**:
  Runs the complete integration testing suite sequentially (enables debugging, checks database configs, crawls all scenarios, and triggers PHPUnit).
  ```bash
  php cli/aacuradebug_scenario.php --all
  ```
