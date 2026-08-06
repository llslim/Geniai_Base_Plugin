# Google Gemini API & Moodle AI Integration Guide

This document details the configuration and integration of **Google Gemini AI** in **AURA** (AAC Understanding & Reflective Assistant).

---

## 1. Overview of Gemini Integration

AURA supports Google Gemini models via two distinct integration paths:

1. **Direct REST API (`external_llm` / `local`)**:
   - **Endpoint**: `https://generativelanguage.googleapis.com/v1beta/openai/chat/completions`
   - **Recommended Model**: `gemini-3.5-flash`
   - **REST Compliance**: Payload parameters are strictly sanitized (omits zero-value `frequency_penalty` and `presence_penalty` parameters).

2. **Moodle Core AI Subsystem (`moodle_core_ai`)**:
   - **Class**: `\local_geniai\strategy\core_ai_provider_strategy`
   - **Provider Plugin**: `aiprovider_gemini`
   - **Configuration**: Managed centrally under **Site Administration > General > AI > AI Providers** (`/admin/settings.php?section=aisettings`).

---

## 2. Step-by-Step Setup Guide

### Option A: Direct Gemini REST Setup
1. Obtain an API Key from [Google AI Studio](https://aistudio.google.com/app/apikey).
2. Log into Moodle as an Administrator.
3. Navigate to **Site Administration > Plugins > Local plugins > AURA Settings** (`/admin/settings.php?section=local_geniaisetting`).
4. Set the following fields:
   - **Engine Strategy**: `external_llm`
   - **API Bearer Token**: Your Gemini API Key (`AIzaSy...`)
   - **Model Identifier**: `gemini-3.5-flash`
   - **API Base URL**: `https://generativelanguage.googleapis.com/v1beta/openai`
5. Click **Save changes**.

### Option B: Moodle Core AI Provider Setup
1. Navigate to **Site Administration > General > AI > AI Providers**.
2. Enable the **Google Gemini Provider** (`aiprovider_gemini`) and enter your API credentials.
3. In AURA Settings, set **Engine Strategy** to `moodle_core_ai`.

---

## 3. Supported Gemini Models

| Model Code | Description | Default Use Case |
| :--- | :--- | :--- |
| `gemini-3.5-flash` | Latest high-speed, high-accuracy conversational model. | **Default AURA Engine** |
| `gemini-3.5-flash-lite` | Ultra-lightweight model for constrained environments. | Fast Evaluation |
| `gemini-2.0-flash` | Previous generation Flash model. | Backup Model |
| `gemini-2.5-pro` | High-reasoning model. | Complex Rubrics |

---

## 4. Technical Architecture Reference

For details on the Strategy pattern implementation (`core_ai_provider_strategy.php`, `generative_ai_api_strategy.php`) and automated fallback routines, see **[ai_strategy.md](ai_strategy.md)**.
