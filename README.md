# AACURA Backend Dialogue State Machine & Evaluation Engine (`local_aacuracore`)

Welcome to the core backend engine repository for **AACURA** (AAC Understanding & Reflective Assistant). This plugin acts as the central state machine coordinator, dialogue engine, and rubric evaluator for the chatbot training simulator in Moodle.

---

## 🚀 Architectural Design

The plugin is designed to decouple presentation from dialogue logic, utilizing classic software engineering patterns in PHP to remain highly adaptable.

### 1. State Pattern (Dialogue Management)
Instead of relying on nested conditionals or hardcoded database sequences, a formal State Pattern coordinates the student through dialogue phases. Concrete state classes (e.g. `StateIntro`, `StateExploration`, `StateEscalation`, `StateComplete`) encapsulate specific transition validations, keeping states modularized and extensible.

### 2. Strategy Pattern (Response Evaluation & Generation)
Response generation and rubric scoring logic are decoupled behind a common Strategy interface, permitting runtime strategy toggles:
* **Pattern Matching Strategy**: Evaluates criteria using regex and token overlaps.
* **Generative AI Strategy**: Executes direct API integration with Google Gemini.
* **Core AI Subsystem Strategy**: Integrates with Moodle 5.x native `\core_ai\manager` APIs.

### 3. Frontend Decoupling & Independence (Disclaimer)
While `local_aacuracore` was historically derived from the legacy `local_geniai` project, it has been completely rewritten and restructured. **There is no dependency** on the legacy `local_geniai` codebase, configuration, or database tables. The core backend communicates with its companion user interface activity module `mod_aacurachat` via clean state data objects and Moodle APIs, allowing separate scaling, updates, and styling.

---

## 📝 Scenario Configurations & Example Graph Structure

Conversations in AACURA are driven by structured JSON scenarios defined as directed graphs. Each node represents a conversation state containing prompt templates, expected criteria, and conditional transition links.

### Example Scenario Schema Snippet:
```json
{
  "code": "parent_exploration",
  "name": "LAFF Strategy: parent exploration phase",
  "start_node": "intro_greeting",
  "nodes": {
    "intro_greeting": {
      "bot_prompt": "Hello, thank you for meeting with me to discuss my child's communication device...",
      "expected_strategy": "pattern_matching",
      "criteria": {
        "empathy": ["glad", "happy", "understand", "here to help"],
        "jargon_avoidance": ["!SLP", "!AAC", "!assistive tech"]
      },
      "transitions": {
        "success": "gather_information",
        "fallback": "intro_greeting_retry"
      }
    }
  }
}
```
*For a complete guide on how to design and upload scenarios, see [scenario_creation_guide.md](scenario_creation_guide.md).*

---

## 📥 Installation Guide

Follow these steps to deploy the plugin into your Moodle environment:

### Option A: Installation via Composer (Recommended)
Add the repository to your Moodle project's root `composer.json` and require it:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/llslim/moodle-plugin-aacuracore.git"
    }
],
"require": {
    "llslim/moodle-plugin-aacuracore": "dev-dev"
}
```

Then run:
```bash
composer update
```

### Option B: Manual Installation
1. Download the latest release or ZIP archive from [llslim/moodle-plugin-aacuracore](https://github.com/llslim/moodle-plugin-aacuracore).
2. Install via Moodle Administration:
   ```
   Site Administration → Plugins → Install Plugins → Install plugin from ZIP file
   ```
   Or clone directly into Moodle's `local/` directory:
   ```bash
   git clone https://github.com/llslim/moodle-plugin-aacuracore.git local/aacuracore
   ```

### ⚙️ Post-Installation Setup
Once installed, configure the AI Providers and plugin settings. See [siteadmin_aacura_setup.md](file:///d:/Antigravity1x_backup/windows-projects/AAC-RERC%20Chatbot/siteadmin_aacura_setup.md) for full instructions.

---

## 📂 Core Documentation & Specification Map

Refer to these dedicated guides to understand and manage specific components:

| Documentation Link | Description / Scope |
| :--- | :--- |
| ⚙️ **[siteadmin_aacura_setup.md](file:///d:/Antigravity1x_backup/windows-projects/AAC-RERC%20Chatbot/siteadmin_aacura_setup.md)** | Step-by-step Moodle site setup guide for AI providers and AACURA settings. |
| 🤖 **[ai_strategy.md](ai_strategy.md)** | Explains responses, Google Gemini REST compliance, and rubric scoring. |
| 💬 **[laff_framework_guide.md](laff_framework_guide.md)** | Overview of the LAFF Don't Cry communication strategy and evaluation checks. |
| 💯 **[scoring_explained.md](scoring_explained.md)** | Explains how final grading scores are calculated, deducted, and synchronized with Gradebook. |
| 📖 **[scenario_creation_guide.md](scenario_creation_guide.md)** | Guidelines on preloaded scenario configurations and custom JSON schema. |
| � **[ai_scenario_builder_PRD.md](ai_scenario_builder_PRD.md)** | PRD for the AI-driven interactive scenario builder (interviewer-mode scenario creation). |
| �🧪 **[scenario_test.md](scenario_test.md)** | Diagnostics for crawler routing and dialogue graph checks. |
| 🧪 **[test_suite_guide.md](test_suite_guide.md)** | Guide for executing PHPUnit test suites and QA validation rules. |
| 🏷️ **[version_history.md](version_history.md)** | Release notes mapping commit hashes to semantic release versions. |
| 🛠️ **[REFACTORING_GUIDE.md](REFACTORING_GUIDE.md)** | Rationale and step-by-step notes on the renaming refactoring. |

---

## 🔄 Automated Database Migration for Existing Sites

For sites upgrading from legacy `local_geniai` / `mod_geniai` installations, run the included CLI migration utility. This renames existing database tables, copies legacy records, and updates settings in `mdl_config_plugins`:

```bash
php local/aacuracore/cli/migrate_geniai_to_aacura.php
```

**Remapped Tables**:
* `local_geniai_sessions` → `local_aacuracore_sessions`
* `local_geniai_scenarios` → `local_aacuracore_scenarios`
* `local_geniai_evaluations` → `local_aacuracore_evaluations`
* `geniai` → `aacurachat` (activity module table)

---

## 🛠️ Developer CLI Diagnostics Cheatsheet

Use the scenario crawler diagnostic script to verify state graphs and simulate conversation sessions:

```bash
php local/aacuracore/cli/aacuradebug_scenario.php [options]
```

### Parameter Guide
* **`--scenario <code_name>`**: Runs the diagnostic graph crawler only on a specific scenario (e.g. `--scenario parent_exploration`).
* **`--enable-debug`**: Enables developer debugging (`debug=developer`, `debugdisplay=1`) on the Moodle site.
* **`--disable-debug`**: Disables developer debugging, reverting Moodle back to its production settings.
* **`--check-configs`**: Tests active LLM provider API credentials and prints system availability diagnostics.
* **`--phpunit`**: Automatically executes Moodle unit tests for scenarios via the `local_aacuracore\scenarios_test` class.
* **`--all`**: Runs the complete diagnostic suite (crawl all scenarios, check configurations, and execute PHPUnit validations).
