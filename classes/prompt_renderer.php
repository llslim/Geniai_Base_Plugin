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

use local_aacuracore\scenario\scenario_definition;

/**
 * Central placeholder-substitution utility for LLM prompt templates.
 *
 * Both generative_ai_api_strategy and core_ai_provider_strategy use this
 * class to render the configurable prompt_template from a scenario JSON,
 * replacing {{placeholder}} tokens with the corresponding scenario fields.
 *
 * @package   local_aacuracore
 * @copyright 2026 AAC-RERC Chatbot Team
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt_renderer {

    /** @var string Default fallback template matching the original hardcoded behavior. */
    const DEFAULT_TEMPLATE = <<<'EOT'
Your name is {{persona_name}}. Backstory:
{{backstory}}

Persona voice and communication style: {{communication_style}}
Your intensity as a parent: {{parent_intensity}}

Current dialogue state requirement:
You are in the '{{statekey}}' state of the conversation.
On this turn, you must convey the following core concern: "{{stateprompt}}"

CRITICAL RULES — LAFF "Don't Cry" communication:
- Stay strictly in character as the {{role_display_label}}.
- Listen empathetically; do not react defensively when the trainee challenges you.
- Use plain, accessible language; if you use any clinical term or acronym, keep it natural for your role.
- Never criticize or compare the trainee's efforts.
- Stay in your role's voice and formality level ({{formality_level}}).
EOT;

    /**
     * Build a natural-language instruction describing how assertive/aggressive
     * the persona should be, derived from the global parent_intensity setting.
     *
     * @param string $intensitylevel One of very_low|low|medium|high|very_high
     * @return string Instruction line used in the {{parent_intensity}} placeholder.
     */
    public static function intensity_instruction(string $intensitylevel = ''): string {
        if (empty($intensitylevel)) {
            $intensitylevel = get_config('local_aacuracore', 'parent_intensity') ?: 'medium';
        }
        $map = [
            'very_low'   => 'extremely gentle, deferential, and cooperative; expresses feelings softly and never challenges; assume a very passive, agreeable tone.',
            'low'        => 'mildly assertive; mostly cooperative and polite, only occasionally expressing mild worry or firmness.',
            'medium'     => 'moderately assertive; clearly communicate your concerns and stand by your point of view without being rude or hostile.',
            'high'       => 'assertive and firm; press your wishes strongly, occasionally interrupt, and make your dissatisfaction clear while staying mostly professional.',
            'very_high'  => 'highly assertive and confrontational; be demanding, frustrated, sharply worded, and prone to expressing anger or ultimatums while still staying in character.',
        ];
        return $map[$intensitylevel] ?? $map['medium'];
    }

    /**
     * Resolve the effective prompt template for a scenario.
     *
     * Fallback chain:
     *   1. Scenario's embedded prompt_template (most specific)
     *   2. Site-wide global setting local_aacuracore/prompt_template
     *   3. Hardcoded DEFAULT_TEMPLATE constant
     *
     * @param scenario_definition $scenario The active scenario.
     * @return string The template to render.
     */
    public static function resolve_template(scenario_definition $scenario): string {
        $template = $scenario->get_prompt_template();
        if (!empty($template)) {
            return $template;
        }
        $globalsetting = get_config('local_aacuracore', 'prompt_template');
        if (!empty($globalsetting)) {
            return $globalsetting;
        }
        return self::DEFAULT_TEMPLATE;
    }

    /**
     * Substitute placeholders in a template string with scenario values.
     *
     * @param string $template    The raw template with {{placeholder}} tokens.
     * @param scenario_definition $scenario The active scenario.
     * @param string $statekey    The current dialogue state key.
     * @return string The rendered template with all placeholders replaced.
     */
    public static function render(string $template, scenario_definition $scenario, string $statekey): string {
        $persona = $scenario->get_persona();
        $node = $scenario->get_state_node($statekey);
        $stateprompt = $node['bot_prompt'] ?? '';
        $role = $scenario->get_role() ?? [];

        $replacements = [
            '{{persona_name}}'              => $persona['name'] ?? '',
            '{{backstory}}'                 => $persona['backstory'] ?? '',
            '{{pronoun}}'                   => $persona['child_preferred_pronoun'] ?? 'he/him',
            '{{communication_style}}'       => $persona['communication_style'] ?? '',
            '{{initial_mood}}'              => $persona['initial_mood'] ?? '',
            '{{statekey}}'                  => $statekey,
            '{{stateprompt}}'               => $stateprompt,
            '{{scenario_id}}'               => $scenario->get_id(),
            '{{learning_objectives}}'       => implode(', ', $scenario->get_learning_objectives()),
            '{{role_type}}'                 => $role['type'] ?? 'parent',
            '{{role_display_label}}'        => $role['display_label'] ?? 'Parent',
            '{{relationship_to_trainee}}'   => $role['relationship_to_trainee'] ?? '',
            '{{formality_level}}'           => $role['formality_level'] ?? 'informal',
            '{{power_dynamic}}'             => $role['power_dynamic'] ?? 'peer',
            '{{technical_expertise}}'       => $role['technical_expertise'] ?? 'low',
            '{{parent_intensity}}'           => self::intensity_instruction(),
        ];

        return strtr($template, $replacements);
    }
}