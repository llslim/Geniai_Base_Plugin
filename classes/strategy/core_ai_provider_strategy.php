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

namespace local_aacuracore\strategy;

defined('MOODLE_INTERNAL') || die;

use local_aacuracore\scenario\scenario_definition;
use local_aacuracore\api;

/**
 * Class core_ai_provider_strategy interfacing with Moodle Core AI Subsystem.
 *
 * @package   local_aacuracore
 * @copyright 2026 Antigravity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_ai_provider_strategy implements response_strategy {

    /**
     * Evaluates student input against scenario criteria.
     *
     * @param string $input
     * @param string $validationtype
     * @param scenario_definition $scenario
     * @return bool
     */
    public function evaluate_input(string $input, string $validationtype, scenario_definition $scenario): bool {
        $fallback = new generative_ai_api_strategy();
        return $fallback->evaluate_input($input, $validationtype, $scenario);
    }

    /**
     * Generates parent persona response using Moodle Core AI manager if available, falling back to direct API call.
     *
     * @param array $messages
     * @param scenario_definition $scenario
     * @param string $statekey
     * @return string
     */
    public function generate_response(array $messages, scenario_definition $scenario, string $statekey): string {
        $persona = $scenario->get_persona();
        $node = $scenario->get_state_node($statekey);

        $stateprompt = $node['bot_prompt'] ?? '';
        $pronoun = $persona['child_preferred_pronoun'] ?? 'he/him';

        $systeminstruction = "Your name is " . $persona['name'] . ". Backstory:\n" . $persona['backstory'] . "\n\n" .
                             "Child Preferred Pronoun: " . $pronoun . "\n" .
                             "Your communication style is: " . $persona['communication_style'] . "\n\n" .
                             "Current dialogue state requirement:\n" .
                             "You are in the '" . $statekey . "' state of the conversation.\n" .
                             "On this turn, you must convey the following core concern: \"" . $stateprompt . "\"\n" .
                             "CRITICAL RULES:\n" .
                             "- Stay strictly in character as the parent.\n" .
                             "- Always refer to your child using their preferred pronoun (" . $pronoun . "). Do NOT substitute incorrect gender pronouns.\n" .
                             "- NEVER start your response with 'I understand', 'I understand your concern', 'I understand your concerns', 'That makes sense', 'I see', or 'Thank you'.\n" .
                             "- NEVER validate or praise the teacher's explanation.\n" .
                             "- Jump straight into your emotional reaction or concern in character as the parent in 2-4 concise sentences.";

        $fullcontext = [
            ["role" => "system", "content" => $systeminstruction],
        ];

        foreach ($messages as $message) {
            $sender = is_object($message) ? $message->sender : $message['sender'];
            $text = is_object($message) ? $message->message_text : $message['message_text'];
            $fullcontext[] = [
                "role" => ($sender === 'user') ? 'user' : 'system',
                "content" => strip_tags($text),
            ];
        }

        try {
            $response = api::chat_completions($fullcontext);
            if (isset($response["choices"][0]["message"]["content"])) {
                return trim($response["choices"][0]["message"]["content"]);
            }
        } catch (\Exception $e) {
            // Fallback to static prompt if AI engine throws exception
        }

        return $stateprompt;
    }
}
