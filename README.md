# AACURA Core Engine Plugin (`local_aacuracore`)

This repository contains the **core backend engine** for **AACURA** (AAC Understanding & Reflective Assistant). All dialogue state machines, LLM evaluation pipelines, and provider integrations are managed here.

---

## 📖 Key Documentation Links

> 🤖 **AI Strategy & Architecture Specification:**
> For a technical breakdown of AI response strategies, Moodle Core AI Subsystem (`\core_ai\manager`) integration, Google Gemini REST compliance, and dynamic rubric evaluation pipelines, see **[ai_strategy.md](ai_strategy.md)**.

> 🛠️ **Refactoring & Component Migration Guide:**
> For details on migrating from legacy `local_geniai` to `local_aacuracore`, see **[REFACTORING_GUIDE.md](REFACTORING_GUIDE.md)**.

> 🧪 **Integration Test Suite Guide:**
> For a detailed explanation of each automated PHP integration test case and pre-deployment verification steps, see **[test_suite_guide.md](test_suite_guide.md)**.

> 🧪 **Scenario Testing & Graph Diagnostics:**
> For details on the dialogue state machine graph crawler script and PHPUnit scenario validation tests, see **[scenario_test.md](scenario_test.md)**.

> 📖 **Scenarios & Custom JSON Creation Guide:**
> For details on the 4 preloaded parent scenarios and a step-by-step guide for creating custom JSON scenarios, see **[scenario_creation_guide.md](scenario_creation_guide.md)**.

> 💬 **LAFF Don't Cry Framework & Validation Guide:**
> For a detailed explanation of the LAFF Don't Cry communication strategy, validation checks, and state routing graph, see **[laff_framework_guide.md](laff_framework_guide.md)**.

> 🛠️ **Custom Scenario Builder Tool:**
> Teachers and instructors can use the web-based **[Scenario Builder Form](scenario_builder.html)** (or access via Moodle at `/local/aacuracore/scenario_builder.php`) to visually construct custom scenarios and download compliant `.json` files.

---

## 📥 Installation Guide

Follow these steps to install the plugin into your Moodle environment:

1. **Download** the latest ZIP archive of this repository ([llslim/moodle-plugin-aacuracore_engine](https://github.com/llslim/moodle-plugin-aacuracore_engine)).
2. **Navigate** to Moodle and proceed to:

   ```
   Site Administration → Plugins → Install Plugins → Install plugin from ZIP file
   ```
3. **Upload** the downloaded ZIP file and complete the installation.

The plugin will be installed at:

```
local/aacuracore
```

---

## 🔄 Automated Database Migration for Existing Sites

For sites upgrading from legacy `local_geniai`, run the included CLI migration utility:

```bash
php local/aacuracore/cli/migrate_geniai_to_aacura.php
```

---

## 🚀 Contributing & Deployment Workflow

To contribute to this project, follow the structured Git workflow:

```bash
git clone https://github.com/llslim/moodle-plugin-aacuracore_engine.git local/aacuracore
cd local/aacuracore
git checkout -b feature/your-feature-name
```
