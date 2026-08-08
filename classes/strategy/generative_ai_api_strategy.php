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

namespace local_aacura_core\strategy;

defined('MOODLE_INTERNAL') || die;

use local_aacura_core\scenario\scenario_definition;
use local_aacura_core\api;

/**
 * Class generative_ai_api_strategy interfacing with LLM endpoints for evaluative roleplay.
 *
 * @package   local_aacura_core
 * @copyright 2026 Antigravity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generative_ai_api_strategy implements response_strategy {
    /**
     * Dynamically evaluates student text using a targeted LLM evaluation prompt.
     *
     * @param string $input
     * @param string $validationtype
     * @param scenario_definition $scenario
     * @return bool
     */
    public function evaluate_input(string $input, string $validationtype, scenario_definition $scenario): bool {
        // Construct targeted guidelines based on the required validation check
        switch ($validationtype) {
            case 'empathy_check':
                $guideline = "Determine if the teacher expressed genuine empathy, active listening, or validated the parent's feelings/frustration. The teacher must sound supportive and understanding, not defensive or purely technical.";
                break;
            case 'jargon_check':
                $guideline = "Scan the teacher's message for unexplained clinical jargon, acronyms, or professional abbreviations (e.g. 'AAC', 'SGD', 'IEP', 'SLP', 'apraxia'). If jargon was used, the teacher MUST have explained or introduced its definition in a parent-friendly way. If they used unexplained terms, they fail. If no jargon was used at all, they pass.";
                break;
            case 'de_escalation_check':
                $guideline = "Evaluate if the teacher spoke respectfully, apologized or validated the parent's concern, and actively attempted to de-escalate the confrontation. Defensive, dismissive, or passive-aggressive responses fail.";
                break;
            case 'clarification_check':
                $guideline = "Evaluate if the teacher clearly explained the technology/processes or politely asked clarifying questions to address the parent's confusion without overwhelming them with clinical abbreviations.";
                break;
            default:
                $guideline = "Decide if the teacher's statement is professional, respectful, and constructively addresses the parent.";
        }

        $prompt = [
            [
                "role" => "system",
                "content" => "You are an expert pedagogical evaluator. Your job is to analyze a teacher's message during a simulated parent-teacher roleplay meeting.\n\n" .
                             "Pedagogical Guideline to evaluate: " . $guideline . "\n\n" .
                             "Respond with only 'yes' (if they passed/met the criteria) or 'no' (if they failed/missed the opportunity).",
            ],
            [
                "role" => "user",
                "content" => "Teacher's statement to evaluate: \"" . strip_tags($input) . "\"",
            ],
        ];

        try {
            $response = api::chat_completions($prompt, true);
            if (isset($response["choices"][0]["message"]["content"])) {
                $decision = strtolower(trim($response["choices"][0]["message"]["content"]));
                return (strpos($decision, 'yes') !== false);
            }
            if (isset($response['error']['message'])) {
                throw new \Exception($response['error']['message']);
            }
            throw new \Exception("External API returned no choices");
        } catch (\Exception $e) {
            // Fallback to pattern matcher if external API fails
            $fallback = new regex_matcher_strategy();
            return $fallback->evaluate_input($input, $validationtype, $scenario);
        }

        return false;
    }

    /**
     * Dynamic conversational persona reply generation using complete history and active state directives.
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

        // Overwrite system instructions with detailed context, backstory, and state directives
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

        // Format and interleave conversation history safely supporting both arrays and stdClass objects
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
            if (isset($response['error']['message'])) {
                throw new \Exception($response['error']['message']);
            }
            throw new \Exception("External API returned no choices");
        } catch (\Exception $e) {
            // Fallback to static prompt if cURL errors out
            return $stateprompt;
        }

        return $stateprompt;
    }
}
