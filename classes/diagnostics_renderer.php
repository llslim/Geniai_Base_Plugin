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

namespace local_aacuracore;

defined('MOODLE_INTERNAL') || die;

/**
 * Renders the Diagnostics tab content for the local_aacuracore settings page.
 *
 * Produces a client-side tabbed layout (Configuration | Diagnostics) and the
 * diagnostics panel showing: plugin configs, the connected AI provider, and
 * visual scenario state-machine graphs.
 *
 * @package   local_aacuracore
 * @copyright 2026 AAC-RERC Chatbot Team
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnostics_renderer {

    /**
     * Renders the tab bar, the diagnostics panel, and the tab-switching script.
     *
     * @return string
     */
    public static function render_tabbar_and_panel(): string {
        global $DB;

        $configs = self::render_configs();
        $provider = self::render_provider();
        $promptpreview = self::render_prompt_preview();
        $evaluationpreview = self::render_evaluation_preview();
        $graphs = self::render_scenario_graphs();

        $html = '
        <style>
            .aacura-tabbar { display: flex; gap: 6px; border-bottom: 2px solid #dee2e6; margin-bottom: 18px; padding-bottom: 0; }
            .aacura-tabbar .aacura-tab {
                border: 1px solid #ced4da; border-bottom: none; background: #f8f9fa; color: #495057;
                padding: 9px 18px; font-size: 14px; font-weight: 600; cursor: pointer; border-radius: 6px 6px 0 0;
            }
            .aacura-tabbar .aacura-tab.active { background: #4f2c11; color: #ffffff; border-color: #4f2c11; }
            .aacura-tab-panel { padding: 4px 2px; }
            #aacura-panel-prompts textarea.form-control {
                height: auto; min-height: 90px; resize: vertical; overflow-y: auto; transition: max-height 0.25s ease;
            }
            #aacura-panel-prompts .aacura-textarea-toggle {
                display: inline-block; margin: 4px 0 0 0; padding: 2px 10px; font-size: 12px; font-weight: 600;
                border: 1px solid #ced4da; border-radius: 4px; background: #f8f9fa; color: #495057; cursor: pointer;
            }
            #aacura-panel-prompts .aacura-textarea-toggle:hover { background: #e9ecef; }
            .aacura-diag-card {
                border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 18px; overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            }
            .aacura-diag-card > .aacura-diag-head {
                background: #4f2c11; color: #ffffff; padding: 10px 16px; font-size: 15px; font-weight: 700;
            }
            .aacura-diag-card > .aacura-diag-body { padding: 14px 16px; background: #ffffff; }
            .aacura-diag-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .aacura-diag-table th, .aacura-diag-table td { border: 1px solid #e9ecef; padding: 6px 10px; text-align: left; }
            .aacura-diag-table th { background: #f1f3f5; font-weight: 600; }
            .aacura-diag-table code { background: #f1f3f5; padding: 1px 5px; border-radius: 4px; }
            .aacura-provider {
                border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; background: #f8f9fa;
            }
            .aacura-provider.aacura-provider-active { border: 2px solid #198754; background: #f0faf4; }
            .aacura-provider .aacura-provider-name { font-weight: 700; font-size: 14px; }
            .aacura-badge {
                display: inline-block; padding: 2px 9px; border-radius: 12px; font-size: 11px; font-weight: 700;
                color: #fff; margin-left: 8px; vertical-align: middle;
            }
            .aacura-badge.aacura-badge-active { background: #198754; }
            .aacura-badge.aacura-badge-inactive { background: #6c757d; }
            .aacura-badge.aacura-badge-selected { background: #0d6efd; }
            .aacura-provider ul { margin: 8px 0 0 0; padding-left: 20px; }
            .aacura-provider li { font-size: 13px; margin-bottom: 2px; }
            .aacura-graph-wrap { overflow-x: auto; }
            .aacura-graph-wrap svg { max-width: 100%; height: auto; }
            .aacura-scenario-title { font-weight: 700; font-size: 14px; margin: 4px 0 8px 0; }
            .aacura-muted { color: #6c757d; font-size: 12px; }
        </style>

        <div id="aacura-tabbar" class="aacura-tabbar">
            <button type="button" class="aacura-tab active" data-tab="config">⚙️ Configuration</button>
            <button type="button" class="aacura-tab" data-tab="prompts">📝 Prompt Templates</button>
            <button type="button" class="aacura-tab" data-tab="diag">🩺 Diagnostics</button>
        </div>

        <div id="aacura-panel-diag" class="aacura-tab-panel" style="display:none;">
            ' . $provider . '
            ' . $configs . '
            ' . $promptpreview . '
            ' . $evaluationpreview . '
            ' . $graphs . '
        </div>

        <script>
        (function() {
            function initAacuraTabs() {
                var form = document.querySelector("form.settingsform") || document.querySelector(".settingsform");
                if (!form) return;
                var tabbar = document.getElementById("aacura-tabbar");
                var diagPanel = document.getElementById("aacura-panel-diag");
                if (!tabbar || !diagPanel) return;

                // Detach tabbar + diagnostics panel from their heading container.
                if (tabbar.parentNode) { tabbar.parentNode.removeChild(tabbar); }
                if (diagPanel.parentNode) { diagPanel.parentNode.removeChild(diagPanel); }

                // Wrap the remaining settings form content in the Configuration panel.
                var configPanel = document.createElement("div");
                configPanel.className = "aacura-tab-panel";
                configPanel.id = "aacura-panel-config";
                while (form.firstChild) { configPanel.appendChild(form.firstChild); }

                // Create the Prompt Templates panel and move the two prompt
                // template settings (persona + evaluation) into it. The items
                // are inside configPanel (not yet in the document), so query
                // them from configPanel directly.
                var promptsPanel = document.createElement("div");
                promptsPanel.className = "aacura-tab-panel";
                promptsPanel.id = "aacura-panel-prompts";
                promptsPanel.style.display = "none";

                var promptItems = [
                    configPanel.querySelector("#admin-prompt_template"),
                    configPanel.querySelector("#admin-evaluation_prompt_template"),
                    configPanel.querySelector("#admin-ai_builder_prompt_template")
                ];
                promptItems.forEach(function(item) {
                    if (item && item.parentNode) {
                        item.parentNode.removeChild(item);
                        promptsPanel.appendChild(item);
                    }
                });

                // Add an Expand/Hide toggle button to each prompt textarea so
                // admins can expand the full prompt when needed and collapse
                // it back to avoid long page scroll.
                var COLLAPSED = 260; // px
                promptsPanel.querySelectorAll("textarea").forEach(function(ta) {
                    var wrapper = ta.closest(".form-item") || ta.parentElement;
                    var btn = document.createElement("button");
                    btn.type = "button";
                    btn.className = "aacura-textarea-toggle";
                    var expanded = false;
                    function refresh() {
                        if (expanded) {
                            ta.style.maxHeight = "";
                            btn.textContent = "▲ Hide";
                        } else {
                            ta.style.maxHeight = COLLAPSED + "px";
                            btn.textContent = "▼ Expand";
                        }
                    }
                    btn.addEventListener("click", function() {
                        expanded = !expanded;
                        refresh();
                    });
                    wrapper.appendChild(btn);
                    refresh();
                });

                form.appendChild(tabbar);
                form.appendChild(configPanel);
                form.appendChild(promptsPanel);
                form.appendChild(diagPanel);

                var tabs = tabbar.querySelectorAll(".aacura-tab");
                function showTab(name) {
                    var cfg = document.getElementById("aacura-panel-config");
                    var prompts = document.getElementById("aacura-panel-prompts");
                    var diag = document.getElementById("aacura-panel-diag");
                    if (cfg) { cfg.style.display = (name === "config") ? "" : "none"; }
                    if (prompts) { prompts.style.display = (name === "prompts") ? "" : "none"; }
                    if (diag) { diag.style.display = (name === "diag") ? "" : "none"; }
                    tabs.forEach(function(b) { b.classList.toggle("active", b.getAttribute("data-tab") === name); });
                }
                tabs.forEach(function(btn) {
                    btn.addEventListener("click", function() { showTab(btn.getAttribute("data-tab")); });
                });
                showTab("config");
            }
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", initAacuraTabs);
            } else {
                initAacuraTabs();
            }
        })();
        </script>';

        return $html;
    }

    /**
     * Renders the plugin configuration table.
     *
     * @return string
     */
    private static function render_configs(): string {
        $configs = (array) get_config('local_aacuracore');
        ksort($configs);

        $rows = '';
        foreach ($configs as $k => $v) {
            $display = $v;
            if (stripos($k, 'key') !== false || stripos($k, 'secret') !== false || stripos($k, 'token') !== false) {
                $display = '•••••••• (hidden)';
            }
            $rows .= '<tr><td><code>' . s($k) . '</code></td><td><code>' . s($display) . '</code></td></tr>';
        }

        return '
        <div class="aacura-diag-card">
            <div class="aacura-diag-head">⚙️ Plugin Configuration</div>
            <div class="aacura-diag-body">
                <table class="aacura-diag-table">
                    <thead><tr><th>Setting</th><th>Value</th></tr></thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        </div>';
    }

    /**
     * Renders a read-only preview of the resolved persona system prompt for
     * each active scenario (placeholders substituted). This helps admins
     * verify how the scenario's embedded template, the global prompt_template
     * setting, or the default fallback resolves at runtime.
     *
     * @return string
     */
    private static function render_prompt_preview(): string {
        $active = get_config('local_aacuracore', 'active_scenarios');
        $codes = $active ? array_map('trim', explode(',', $active)) : ['anna', 'brianna', 'cathy', 'mary'];

        $source = '';
        $globalsetting = get_config('local_aacuracore', 'prompt_template');
        if (!empty($globalsetting)) {
            $source = 'GLOBAL SETTING';
        } else {
            $source = 'DEFAULT (hardcoded)';
        }

        $out = '';
        foreach ($codes as $code) {
            try {
                $sc = \local_aacuracore\scenario\scenario_loader::load($code, 0);
                $persona = $sc->get_persona();
                $name = $persona['name'] ?? ucfirst($code);

                // Resolve which template applies to this scenario.
                $template = $sc->get_prompt_template();
                if (!empty($template)) {
                    $localsource = 'SCENARIO';
                } else if (!empty($globalsetting)) {
                    $localsource = 'GLOBAL';
                } else {
                    $localsource = 'DEFAULT';
                }

                $rendered = \local_aacuracore\prompt_renderer::render(
                    \local_aacuracore\prompt_renderer::resolve_template($sc),
                    $sc,
                    'START'
                );

                $out .= '
                <div class="aacura-diag-card">
                    <div class="aacura-diag-head">🧠 System Prompt: ' . s(strtoupper($code)) . ' — ' . s($name) . '</div>
                    <div class="aacura-diag-body">
                        <p class="aacura-muted">Source: <strong>' . s($localsource) . '</strong></p>
                        <pre style="background:#f8f9fa;border:1px solid #e9ecef;padding:12px;border-radius:6px;
                                    white-space:pre-wrap;word-break:break-word;font-size:12px;max-height:320px;overflow-y:auto;">' . s($rendered) . '</pre>
                    </div>
                </div>';
            } catch (\Throwable $e) {
                $out .= '
                <div class="aacura-diag-card">
                    <div class="aacura-diag-head">🧠 System Prompt: ' . s(strtoupper($code)) . '</div>
                    <div class="aacura-diag-body"><p class="aacura-muted">Could not load scenario: ' . s($e->getMessage()) . '</p></div>
                </div>';
            }
        }

        return '
        <div class="aacura-diag-card">
            <div class="aacura-diag-head">🧠 Persona System Prompt Preview</div>
            <div class="aacura-diag-body">
                <p class="aacura-muted">Template source (site-wide): <strong>' . s($source) . '</strong>. Shows the fully resolved START-state system prompt with placeholders substituted.</p>
                ' . $out . '
            </div>
        </div>';
    }

    /**
     * Renders a read-only preview of the resolved evaluation (rubric) prompt.
     * This prompt is used in the second LLM call that grades the conversation
     * after the persona dialogue completes.
     *
     * @return string
     */
    private static function render_evaluation_preview(): string {
        $globalsetting = get_config('local_aacuracore', 'evaluation_prompt_template');
        $source = !empty($globalsetting) ? 'GLOBAL SETTING' : 'DEFAULT (hardcoded)';
        $template = \local_aacuracore\prompt_renderer::resolve_evaluation_template();

        // Build a sample rubric to demonstrate the {{rubric}} substitution.
        $samplerubric = implode("\n", array_map(
            fn($k, $v) => ucfirst(str_replace("_", " ", $k)) . ": " . $v,
            array_keys($sample = [
                "greeting" => "1 point if teacher starts with a greeting.",
                "empathy" => "1 point for a statement of empathy.",
                "wrap_up" => "1 point for asking 'anything else to add?'.",
            ]),
            $sample
        ));

        $rendered = str_replace('{{rubric}}', $samplerubric, $template);

        return '
        <div class="aacura-diag-card">
            <div class="aacura-diag-head">🧪 Evaluation (Rubric) System Prompt</div>
            <div class="aacura-diag-body">
                <p class="aacura-muted">Template source: <strong>' . s($source) . '</strong>. This prompt is used in the second LLM call that grades the teacher\'s replies after the parent-persona dialogue ends (called when the max turn count is reached or a terminal state is hit). <code>{{rubric}}</code> is substituted with the scenario\'s per-state rubric.</p>
                <pre style="background:#f8f9fa;border:1px solid #e9ecef;padding:12px;border-radius:6px;
                            white-space:pre-wrap;word-break:break-word;font-size:12px;max-height:320px;overflow-y:auto;">' . s($rendered) . '</pre>
            </div>
        </div>';
    }

    /**
     * Renders the connected AI provider summary.
     *
     * @return string
     */
    private static function render_provider(): string {
        global $DB;

        $strategy = get_config('local_aacuracore', 'engine_strategy') ?: 'moodle_core_ai';
        $selected = get_config('local_aacuracore', 'core_ai_selected_provider');

        $strategybadge = '';
        if ($strategy === 'moodle_core_ai') {
            $strategybadge = '<span class="aacura-badge aacura-badge-active">ACTIVE</span>';
        } else {
            $strategybadge = '<span class="aacura-badge aacura-badge-inactive">INACTIVE</span>';
        }

        $providerhtml = '';
        if (class_exists('\\core_ai\\manager')) {
            try {
                $manager = new \core_ai\manager($DB);
                $providers = $manager->get_provider_records();
                if (empty($providers)) {
                    $providerhtml = '<p class="aacura-muted">No AI providers registered.</p>';
                }
                foreach ($providers as $p) {
                    $isselected = ($p->provider === $selected);
                    $enabled = !empty($p->enabled);
                    $badge = $isselected
                        ? '<span class="aacura-badge aacura-badge-selected">CONNECTED</span>'
                        : ($enabled ? '<span class="aacura-badge aacura-badge-active">ENABLED</span>'
                                    : '<span class="aacura-badge aacura-badge-inactive">DISABLED</span>');

                    $actions = '';
                    $inst = $manager->get_provider_instances(['id' => $p->id]);
                    $inst = reset($inst);
                    if ($inst && !empty($inst->actionconfig)) {
                        $actions = '<ul>';
                        foreach ($inst->actionconfig as $action => $conf) {
                            $en = ($conf['enabled'] ?? false) ? 'ENABLED' : 'DISABLED';
                            $model = $conf['settings']['model'] ?? 'none';
                            $actions .= '<li><code>' . s($action) . '</code> → ' . $en . ' (Model: <code>' . s($model) . '</code>)</li>';
                        }
                        $actions .= '</ul>';
                    } else {
                        $actions = '<p class="aacura-muted">No actions configured for this provider.</p>';
                    }

                    $providerhtml .= '
                    <div class="aacura-provider' . ($isselected ? ' aacura-provider-active' : '') . '">
                        <div class="aacura-provider-name">' . s($p->name) . ' <code>' . s($p->provider) . '</code> ' . $badge . '</div>
                        ' . $actions . '
                    </div>';
                }
            } catch (\Throwable $e) {
                $providerhtml = '<p class="aacura-muted">Could not read provider records: ' . s($e->getMessage()) . '</p>';
            }
        } else {
            $providerhtml = '<p class="aacura-muted">Moodle Core AI subsystem (\core_ai\manager) is not available.</p>';
        }

        return '
        <div class="aacura-diag-card">
            <div class="aacura-diag-head">🔌 Connected Provider</div>
            <div class="aacura-diag-body">
                <p><strong>Engine strategy:</strong> <code>' . s($strategy) . '</code> ' . $strategybadge . '</p>
                <p><strong>Selected provider:</strong> <code>' . s($selected ?: '(none)') . '</code></p>
                ' . $providerhtml . '
            </div>
        </div>';
    }

    /**
     * Renders visual state-machine graphs for all active scenarios.
     *
     * @return string
     */
    private static function render_scenario_graphs(): string {
        $active = get_config('local_aacuracore', 'active_scenarios');
        $codes = $active ? array_map('trim', explode(',', $active)) : ['anna', 'brianna', 'cathy', 'mary'];

        $out = '';
        foreach ($codes as $code) {
            try {
                $sc = \local_aacuracore\scenario\scenario_loader::load($code, 0);
                $persona = $sc->get_persona();
                $name = $persona['name'] ?? ucfirst($code);
                $mood = $persona['initial_mood'] ?? 'unknown';
                $objectives = implode(', ', $sc->get_learning_objectives());
                $graph = self::render_graph($sc);

                $out .= '
                <div class="aacura-diag-card">
                    <div class="aacura-diag-head">🗺️ Scenario: ' . s(strtoupper($code)) . ' — ' . s($name) . '</div>
                    <div class="aacura-diag-body">
                        <div class="aacura-scenario-title">Initial mood: ' . s($mood) . ' &nbsp;|&nbsp; Objectives: ' . s($objectives) . '</div>
                        <div class="aacura-graph-wrap">' . $graph . '</div>
                    </div>
                </div>';
            } catch (\Throwable $e) {
                $out .= '
                <div class="aacura-diag-card">
                    <div class="aacura-diag-head">🗺️ Scenario: ' . s(strtoupper($code)) . '</div>
                    <div class="aacura-diag-body"><p class="aacura-muted">Could not load scenario: ' . s($e->getMessage()) . '</p></div>
                </div>';
            }
        }

        return '
        <div class="aacura-diag-card">
            <div class="aacura-diag-head">🗺️ Scenario State-Machine Graphs</div>
            <div class="aacura-diag-body">
                <p class="aacura-muted">Green edges = PASS route, red edges = FAIL route. Blue nodes are terminal states.</p>
                ' . $out . '
            </div>
        </div>';
    }

    /**
     * Renders a single scenario as an SVG directed graph.
     *
     * @param \local_aacuracore\scenario\scenario_definition $sc
     * @return string
     */
    private static function render_graph($sc): string {
        $states = $sc->get_states();
        $statekeys = array_keys($states);

        // BFS depth from START for a layered layout.
        $depth = [];
        $queue = ['START'];
        $depth['START'] = 0;
        $seen = ['START' => true];
        while ($queue) {
            $cur = array_shift($queue);
            $node = $states[$cur] ?? null;
            if (!$node) {
                continue;
            }
            $crit = $node['expected_criteria'] ?? null;
            if (!$crit) {
                continue;
            }
            foreach (['pass_route', 'fail_route'] as $r) {
                $next = $crit[$r] ?? null;
                if ($next && !isset($seen[$next])) {
                    $seen[$next] = true;
                    $depth[$next] = $depth[$cur] + 1;
                    $queue[] = $next;
                }
            }
        }
        foreach ($statekeys as $k) {
            if (!isset($depth[$k])) {
                $depth[$k] = 0;
            }
        }

        // Group nodes by depth.
        $layers = [];
        foreach ($statekeys as $k) {
            $layers[$depth[$k]][] = $k;
        }
        ksort($layers);

        $w = 900;
        $h = 520;
        $nodew = 150;
        $nodeh = 46;
        $positions = [];
        $layercount = count($layers);
        $layerspacing = $layercount > 1 ? ($h - 120) / ($layercount - 1) : 0;

        foreach ($layers as $d => $nodes) {
            $y = 70 + $d * $layerspacing;
            $n = count($nodes);
            $xspacing = $n > 1 ? ($w - 220) / ($n - 1) : 0;
            $startx = ($w - ($n - 1) * $xspacing) / 2;
            foreach ($nodes as $i => $k) {
                $positions[$k] = ['x' => $startx + $i * $xspacing, 'y' => $y];
            }
        }

        // Collect edges.
        $edges = [];
        foreach ($statekeys as $k) {
            $node = $states[$k] ?? null;
            if (!$node) {
                continue;
            }
            $crit = $node['expected_criteria'] ?? null;
            if (!$crit) {
                continue;
            }
            $vt = $crit['validation_type'] ?? 'transition';
            foreach (['pass_route' => 'PASS', 'fail_route' => 'FAIL'] as $r => $label) {
                $next = $crit[$r] ?? null;
                if ($next && isset($positions[$next])) {
                    $edges[] = ['from' => $k, 'to' => $next, 'label' => $label, 'type' => $vt];
                }
            }
        }

        $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" xmlns="http://www.w3.org/2000/svg" font-family="Arial, sans-serif">';
        $svg .= '<defs>
            <marker id="aacura-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                <path d="M 0 0 L 10 5 L 0 10 z" fill="#6c757d"/>
            </marker>
        </defs>';

        // Edges.
        foreach ($edges as $e) {
            $f = $positions[$e['from']];
            $t = $positions[$e['to']];
            $x1 = $f['x'] + $nodew / 2;
            $y1 = $f['y'] + $nodeh / 2;
            $x2 = $t['x'] + $nodew / 2;
            $y2 = $t['y'] + $nodeh / 2;
            $color = ($e['label'] === 'PASS') ? '#198754' : '#dc3545';
            $svg .= '<line x1="' . $x1 . '" y1="' . $y1 . '" x2="' . $x2 . '" y2="' . $y2 . '" stroke="' . $color . '" stroke-width="2" marker-end="url(#aacura-arrow)"/>';
            $mx = ($x1 + $x2) / 2;
            $my = ($y1 + $y2) / 2 - 6;
            $svg .= '<text x="' . $mx . '" y="' . $my . '" text-anchor="middle" font-size="11" font-weight="bold" fill="' . $color . '">' . s($e['label']) . ' (' . s($e['type']) . ')</text>';
        }

        // Nodes.
        foreach ($positions as $k => $pos) {
            $node = $states[$k] ?? [];
            $crit = $node['expected_criteria'] ?? null;
            $terminal = empty($crit);
            $fill = $terminal ? '#0d6efd' : '#4f2c11';
            $svg .= '<rect x="' . $pos['x'] . '" y="' . $pos['y'] . '" width="' . $nodew . '" height="' . $nodeh . '" rx="9" fill="' . $fill . '" stroke="#ffffff" stroke-width="2"/>';
            $svg .= '<text x="' . ($pos['x'] + $nodew / 2) . '" y="' . ($pos['y'] + $nodeh / 2 + 5) . '" text-anchor="middle" font-size="13" font-weight="bold" fill="#ffffff">' . s($k) . '</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }
}
