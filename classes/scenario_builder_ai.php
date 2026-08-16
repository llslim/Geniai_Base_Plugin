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

use local_aacuracore\scenario\scenario_loader;

/**
 * AI-driven interactive scenario builder.
 *
 * Switches the chatbot into an interviewer role that interacts with an author
 * to build a scenario JSON conversationally, then generates and validates a
 * complete scenario document for export.
 *
 * @package   local_aacuracore
 * @copyright 2026 AAC-RERC Chatbot Team
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scenario_builder_ai {

    /** @var array Interview state machine flow. */
    const FLOW = [
        'ASK_SCENARIO_ID',
        'ASK_PERSONA_NAME',
        'ASK_BACKSTORY',
        'ASK_PRONOUN',
        'ASK_MOOD',
        'ASK_COMM_STYLE',
        'ASK_ROLE',
        'ASK_OBJECTIVES',
        'ASK_START_PROMPT',
        'ASK_OPTIONAL_TEMPLATE',
        'CONFIRM',
        'GENERATE',
    ];

    /**
     * Builds the interviewer system prompt instructing the LLM to guide the
     * author through scenario creation using the LAFF-informed, structured flow.
     *
     * @return string
     */
    public static function interviewer_system_prompt(): string {
        return <<<'EOT'
You are InterviewBot, a friendly scenario interviewer for the AACURA training system.
Your job is to guide the author through creating a complete roleplay scenario by asking
ONE question at a time and collecting their answers.

Request the following fields in this order (do not ask all at once - ONE per turn):
1. scenario_id (short lowercase code, e.g. 'doctor_consult')
2. persona_name (the bot character's full name)
3. backstory (the persona's narrative/child context)
4. pronoun (he/him, she/her, they/them — optional for non-parent roles)
5. initial_mood (e.g. overwhelmed, anxious, confused, defensive, professional, helpful)
6. communication_style (one line describing tone)
7. role (type + display label among: parent, doctor, manufacturer_rep, aac_user, school_admin, iep_coordinator, insurance_rep, other_therapist; plus formality and power dynamic)
8. learning_objectives (comma separated)
9. START state bot_prompt (the persona's opening line)
10. OPTIONAL custom prompt_template (or say 'default')

RULES:
- Ask exactly ONE question per turn.
- After each answer, briefly confirm, then ask the next field.
- Do NOT generate the final JSON until ALL fields are gathered.
- After you have all fields, ask the user to say "generate" to create the JSON.
When the user says "generate", output ONLY a valid JSON object (no markdown fences) that matches the AACURA scenario schema:
{
  "scenario_id": "...",
  "prompt_template": "..." (optional),
  "persona": {
    "name": "...", "backstory": "...", "initial_mood": "...", "communication_style": "...",
    "role": { "type": "...", "display_label": "...", "relationship_to_trainee": "...", "formality_level": "formal|professional|informal", "power_dynamic": "hierarchical_superior|peer|hierarchical_subordinate", "technical_expertise": "high|medium|low|experiential" }
  },
  "learning_objectives": [...],
  "states": {
    "START": {"bot_prompt":"...", "expected_criteria": {"validation_type":"empathy_check","pass_route":"EXPLORATION","fail_route":"ESCALATION"}},
    "EXPLORATION": {"bot_prompt":"...", "expected_criteria": {"validation_type":"jargon_check","pass_route":"RESOLUTION","fail_route":"CONFUSION"}},
    "ESCALATION": {"bot_prompt":"...", "expected_criteria": {"validation_type":"de_escalation_check","pass_route":"EXPLORATION","fail_route":"FAIL_STATE"}},
    "CONFUSION": {"bot_prompt":"...", "expected_criteria": {"validation_type":"clarification_check","pass_route":"EXPLORATION","fail_route":"ESCALATION"}},
    "RESOLUTION": {"bot_prompt":"...", "expected_criteria": null},
    "FAIL_STATE": {"bot_prompt":"...", "expected_criteria": null}
  }
}
If the author supplies a custom prompt_template, include it; otherwise omit it.
Always keep the LAFF 'Don't Cry' framework in mind (Listen/Empathize, Ask, Focus, First Steps; no criticism, no defensive reaction, no unexplained jargon) for the learning objectives.
EOT;
    }

    /**
     * Routes a user message within the interviewer session and optionally returns
     * a generated scenario JSON once the author confirms "generate".
     *
     * @param array $history    Conversation history (role => user/message text).
     * @param string $userinput The author's latest message.
     * @return array {reply: string, json: array|null}
     */
    public static function process_turn(array $history, string $userinput): array {
        $lower = strtolower(trim($userinput));
        $asking = ($lower === 'generate' || $lower === 'generate json' || $lower === 'done' || $lower === 'yes please');

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => self::interviewer_system_prompt()];
        foreach ($history as $m) {
            $messages[] = [
                'role' => ($m['role'] === 'bot') ? 'assistant' : 'user',
                'content' => strip_tags($m['content'] ?? ''),
            ];
        }
        $messages[] = ['role' => 'user', 'content' => strip_tags($userinput)];

        $response = api::chat_completions($messages);
        $reply = $response['choices'][0]['message']['content'] ?? '';
        if (empty($reply)) {
            return ['reply' => 'Sorry, I could not process that. Could you try again?', 'json' => null];
        }

        // If the author asked to generate, try to extract a JSON object.
        $json = null;
        if ($asking) {
            $json = self::extract_json($reply);
            if ($json) {
                $validation = self::validate_json($json);
                if (is_string($validation)) {
                    // Feed the validation error back, ask to fix.
                    $fixprompt = [
                        ['role' => 'system', 'content' => self::interviewer_system_prompt()],
                        ['role' => 'user', 'content' => $userinput],
                        ['role' => 'assistant', 'content' => $reply],
                        ['role' => 'user', 'content' => "The generated scenario failed validation: {$validation}. Please fix the JSON and output only valid JSON."],
                    ];
                    $fixresponse = api::chat_completions($fix);
                    $fixreply = $fixresponse['choices'][0]['message']['content'] ?? '';
                    $fixedjson = self::extract_json($fixreply);
                    if ($fixedjson) {
                        $json = $fixedjson;
                        $reply = $fixreply;
                        $validation = self::validate_json($json);
                    }
                    if (is_string($validation)) {
                        return ['reply' => "⚠️ The generated scenario could not be validated: {$validation}.\n\nPlease correct the details and say 'generate' again.", 'json' => null];
                    }
                }
                return ['reply' => $reply, 'json' => $json];
            }
        }

        return ['reply' => $reply, 'json' => $json];
    }

    /**
     * Extract a JSON object from an LLM response (handles markdown code fences).
     *
     * @param string $text
     * @return array|null
     */
    protected static function extract_json(string $text): ?array {
        // Strip markdown fences if present.
        if (preg_match('/```json\s*([\s\S]*?)```/', $text, $m)) {
            $text = $m[1];
        } else if (preg_match('/```\s*([\s\S]*?)```/', $text, $m)) {
            $text = $m[1];
        }
        // Find the first { ... } block.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $candidate = substr($text, $start, $end - $start + 1);
        $json = json_decode($candidate, true);
        return is_array($json) ? $json : null;
    }

    /**
     * Validate a scenario JSON structure. Returns true if valid, or an error string.
     *
     * @param array $json
     * @return true|string
     */
    protected static function validate_json(array $json) {
        if (empty($json['scenario_id'])) {
            return 'missing scenario_id';
        }
        if (empty($json['persona']['name'])) {
            return 'missing persona.name';
        }
        if (empty($json['states']['START']['bot_prompt'])) {
            return 'missing states.START.bot_prompt';
        }
        try {
            $sc = scenario_loader::from_array($json);
            if (!$sc) {
                return 'could not build scenario definition';
            }
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
        return true;
    }
}