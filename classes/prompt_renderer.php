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

Child Preferred Pronoun: {{pronoun}}
Your communication style is: {{communication_style}}

Current dialogue state requirement:
You are in the '{{statekey}}' state of the conversation.
On this turn, you must convey the following core concern: "{{stateprompt}}"

CRITICAL RULES:
- Stay strictly in character as the parent.
- Always refer to your child using their preferred pronoun ({{pronoun}}). Do NOT substitute incorrect gender pronouns.
- NEVER start your response with 'I understand', 'I understand your concern', 'I understand your concerns', 'That makes sense', 'I see', or 'Thank you'.
- NEVER validate or praise the teacher's explanation.
- Jump straight into your emotional reaction or concern in character as the parent in 2-4 concise sentences.
EOT;

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

        $replacements = [
            '{{persona_name}}'        => $persona['name'] ?? '',
            '{{backstory}}'           => $persona['backstory'] ?? '',
            '{{pronoun}}'             => $persona['child_preferred_pronoun'] ?? 'he/him',
            '{{communication_style}}' => $persona['communication_style'] ?? '',
            '{{initial_mood}}'        => $persona['initial_mood'] ?? '',
            '{{statekey}}'            => $statekey,
            '{{stateprompt}}'         => $stateprompt,
            '{{scenario_id}}'         => $scenario->get_id(),
            '{{learning_objectives}}' => implode(', ', $scenario->get_learning_objectives()),
        ];

        return strtr($template, $replacements);
    }
}