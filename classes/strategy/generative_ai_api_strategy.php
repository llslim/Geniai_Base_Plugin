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
 * Class generative_ai_api_strategy interfacing with LLM endpoints for evaluative roleplay.
 *
 * @package   local_aacuracore
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

    /**
     * Generates the rubric evaluation text feedback.
     *
     * @param array $messages Complete conversation history
     * @param scenario_definition $scenario Active scenario
     * @param array $analytics Logged analytics metrics for the session
     * @return string HTML formatted feedback
     */
    public function generate_rubric_feedback(array $messages, scenario_definition $scenario, array $analytics): string {
        $teacherreplies = [];
        $turn = 1;
        foreach ($messages as $message) {
            $sender = is_object($message) ? $message->sender : $message['sender'];
            $text = is_object($message) ? $message->message_text : $message['message_text'];
            if ($sender === 'user') {
                $teacherreplies[] = "Turn " . $turn . ": " . $text;
                $turn++;
            }
        }

        $rubric = [
            "greeting" => "1 point if teacher starts with a greeting.",
            "empathy" => "1 point for a statement of empathy.",
            "note_permission" => "1 point if teacher asks to take notes.",
            "presenting_problem" => "1 point for asking 'what brings you in today?'.",
            "duration" => "1 point for asking 'how long has this been a problem?'.",
            "exception" => "1 point for asking 'was this ever not a problem?'.",
            "consultation" => "1 point for asking 'have you spoken to anyone else?'.",
            "wrap_up" => "1 point for asking 'anything else to add?'.",
        ];

        $formattedrubric = implode("\n", array_map(
            fn($k, $v) => ucfirst(str_replace("_", " ", $k)) . ": " . $v,
            array_keys($rubric),
            $rubric
        ));

        // Formulate feedback compile prompt for OpenAI with explicit HTML rendering instructions
        $fullcontext = [
            [
                "role" => "system",
                "content" => "You are evaluating a simulated parent-teacher conversation.\n\n" .
                             "Below are only the teacher's replies (from role: `user`).\n" .
                             "Do NOT evaluate any system or parent messages — ONLY evaluate the teacher replies.\n\n" .
                             "Rubric:\n" . $formattedrubric . "\n\n" .
                             "Feedback Format:\n" .
                             "🎯 Your goal is to group feedback into the 4 steps of LAFF:\n" .
                             "1. Listen, empathize, and communicate respect\n" .
                             "2. Ask questions and ask permission to take notes\n" .
                             "3. Focus on the issue\n" .
                             "4. Find a first step\n\n" .
                             "🧮 Scoring:\n" .
                             "- Start from 10 points.\n" .
                             "- Award 1 point for each clearly demonstrated rubric-aligned move.\n" .
                             "- Do not show point deductions.\n" .
                             "- Instead, if something was missed, write it as a Missed opportunity: .\n" .
                             "- Mention the turn number (teacher turn) in parentheses.\n\n" .
                             "IMPORTANT FORMATTING INSTRUCTIONS:\n" .
                             "- Output clean, raw, fully rendered HTML tags (e.g. <h3>, <h4>, <strong>, <ul>, <li>, <p>).\n" .
                             "- Do NOT wrap your output in markdown code blocks like ```html ... ```.\n" .
                             "- Do NOT output raw markdown asterisks or hash headers.\n\n" .
                             "HTML Structure:\n" .
                             "Start with: <h3><strong>Grade - X out of 10</strong></h3>\n" .
                             "For each LAFF step, use <h4><strong>Step Name</strong></h4>\n" .
                             "Under each step, use an HTML list <ul><li>...</li></ul> with list items:\n" .
                             "- <li>Earned ✅ 1 pt for ___ (turn #)</li>\n" .
                             "- <li>Missed opportunity: 💡 ___</li>\n\n" .
                             "End with:\n" .
                             "<p><strong>Total score: X out of 10</strong></p>\n" .
                             "<p>A warm thank-you message with emojis</p>\n" .
                             "<p>Suggest to click <strong>Clear Chat</strong> button to restart if needed</p>",
            ],
        ];

        foreach ($teacherreplies as $reply) {
            $fullcontext[] = ["role" => "user", "content" => $reply];
        }

        try {
            debugging('[AACURA] generate_rubric_feedback: calling chat_completions, context size=' . count($fullcontext), DEBUG_DEVELOPER);
            $response = api::chat_completions($fullcontext);
            if (isset($response["choices"][0]["message"]["content"])) {
                $rawcontent = trim($response["choices"][0]["message"]["content"]);
                // Strip markdown code fences if LLM accidentally returns them
                $rawcontent = preg_replace('/^```(?:html)?\s*/i', '', $rawcontent);
                $rawcontent = preg_replace('/\s*```$/', '', $rawcontent);
                return trim($rawcontent);
            }
            if (isset($response['error']['message'])) {
                throw new \Exception($response['error']['message']);
            }
            throw new \Exception("External API returned no choices");
        } catch (\Throwable $e) {
            debugging('[AACURA] generate_rubric_feedback: Throwable: ' . $e->getMessage() . '. Falling back to pattern matcher strategy.', DEBUG_DEVELOPER);
            $fallback = new regex_matcher_strategy();
            return $fallback->generate_rubric_feedback($messages, $scenario, $analytics);
        }
    }
}
