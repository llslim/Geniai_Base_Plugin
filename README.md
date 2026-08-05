# Geniai Base Plugin (Backend)

This repository contains the **core backend logic** for the AAC Moodle Chatbot. All backend processing and integrations must be managed here.

> ℹ️ **Provenance & Ancestry Note:**
> This repository is a customized fork derived from a previous version of [EduardoKrausME/moodle-local_geniai](https://github.com/EduardoKrausME/moodle-local_geniai).
> 
> **Important:** This plugin operates as a fully standalone core engine at `local/geniai`. There is **no runtime dependency** on the original `moodle-local_geniai` repository; installing `EduardoKrausME/moodle-local_geniai` alongside this plugin is neither required nor recommended.

> 🧪 **Integration Test Suite Guide:**
> For a detailed explanation of each automated PHP integration test case and pre-deployment verification steps, see **[test_suite_guide.md](test_suite_guide.md)**.

> 📖 **Scenarios & Custom JSON Creation Guide:**
> For details on the 4 preloaded parent scenarios and a step-by-step guide for creating custom JSON scenarios, see **[scenario_creation_guide.md](scenario_creation_guide.md)**.

> 💬 **LAFF Don't Cry Framework & Validation Guide:**
> For a detailed explanation of the LAFF Don't Cry communication strategy, validation checks, and state routing graph, see **[laff_framework_guide.md](laff_framework_guide.md)**.

> 🔍 **Technical Validation Checks Execution Guide:**
> For a detailed technical breakdown of how validation checks are evaluated via Generative AI (LLM) and Pattern Matcher (Regex) strategies, see **[validation_checks_execution_guide.md](validation_checks_execution_guide.md)**.

> 🛠️ **Custom Scenario Builder Tool:**
> Teachers and instructors can use the web-based **[Scenario Builder Form](scenario_builder.html)** (or access via Moodle at `/local/geniai/scenario_builder.php`) to visually construct custom scenarios and download compliant `.json` files.

---

## 📥 Installation Guide

Follow these steps to install the plugin into your Moodle environment:

1. **Download** the latest ZIP archive of this repository.
2. **Navigate** to Moodle and proceed to:

   ```
   Site Administration → Plugins → Install Plugins → Install plugin from ZIP file
   ```
3. **Upload** the downloaded ZIP file and complete the installation.

The plugin will be installed at:

```
local/geniai
```

---

## 🔄 Frontend Synchronization

Ensure synchronization between backend updates and frontend UI changes:

* After updating backend logic, ensure any relevant API changes or adjustments are reflected in the frontend chatbot activity plugin.
* **Reminder:** Do not push frontend UI/activity code to this backend repository.

---

## 🚀 Contributing Workflow

To contribute to this project, follow the structured Git workflow:

### 1. Clone Repository

Clone via SSH:

```bash
git clone git@github.com:DrKat0m/Geniai_Base_Plugin.git
cd Geniai_Base_Plugin
```

### 2. Create Feature Branch

Create and switch to your branch:

```bash
git checkout -b your-feature-branch
```

### 3. Commit and Push Changes

Make your changes, then stage, commit, and push:

```bash
git add .
git commit -m "Descriptive commit message"
git push -u origin your-feature-branch
```

### 4. Submit Pull Request

Open a Pull Request on GitHub for review and merging.

---

## 🐞 Debugging with Xdebug

For setting up Xdebug with PHP, refer to Kartavya’s comprehensive guide on Discord:

🔗 [Xdebug Setup Guide](https://gist.github.com/DrKat0m#-debugging-with-xdebug)

---

## ✨ Fresh Installation & Setup

Follow these instructions for a clean setup:

1. **Ensure Git installation:** [Download Git](https://git-scm.com/downloads)

2. **Navigate to Moodle's local plugins directory:**

```bash
cd D:\xampp\htdocs\moodle\local
```

3. **Clone the repository:**

```bash
git clone git@github.com:DrKat0m/Geniai_Base_Plugin.git
```

4. **Create a working branch:**

```bash
cd Geniai_Base_Plugin
git checkout -b your-feature-branch
```

---

## ✅ Important Guidelines

* Commit **only backend changes** to this repository.
* Update dependent frontend changes in the [moodle-chatbot](https://github.com/DrKat0m/EDURA) repository promptly.
* Do not push chatbot activity module or block code to this repository to prevent codebase clutter and conflicts.
