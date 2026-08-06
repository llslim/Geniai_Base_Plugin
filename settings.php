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
 * @package   local_geniai
 * @copyright 2024 Eduardo Kraus {@link http://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    global $CFG, $DB, $PAGE, $ADMIN;

    $settings = new admin_settingpage("local_geniai", get_string("pluginname", "local_geniai"));

    $ADMIN->add("localplugins", $settings);

    // Link button to Scenario Builder & Site-Wide Registry
    $registryurl = new moodle_url('/local/geniai/scenario_builder.php');
    $registryhtml = 'Upload custom JSON scenarios, view registered personas, or remove personas site-wide. ' .
        '<a href="' . $registryurl->out() . '" target="_blank" class="btn btn-sm btn-primary ml-2" style="background-color: #4f2c11; border-color: #4f2c11; color: white;">' .
        '🛠️ Manage Personas & Open Scenario Builder' .
        '</a>';
    $settings->add(new admin_setting_heading('scenario_registry_heading', 'Custom Persona Scenario Registry', $registryhtml));

    $models = [
        "none" => get_string("mode_name_none", "local_geniai"),
        "assistant" => get_string("mode_name_assistant", "local_geniai"),
        "geniai" => get_string("mode_name_geniai", "local_geniai"),
    ];
    $setting = new admin_setting_configselect(
        "local_geniai/mode",
        get_string("mode", "local_geniai"),
        get_string("mode_desc", "local_geniai"),
        "none",
        $models
    );
    $settings->add($setting);

    // LLM Strategy and Endpoint settings for AAC-RERC Chatbot Upgrade.
    $strategies = [
        "moodle_core_ai" => get_string("engine_strategy_core_ai", "local_geniai"),
        "external_llm" => get_string("engine_strategy_external", "local_geniai"),
        "local" => get_string("engine_strategy_local", "local_geniai"),
    ];
    $setting = new admin_setting_configselect(
        "local_geniai/engine_strategy",
        get_string("engine_strategy", "local_geniai"),
        get_string("engine_strategy_desc", "local_geniai"),
        "moodle_core_ai",
        $strategies
    );
    $settings->add($setting);

    // Active Preloaded and Custom Persona Scenarios selection for AURA Chatbot
    $scenarios = [
        "anna" => "Anna Charles (Autism pre-K concern)",
        "brianna" => "Brianna Mitchell (Apraxia / social isolation)",
        "cathy" => "Cathy Fratner (Down Syndrome / app concern)",
        "mary" => "Mary (Mother of Non-Verbal 6-Year-Old)",
    ];

    // Append site-wide uploaded custom personas from database
    $customrecords = $DB->get_records("local_geniai_custom_scenarios", null, "name ASC");
    foreach ($customrecords as $cr) {
        $scenarios[$cr->scenariocode] = $cr->name . " (Custom: " . $cr->scenariocode . ")";
    }

    $setting = new admin_setting_configmultiselect(
        "local_geniai/active_scenarios",
        get_string("active_scenarios", "local_geniai"),
        get_string("active_scenarios_desc", "local_geniai"),
        array_keys($scenarios),
        $scenarios
    );
    $settings->add($setting);

    // Determine current active strategy solution
    $activestratey = get_config("local_geniai", "engine_strategy") ?: "moodle_core_ai";

    $coreai_badge = ($activestratey === 'moodle_core_ai') ? ' [✓ ACTIVELY IN USE]' : ' [INACTIVE]';
    $gemini_badge = ($activestratey === 'external_llm') ? ' [✓ ACTIVELY IN USE]' : ' [INACTIVE]';
    $chatgpt_badge = ($activestratey === 'local') ? ' [✓ ACTIVELY IN USE]' : ' [INACTIVE]';

    // 1. MOODLE CORE AI SUB-SYSTEM CONFIGURATION SECTION
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

    $collapsiblescript = '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var headings = document.querySelectorAll(".adminsettingflags, fieldset, h3");
        var sections = [
            { heading: "core_ai_section_heading", active: ' . ($activestratey === 'moodle_core_ai' ? 'true' : 'false') . ' },
            { heading: "gemini_section_heading", active: ' . ($activestratey === 'external_llm' ? 'true' : 'false') . ' },
            { heading: "chatgpt_section_heading", active: ' . ($activestratey === 'local' ? 'true' : 'false') . ' }
        ];

        sections.forEach(function(sec) {
            var h3 = document.querySelector("#admin-" + sec.heading + " h3, #admin-" + sec.heading + " legend");
            if (!h3) return;
            h3.style.cursor = "pointer";
            h3.style.userSelect = "none";
            h3.title = "Click to Expand / Collapse Section";
            
            var container = h3.closest(".form-item, fieldset, .setting-heading");
            if (!container) return;

            var sibling = container.nextElementSibling;
            var settingsGroup = [];
            while (sibling && !sibling.querySelector("h3") && !sibling.id.includes("_heading")) {
                settingsGroup.push(sibling);
                sibling = sibling.nextElementSibling;
            }

            var toggleState = sec.active;
            function updateVisibility() {
                settingsGroup.forEach(function(el) {
                    el.style.display = toggleState ? "" : "none";
                });
                h3.innerHTML = (toggleState ? "▼ " : "► ") + h3.innerHTML.replace(/^[▼►]\s*/, "");
            }
            updateVisibility();

            h3.addEventListener("click", function() {
                toggleState = !toggleState;
                updateVisibility();
            });
        });
    });
    </script>';

    $settings->add(new admin_setting_heading(
        'core_ai_section_heading',
        '🔌 1. ' . get_string("tab_core_ai", "local_geniai") . $coreai_badge,
        get_string("core_ai_providers_desc", "local_geniai") . $collapsiblescript
    ));

    $settings->add(new admin_setting_configselect(
        "local_geniai/core_ai_selected_provider",
        get_string("core_ai_selected_provider", "local_geniai"),
        get_string("core_ai_selected_provider_desc", "local_geniai"),
        "aiprovider_gemini",
        $coreaiprovideroptions
    ));

    // 2. GOOGLE GEMINI DIRECT REST CONFIGURATION SECTION
    $settings->add(new admin_setting_heading(
        'gemini_section_heading',
        '✨ 2. ' . get_string("tab_gemini", "local_geniai") . ' Direct REST API' . $gemini_badge,
        'Configure direct cURL API connection to Google Gemini REST endpoints (gemini-3.5-flash).'
    ));

    $setting = new admin_setting_configtext(
        "local_geniai/api_base_url",
        get_string("api_base_url", "local_geniai"),
        get_string("api_base_url_desc", "local_geniai"),
        "https://generativelanguage.googleapis.com/v1beta/openai",
        PARAM_RAW
    );
    $settings->add($setting);

    $setting = new admin_setting_configpasswordunmask(
        "local_geniai/api_bearer_token",
        get_string("api_bearer_token", "local_geniai"),
        get_string("api_bearer_token_desc", "local_geniai"),
        ""
    );
    $settings->add($setting);

    $setting = new admin_setting_configtext(
        "local_geniai/model_identifier",
        get_string("model_identifier", "local_geniai"),
        get_string("model_identifier_desc", "local_geniai"),
        "gemini-3.5-flash",
        PARAM_RAW
    );
    $settings->add($setting);

    // 3. CHATGPT (OPENAI) DIRECT CONFIGURATION SECTION
    $settings->add(new admin_setting_heading(
        'chatgpt_section_heading',
        '🤖 3. ' . get_string("tab_chatgpt", "local_geniai") . ' API' . $chatgpt_badge,
        'Configure direct API connection to OpenAI ChatGPT endpoints.'
    ));

    $apikey = get_config("local_geniai", "apikey");
    if (isset($apikey[12])) {
        $setting = new admin_setting_configpasswordunmask(
            "local_geniai/apikey",
            get_string("apikey", "local_geniai"),
            get_string("apikey_desc", "local_geniai"),
            ""
        );
        $settings->add($setting);
    } else {
        $setting = new admin_setting_configtext(
            "local_geniai/apikey",
            get_string("apikey", "local_geniai"),
            get_string("apikey_desc", "local_geniai"),
            ""
        );
        $settings->add($setting);
    }

    $models = [
        "gpt-4" => "gpt-4",
        "gpt-4o-mini" => "gpt-4o-mini",
        "gpt-4-32k" => "gpt-4-32k",
        "gpt-4-turbo" => "gpt-4-turbo",
    ];
    $setting = new admin_setting_configselect(
        "local_geniai/model",
        get_string("model", "local_geniai"),
        get_string("model_desc", "local_geniai"),
        "gpt-4o-mini",
        $models
    );
    $settings->add($setting);

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
    $setting = new admin_setting_configselect(
        "local_geniai/voice",
        get_string("voice", "local_geniai"),
        $voicedesc,
        "alloy",
        $voices
    );
    $settings->add($setting);

    $cases = [
        "chatbot" => get_string("caseuse_chatbot", "local_geniai"),
        "creative" => get_string("caseuse_creative", "local_geniai"),
        "balanced" => get_string("caseuse_balanced", "local_geniai"),
        "precise" => get_string("caseuse_precise", "local_geniai"),
        "exploration" => get_string("caseuse_exploration", "local_geniai"),
        "formal" => get_string("caseuse_formal", "local_geniai"),
        "informal" => get_string("caseuse_informal", "local_geniai"),
    ];
    $casedesc = $OUTPUT->render_from_template("local_geniai/settings_casedesc", []);
    $settings->add(new admin_setting_configselect(
        "local_geniai/case",
        get_string("case", "local_geniai"),
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
        "local_geniai/modules",
        get_string("modules", "local_geniai", $geniainame),
        get_string("modules_desc", "local_geniai", $geniainame),
        ["glossary", "lesson", "forum", "scorm", "feedback", "survey", "quiz", "assign", "wiki", "lti", "workshop"],
        $modules
    ));

    $setting = new admin_setting_configtext(
        "local_geniai/max_tokens",
        get_string("max_tokens", "local_geniai"),
        get_string("max_tokens_desc", "local_geniai"),
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
        "local_geniai/frequency_penalty",
        get_string("frequency_penalty", "local_geniai"),
        get_string("frequency_penalty_desc", "local_geniai"),
        "0.0",
        $penalty
    );
    $settings->add($setting);

    $setting = new admin_setting_configselect(
        "local_geniai/presence_penalty",
        get_string("presence_penalty", "local_geniai"),
        get_string("presence_penalty_desc", "local_geniai"),
        "0.0",
        $penalty
    );
    $settings->add($setting);
}
