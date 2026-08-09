# Google Gemini API & Moodle AI Integration Guide

This document details the configuration and integration of **Google Gemini AI** in **AACURA** (AAC Understanding & Reflective Assistant).

---

## 1. Overview of Gemini Integration

AACURA supports Google Gemini models via two distinct integration paths:

1. **Direct REST API (`external_llm` / `local`)**:
   - **Endpoint**: `https://generativelanguage.googleapis.com/v1beta/openai/chat/completions`
   - **Recommended Model**: `gemini-3.5-flash`
   - **REST Compliance**: Payload parameters are strictly sanitized (omits zero-value `frequency_penalty` and `presence_penalty` parameters).

2. **Moodle Core AI Subsystem (`moodle_core_ai`)**:
   - **Class**: `\local_aacuracore\strategy\core_ai_provider_strategy`
   - **Provider Plugin**: `aiprovider_gemini`
   - **Configuration**: Managed centrally under **Site Administration > General > AI > AI Providers** (`/admin/settings.php?section=aisettings`).

---

## 2. Step-by-Step Setup Guide

### Option A: Direct Gemini REST Setup
1. Obtain an API Key from [Google AI Studio](https://aistudio.google.com/app/apikey).
2. Log into Moodle as an Administrator.
3. Navigate to **Site Administration > Plugins > Local plugins > AACURA Settings** (`/admin/settings.php?section=local_aacuracoresetting`).
4. Set the following fields:
   - **Engine Strategy**: `external_llm`
   - **API Bearer Token**: Your Gemini API Key (`AIzaSy...`)
   - **Model Identifier**: `gemini-3.5-flash`
   - **API Base URL**: `https://generativelanguage.googleapis.com/v1beta/openai`
5. Click **Save changes**.

### Option B: Moodle Core AI Provider Setup
1. Navigate to **Site Administration > General > AI > AI Providers**.
2. Enable the **Google Gemini Provider** (`aiprovider_gemini`) and enter your API credentials.
3. In AACURA Settings, set **Engine Strategy** to `moodle_core_ai`.

---

## 3. Supported Gemini Models

| Model Code | Description | Default Use Case |
| :--- | :--- | :--- |
| `gemini-3.5-flash` | Latest high-speed, high-accuracy conversational model. | **Default AACURA Engine** |
| `gemini-3.5-flash-lite` | Ultra-lightweight model for constrained environments. | Fast Evaluation |
| `gemini-2.0-flash` | Previous generation Flash model. | Backup Model |
| `gemini-2.5-pro` | High-reasoning model. | Complex Rubrics |

---

## 4. Technical Architecture Reference

For details on the Strategy pattern implementation (`core_ai_provider_strategy.php`, `generative_ai_api_strategy.php`) and automated fallback routines, see **[ai_strategy.md](ai_strategy.md)**.

## 5. Development & Deployment Workflow Guidelines

To maintain environment stability and version control integrity, all code updates follow a standardized GitHub Actions deployment workflow:

1. **Local Repository Edits**: Code modifications are made strictly within the local workspace repository (`local/aacuracore/` or `mod/aacura_chat/`).
2. **Git Commit & Push**: Changes are committed and pushed to the primary Git branch (`origin/moodle-5.2`).
3. **Automated GitHub Actions CI/CD Pipeline**: GitHub Actions (`deploy.yml`) automatically triggers on push, checks out submodules, deploys code via rsync, runs database upgrades, executes integration tests (`run_tests.php`), increments asset revisions (`jsrev`/`themerev`), and purges site caches.
4. **SSH Verification**: Verify GitHub Actions workflow run completion and deployment status via SSH or gh CLI instead of manual git pulls.

---

## 6. Automated Scenario & Integration Testing

For details on how to debug Gemini API configurations, toggle server developer debugging, run automated graph verification crawls, and execute PHPUnit unit tests, see the **[Scenario Testing & Diagnostics Guide](scenario_test.md)**.
