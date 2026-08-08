<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings file.
 *
 * @package   local_aacura_core
 * @copyright 2024 Eduardo Kraus {@link http://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    global $CFG, $DB, $PAGE, $ADMIN;

    // Single admin settings page under localplugins
    $settings = new admin_settingpage("local_aacura_core", get_string("pluginname", "local_aacura_core"));
    $ADMIN->add("localplugins", $settings);

    // Link button to Scenario Builder & Site-Wide Registry
    $registryurl = new moodle_url('/local/aacura_core/scenario_builder.php');
    $registryhtml = 'Upload custom JSON scenarios, view registered personas, or remove personas site-wide. ' .
        '<a href="' . $registryurl->out() . '" target="_blank" class="btn btn-sm btn-primary ml-2" style="background-color: #4f2c11; border-color: #4f2c11; color: white;">' .
        '🛠️ Manage Personas & Open Scenario Builder' .
        '</a>';
    $settings->add(new admin_setting_heading('scenario_registry_heading', 'Custom Persona Scenario Registry', $registryhtml));

    $models = [
        "none" => get_string("mode_name_none", "local_aacura_core"),
        "assistant" => get_string("mode_name_assistant", "local_aacura_core"),
        "geniai" => get_string("mode_name_geniai", "local_aacura_core"),
    ];
    $settings->add(new admin_setting_configselect(
        "local_aacura_core/mode",
        get_string("mode", "local_aacura_core"),
        get_string("mode_desc", "local_aacura_core"),
        "none",
        $models
    ));

    $strategies = [
        "moodle_core_ai" => get_string("engine_strategy_core_ai", "local_aacura_core"),
        "external_llm" => get_string("engine_strategy_external", "local_aacura_core"),
        "local" => get_string("engine_strategy_local", "local_aacura_core"),
    ];
    $settings->add(new admin_setting_configselect(
        "local_aacura_core/engine_strategy",
        get_string("engine_strategy", "local_aacura_core"),
        get_string("engine_strategy_desc", "local_aacura_core"),
        "moodle_core_ai",
        $strategies
    ));

    $scenarios = [
        "anna" => "Anna Charles (Autism pre-K concern)",
        "brianna" => "Brianna Mitchell (Apraxia / social isolation)",
        "cathy" => "Cathy Fratner (Down Syndrome / app concern)",
        "mary" => "Mary (Mother of Non-Verbal 6-Year-Old)",
    ];
    $customrecords = $DB->get_records("local_aacura_core_custom_scenarios", null, "name ASC");
    foreach ($customrecords as $cr) {
        $scenarios[$cr->scenariocode] = $cr->name . " (Custom: " . $cr->scenariocode . ")";
    }
    $settings->add(new admin_setting_configmultiselect(
        "local_aacura_core/active_scenarios",
        get_string("active_scenarios", "local_aacura_core"),
        get_string("active_scenarios_desc", "local_aacura_core"),
        array_keys($scenarios),
        $scenarios
    ));

    // Determine current active strategy solution
    $activestratey = get_config("local_aacura_core", "engine_strategy") ?: "moodle_core_ai";

    $coreai_badge = ($activestratey === 'moodle_core_ai') ? ' <span style="background-color: #198754; color: #ffffff; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 8px;">[✓ ACTIVELY IN USE]</span>' : ' <span style="background-color: #6c757d; color: #ffffff; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 8px;">[INACTIVE]</span>';
    $gemini_badge = ($activestratey === 'external_llm') ? ' <span style="background-color: #198754; color: #ffffff; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 8px;">[✓ ACTIVELY IN USE]</span>' : ' <span style="background-color: #6c757d; color: #ffffff; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 8px;">[INACTIVE]</span>';
    $chatgpt_badge = ($activestratey === 'local') ? ' <span style="background-color: #198754; color: #ffffff; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 8px;">[✓ ACTIVELY IN USE]</span>' : ' <span style="background-color: #6c757d; color: #ffffff; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 8px;">[INACTIVE]</span>';

    // Core AI Provider records dropdown options
    $coreaiprovideroptions = [];
    if (class_exists('\\core_ai\\manager')) {
        try {
            $manager = new \core_ai\manager();
            $providers = $manager->get_provider_records();
            foreach ($providers as $p) {
                $status = ($p->enabled) ? 'Enabled' : 'Disabled';
                $coreaiprovideroptions[$p->provider] = "{$p->name} ({$p->provider}) - [{$status}]";
            }
        } catch (\Throwable $e) {
            // Core AI manager exception fallback
        }
    }
    if (empty($coreaiprovideroptions)) {
        $coreaiprovideroptions['aiprovider_gemini'] = 'Google Gemini Provider (aiprovider_gemini)';
        $coreaiprovideroptions['aiprovider_openai'] = 'OpenAI Provider (aiprovider_openai)';
    }

    // Combine interactive accordion card headers with precise s_local_aacura_core_* field prefix controls
    $visibilityscript = '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var activeStrat = ' . json_encode($activestratey) . ';

        var sections = [
            {
                strategyVal: "moodle_core_ai",
                matchText: "1. Moodle Core AI",
                fields: ["s_local_aacura_core_core_ai_selected_provider"]
            },
            {
                strategyVal: "external_llm",
                matchText: "2. Google Gemini",
                fields: ["s_local_aacura_core_api_base_url", "s_local_aacura_core_api_bearer_token", "s_local_aacura_core_model_identifier"]
            },
            {
                strategyVal: "local",
                matchText: "3. ChatGPT",
                fields: ["s_local_aacura_core_apikey", "s_local_aacura_core_model", "s_local_aacura_core_voice", "s_local_aacura_core_case"]
            }
        ];

        sections.forEach(function(sec) {
            // Find section header by heading title text
            var titleEl = null;
            var allHeadings = document.querySelectorAll("h3, legend, .form-header");
            for (var i = 0; i < allHeadings.length; i++) {
                if (allHeadings[i].textContent && allHeadings[i].textContent.indexOf(sec.matchText) !== -1) {
                    titleEl = allHeadings[i];
                    break;
                }
            }
            if (!titleEl) return;

            titleEl.style.cursor = "pointer";
            titleEl.style.userSelect = "none";
            titleEl.style.display = "flex";
            titleEl.style.justifyContent = "space-between";
            titleEl.style.alignItems = "center";
            titleEl.style.padding = "10px 14px";
            titleEl.style.backgroundColor = "#e9ecef";
            titleEl.style.border = "1px solid #ced4da";
            titleEl.style.borderRadius = "6px";
            titleEl.style.marginTop = "20px";

            var isOpen = (sec.strategyVal === activeStrat);

            var toggleSpan = document.createElement("span");
            toggleSpan.className = "aacura-toggle-badge";
            toggleSpan.style.fontWeight = "bold";
            toggleSpan.style.fontSize = "13px";
            toggleSpan.style.padding = "3px 10px";
            toggleSpan.style.borderRadius = "4px";

            titleEl.appendChild(toggleSpan);

            function setVisibility(show) {
                isOpen = show;
                toggleSpan.textContent = isOpen ? "▼ Collapse" : "► Expand";
                toggleSpan.style.backgroundColor = isOpen ? "#cbd5e1" : "#ffffff";
                toggleSpan.style.border = "1px solid #94a3b8";

                sec.fields.forEach(function(fieldName) {
                    var inputEl = document.querySelector("[name=\'" + fieldName + "\']");
                    if (inputEl) {
                        var container = inputEl.closest(".form-item, .setting-item, div.row, fieldset");
                        if (container) {
                            container.style.display = isOpen ? "" : "none";
                        }
                    }
                });
            }

            setVisibility(isOpen);

            titleEl.addEventListener("click", function(e) {
                e.preventDefault();
                setVisibility(!isOpen);
            });
        });

        // Dynamic change listener on strategy select dropdown
        var strategySelect = document.querySelector("select[name=\'s_local_aacura_core_engine_strategy\']") || document.querySelector("select[name*=\'engine_strategy\']");
        if (strategySelect) {
            strategySelect.addEventListener("change", function() {
                var selectedVal = strategySelect.value;
                sections.forEach(function(sec) {
                    var titleEl = null;
                    var allHeadings = document.querySelectorAll("h3, legend, .form-header");
                    for (var i = 0; i < allHeadings.length; i++) {
                        if (allHeadings[i].textContent && allHeadings[i].textContent.indexOf(sec.matchText) !== -1) {
                            titleEl = allHeadings[i];
                            break;
                        }
                    }
                    if (!titleEl) return;

                    var toggleSpan = titleEl.querySelector(".aacura-toggle-badge");
                    var shouldShow = (sec.strategyVal === selectedVal);

                    if (toggleSpan) {
                        toggleSpan.textContent = shouldShow ? "▼ Collapse" : "► Expand";
                        toggleSpan.style.backgroundColor = shouldShow ? "#cbd5e1" : "#ffffff";
                    }

                    sec.fields.forEach(function(fieldName) {
                        var inputEl = document.querySelector("[name=\'" + fieldName + "\']");
                        if (inputEl) {
                            var container = inputEl.closest(".form-item, .setting-item, div.row, fieldset");
                            if (container) {
                                container.style.display = shouldShow ? "" : "none";
                            }
                        }
                    });
                });
            });
        }
    });
    </script>';

    // 1. MOODLE CORE AI SUB-SYSTEM CONFIGURATION SECTION
    $settings->add(new admin_setting_heading(
        'core_ai_section_heading',
        '🔌 1. Moodle Core AI Framework' . $coreai_badge,
        'Site-wide provider manager integration (\core_ai\manager).' . $visibilityscript
    ));

    $settings->add(new admin_setting_configselect(
        "local_aacura_core/core_ai_selected_provider",
        get_string("core_ai_selected_provider", "local_aacura_core"),
        get_string("core_ai_selected_provider_desc", "local_aacura_core"),
        "aiprovider_gemini",
        $coreaiprovideroptions
    ));

    // 2. GOOGLE GEMINI DIRECT REST CONFIGURATION SECTION
    $settings->add(new admin_setting_heading(
        'gemini_section_heading',
        '✨ 2. Google Gemini Direct REST API' . $gemini_badge,
        'Direct cURL REST API connection to Google Gemini models (gemini-3.5-flash).'
    ));

    $settings->add(new admin_setting_configtext(
        "local_aacura_core/api_base_url",
        get_string("api_base_url", "local_aacura_core"),
        get_string("api_base_url_desc", "local_aacura_core"),
        "https://generativelanguage.googleapis.com/v1beta/openai",
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        "local_aacura_core/api_bearer_token",
        get_string("api_bearer_token", "local_aacura_core"),
        get_string("api_bearer_token_desc", "local_aacura_core"),
        ""
    ));

    $settings->add(new admin_setting_configtext(
        "local_aacura_core/model_identifier",
        get_string("model_identifier", "local_aacura_core"),
        get_string("model_identifier_desc", "local_aacura_core"),
        "gemini-3.5-flash",
        PARAM_RAW
    ));

    // 3. CHATGPT (OPENAI) DIRECT API CONFIGURATION SECTION
    $settings->add(new admin_setting_heading(
        'chatgpt_section_heading',
        '🤖 3. ChatGPT (OpenAI Direct API)' . $chatgpt_badge,
        'Direct API connection to OpenAI ChatGPT endpoints.'
    ));

    $apikey = get_config("local_aacura_core", "apikey");
    if (isset($apikey[12])) {
        $settings->add(new admin_setting_configpasswordunmask(
            "local_aacura_core/apikey",
            get_string("apikey", "local_aacura_core"),
            get_string("apikey_desc", "local_aacura_core"),
            ""
        ));
    } else {
        $settings->add(new admin_setting_configtext(
            "local_aacura_core/apikey",
            get_string("apikey", "local_aacura_core"),
            get_string("apikey_desc", "local_aacura_core"),
            ""
        ));
    }

    $models = [
        "gpt-4" => "gpt-4",
        "gpt-4o-mini" => "gpt-4o-mini",
        "gpt-4-32k" => "gpt-4-32k",
        "gpt-4-turbo" => "gpt-4-turbo",
    ];
    $settings->add(new admin_setting_configselect(
        "local_aacura_core/model",
        get_string("model", "local_aacura_core"),
        get_string("model_desc", "local_aacura_core"),
        "gpt-4o-mini",
        $models
    ));

    $voices = [
        "alloy" => "Alloy",
        "echo" => "Echo",
        "fable" => "Fable",
        "onyx" => "Onyx",
        "nova" => "Nova",
        "shimmer" => "Shimmer",
    ];
    $voicedesc = preg_replace('/\s+</s', "<", '
            <table>
                <tr>
                    <th style="text-align: right;">Alloy:</th>
                    <td><audio src="https://cdn.openai.com/API/docs/audio/alloy.wav" controls></audio></td>
                </tr>
                <tr>
                    <th style="text-align: right;">Echo:</th>
                    <td><audio src="https://cdn.openai.com/API/docs/audio/echo.wav" controls></audio></td>
                </tr>
                <tr>
                    <th style="text-align: right;">Fable:</th>
                    <td><audio src="https://cdn.openai.com/API/docs/audio/fable.wav" controls></audio></td>
                </tr>
                <tr>
                    <th style="text-align: right;">Onyx:</th>
                    <td><audio src="https://cdn.openai.com/API/docs/audio/onyx.wav" controls></audio></td>
                </tr>
                <tr>
                    <th style="text-align: right;">Nova:</th>
                    <td><audio src="https://cdn.openai.com/API/docs/audio/nova.wav" controls></audio></td>
                </tr>
                <tr>
                    <th style="text-align: right;">Shimmer:</th>
                    <td><audio src="https://cdn.openai.com/API/docs/audio/shimmer.wav" controls></audio></td>
                </tr>
            </table>');
    $settings->add(new admin_setting_configselect(
        "local_aacura_core/voice",
        get_string("voice", "local_aacura_core"),
        $voicedesc,
        "alloy",
        $voices
    ));

    $cases = [
        "chatbot" => get_string("caseuse_chatbot", "local_aacura_core"),
        "creative" => get_string("caseuse_creative", "local_aacura_core"),
        "balanced" => get_string("caseuse_balanced", "local_aacura_core"),
        "precise" => get_string("caseuse_precise", "local_aacura_core"),
        "exploration" => get_string("caseuse_exploration", "local_aacura_core"),
        "formal" => get_string("caseuse_formal", "local_aacura_core"),
        "informal" => get_string("caseuse_informal", "local_aacura_core"),
    ];
    $casedesc = $OUTPUT->render_from_template("local_aacura_core/settings_casedesc", []);
    $settings->add(new admin_setting_configselect(
        "local_aacura_core/case",
        get_string("case", "local_aacura_core"),
        $casedesc,
        "chatbot",
        $cases
    ));

    $modules = [];
    $records = $DB->get_records("modules", ["visible" => 1], "name", "name");
    foreach ($records as $record) {
        if (file_exists("{$CFG->dirroot}/mod/{$record->name}/lib.php")) {
            if (!(plugin_supports("mod", $record->name, FEATURE_MOD_ARCHETYPE) === MOD_ARCHETYPE_SYSTEM)) {
                $modules[$record->name] = get_string("pluginname", $record->name);
            }
        }
    }
    $settings->add(new admin_setting_configmultiselect(
        "local_aacura_core/modules",
        get_string("modules", "local_aacura_core", $geniainame),
        get_string("modules_desc", "local_aacura_core", $geniainame),
        ["glossary", "lesson", "forum", "scorm", "feedback", "survey", "quiz", "assign", "wiki", "lti", "workshop"],
        $modules
    ));

    $setting = new admin_setting_configtext(
        "local_aacura_core/max_tokens",
        get_string("max_tokens", "local_aacura_core"),
        get_string("max_tokens_desc", "local_aacura_core"),
        200,
        PARAM_INT
    );
    $settings->add($setting);

    $penalty = [
        "-2.0" => "-2.0",
        "-1.9" => "-1.9",
        "-1.8" => "-1.8",
        "-1.7" => "-1.7",
        "-1.6" => "-1.6",
        "-1.5" => "-1.5",
        "-1.4" => "-1.4",
        "-1.3" => "-1.3",
        "-1.2" => "-1.2",
        "-1.1" => "-1.1",
        "-1.0" => "-1.0",
        "-0.9" => "-0.9",
        "-0.8" => "-0.8",
        "-0.7" => "-0.7",
        "-0.6" => "-0.6",
        "-0.5" => "-0.5",
        "-0.4" => "-0.4",
        "-0.3" => "-0.3",
        "-0.2" => "-0.2",
        "-0.1" => "-0.1",
        "0.0" => "0.0",
        "0.1" => "0.1",
        "0.2" => "0.2",
        "0.3" => "0.3",
        "0.4" => "0.4",
        "0.5" => "0.5",
        "0.6" => "0.6",
        "0.7" => "0.7",
        "0.8" => "0.8",
        "0.9" => "0.9",
        "1.0" => "1.0",
        "1.1" => "1.1",
        "1.2" => "1.2",
        "1.3" => "1.3",
        "1.4" => "1.4",
        "1.5" => "1.5",
        "1.6" => "1.6",
        "1.7" => "1.7",
        "1.8" => "1.8",
        "1.9" => "1.9",
        "2.0" => "2.0",
    ];
    $setting = new admin_setting_configselect(
        "local_aacura_core/frequency_penalty",
        get_string("frequency_penalty", "local_aacura_core"),
        get_string("frequency_penalty_desc", "local_aacura_core"),
        "0.0",
        $penalty
    );
    $settings->add($setting);

    $setting = new admin_setting_configselect(
        "local_aacura_core/presence_penalty",
        get_string("presence_penalty", "local_aacura_core"),
        get_string("presence_penalty_desc", "local_aacura_core"),
        "0.0",
        $penalty
    );
    $settings->add($setting);
}
