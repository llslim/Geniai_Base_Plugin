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

namespace local_geniai;

use local_geniai\scenario\scenario_loader;
use local_geniai\scenario\scenario_definition;
use local_geniai\scenario\state_node;

defined('MOODLE_INTERNAL') || die;

/**
 * Core Orchestrator Class bot_engine.
 *
 * Responsibilities:
 * - Manages session lifecycle state machine.
 * - Handles prompt/response pipelines.
 * - Logs turn history to DB tables local_geniai_sessions, local_geniai_messages, and local_geniai_analytics.
 * - Triggers dynamic rubric evaluation & Gradebook integration on turn completion.
 *
 * @package   local_geniai
 * @copyright 2026 AAC-RERC Chatbot Team
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bot_engine {

    /** @var int $userid */
    private int $userid;

    /** @var int $courseid */
    private int $courseid;

    /** @var int $cmid */
    private int $cmid;

    /** @var scenario_definition $scenario */
    private scenario_definition $scenario;

    /** @var mixed $strategy */
    private $strategy;

    /** @var \stdClass $sessionrecord */
    private \stdClass $sessionrecord;

    /**
     * Constructor.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $cmid
     * @param string $scenariocode
     */
    public function __construct(int $userid, int $courseid, int $cmid = 0, string $scenariocode = 'anna') {
        $this->userid = $userid;
        $this->courseid = $courseid;
        $this->cmid = $cmid;
        $this->scenario = scenario_loader::load($scenariocode, $courseid);

        $strategytype = get_config('local_geniai', 'engine_strategy') ?: 'external_llm';
        if ($strategytype === 'regex') {
            $this->strategy = new \local_geniai\strategy\regex_matcher_strategy();
        } else if ($strategytype === 'moodle_core_ai') {
            $this->strategy = new \local_geniai\strategy\core_ai_provider_strategy();
        } else {
            $this->strategy = new \local_geniai\strategy\generative_ai_api_strategy();
        }

        $this->sessionrecord = $this->lookup_or_create_session($userid, $courseid, $scenariocode);
    }

    /**
     * Looks up existing active session or initializes a new state machine session.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $scenariocode
     * @return \stdClass
     */
    private function lookup_or_create_session(int $userid, int $courseid, string $scenariocode): \stdClass {
        global $DB;

        $record = $DB->get_record('local_geniai_sessions', [
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $this->cmid,
        ], '*', IGNORE_MULTIPLE);

        if ($record && $record->scenariocode !== $scenariocode) {
            $record->scenariocode = $scenariocode;
            $DB->update_record('local_geniai_sessions', $record);
        }

        if (!$record) {
            $record = new \stdClass();
            $record->userid = $userid;
            $record->courseid = $courseid;
            $record->cmid = $this->cmid;
            $record->scenariocode = $scenariocode;
            $record->current_state = 'START';
            $record->timecreated = time();
            $record->timemodified = time();
            $record->id = $DB->insert_record('local_geniai_sessions', $record);

            // Seed initial parent prompt into messages if scenario defines START state prompt
            $startnode = $this->scenario->get_state('START');
            if ($startnode && !empty($startnode['bot_prompt'])) {
                $msg = new \stdClass();
                $msg->sessionid = $record->id;
                $msg->sender = 'system';
                $msg->message_text = $startnode['bot_prompt'];
                $msg->timestamp = time();
                $DB->insert_record('local_geniai_messages', $msg);
            }
        }

        return $record;
    }

    /**
     * Gets the active scenario definition.
     *
     * @return scenario_definition
     */
    public function get_scenario(): scenario_definition {
        return $this->scenario;
    }

    /**
     * Gets the active response strategy.
     *
     * @return mixed
     */
    public function get_strategy() {
        return $this->strategy;
    }

    /**
     * Gets active database session record.
     *
     * @return \stdClass
     */
    public function get_session_record(): \stdClass {
        return $this->sessionrecord;
    }

    /**
     * Gets the active state node array data.
     *
     * @return array|null
     */
    public function get_current_state(): ?array {
        $statekey = $this->sessionrecord->current_state ?? 'START';
        return $this->scenario->get_state_node($statekey);
    }

    /**
     * Logs conversation turn message to DB.
     *
     * @param string $sender 'user' | 'system'
     * @param string $message
     * @return int message ID
     */
    public function log_message(string $sender, string $message): int {
        global $DB;

        $record = new \stdClass();
        $record->sessionid = $this->sessionrecord->id;
        $record->sender = $sender;
        $record->message_text = $message;
        $record->timestamp = time();

        return (int)$DB->insert_record('local_geniai_messages', $record);
    }

    /**
     * Logs performance metrics to local_geniai_analytics DB table.
     *
     * @param string $metrictype
     * @param float $value
     * @return int record ID
     */
    public function log_analytic(string $metrictype, float $value): int {
        global $DB;

        $record = new \stdClass();
        $record->sessionid = $this->sessionrecord->id;
        $record->metric_type = $metrictype;
        $record->metric_value = $value;
        $record->timestamp = time();

        return (int)$DB->insert_record('local_geniai_analytics', $record);
    }

    /**
     * Returns all turn messages for active session.
     *
     * @return array
     */
    public function get_messages(): array {
        global $DB;
        return array_values($DB->get_records('local_geniai_messages', ['sessionid' => $this->sessionrecord->id], 'timestamp ASC, id ASC'));
    }

    /**
     * Calculates current turn count (student turns).
     *
     * @return int
     */
    public function get_turn_count(): int {
        global $DB;
        return (int)$DB->count_records('local_geniai_messages', ['sessionid' => $this->sessionrecord->id, 'sender' => 'user']);
    }

    /**
     * Resets the active session back to START.
     */
    public function reset_session(): void {
        global $DB;

        $DB->delete_records('local_geniai_messages', ['sessionid' => $this->sessionrecord->id]);
        $DB->delete_records('local_geniai_analytics', ['sessionid' => $this->sessionrecord->id]);

        $this->sessionrecord->current_state = 'START';
        $this->sessionrecord->timemodified = time();
        $DB->update_record('local_geniai_sessions', $this->sessionrecord);

        // Seed initial parent prompt into messages if scenario defines START state prompt
        $startnode = $this->scenario->get_state('START');
        if ($startnode && !empty($startnode['bot_prompt'])) {
            $msg = new \stdClass();
            $msg->sessionid = $this->sessionrecord->id;
            $msg->sender = 'system';
            $msg->message_text = $startnode['bot_prompt'];
            $msg->timestamp = time();
            $DB->insert_record('local_geniai_messages', $msg);
        }
    }

    /**
     * Processes user text message through the pipeline:
     * 1. XSS / Script Sanitization.
     * 2. Dialog state transition validation and routing.
     * 3. Response strategy generation.
     * 4. Persistent database logging.
     * 5. Rubric evaluation check on Turn 10.
     *
     * @param string $usermessage
     * @return string generated response html
     */
    public function process_user_turn(string $usermessage): string {
        global $DB;

        // 1. Sanitization Layer
        $cleanedmessage = clean_param($usermessage, PARAM_CLEANHTML);

        // 2. State & Intent validation routing
        $statekey = $this->sessionrecord->current_state ?? 'START';
        $currentnode = $this->scenario->get_state_node($statekey);
        $nextstatekey = 'EXPLORATION'; // Default transition route
        if (!empty($currentnode['expected_criteria']['pass_route'])) {
            $nextstatekey = $currentnode['expected_criteria']['pass_route'];
        }

        // Update database session state
        $this->sessionrecord->current_state = $nextstatekey;
        $this->sessionrecord->timemodified = time();
        $DB->update_record('local_geniai_sessions', $this->sessionrecord);

        $turncount = $this->get_turn_count();

        // 3. Check for final Turn 10 Rubric grading completion or terminal state
        if ($turncount >= 10 || $nextstatekey === 'RESOLUTION' || $nextstatekey === 'FAIL_STATE') {
            $terminalnode = $this->scenario->get_state_node($nextstatekey);
            $parentclosing = ($terminalnode && !empty($terminalnode['bot_prompt'])) ? $terminalnode['bot_prompt'] : '';

            if (!empty($parentclosing)) {
                $this->log_message('system', $parentclosing);
            }

            $feedback = $this->generate_rubric_evaluation();
            $fullclosingresponse = !empty($parentclosing) ? ($parentclosing . "<br><br>" . $feedback) : $feedback;

            $this->log_message('system', $feedback);

            // Sync performance metrics straight to Gradebook via trigger
            $this->trigger_gradebook_sync();

            // Auto reset/clear active state variables
            $this->sessionrecord->current_state = 'START';
            $DB->update_record('local_geniai_sessions', $this->sessionrecord);

            return $fullclosingresponse;
        }

        // 4. Regular response generation using active strategy
        $messages = $this->get_messages();
        $botreply = $this->strategy->generate_response($messages, $this->scenario, $nextstatekey);

        if (!empty($botreply)) {
            // Log parent response in history
            $this->log_message('system', $botreply);
        }

        return $botreply;
    }

    /**
     * Programmatically triggers Moodle Gradebook sync updates.
     */
    private function trigger_gradebook_sync(): void {
        global $DB;

        // Fetch overall scores compiled inside database analytics
        $totalscore = 10; // Out of 10 points
        $analytics = $DB->get_records('local_geniai_analytics', ['sessionid' => $this->sessionrecord->id]);
        $missedcount = 0;
        foreach ($analytics as $analytic) {
            if ($analytic->metric_value == 0.00) {
                $missedcount++;
            }
        }
        $finalscore = max(0, $totalscore - $missedcount);

        // Trigger standard mod_geniai library grading hook
        if (file_exists(__DIR__ . '/../../mod/geniai/lib.php')) {
            require_once(__DIR__ . '/../../mod/geniai/lib.php');
            if (function_exists('geniai_grade_item_update')) {
                // Fetch course module record
                $cm = get_coursemodule_from_id('geniai', $this->sessionrecord->cmid);
                if ($cm) {
                    $geniai = $DB->get_record('geniai', ['id' => $cm->instance]);
                    if ($geniai) {
                        $grade = new \stdClass();
                        $grade->userid = $this->sessionrecord->userid;
                        $grade->rawgrade = $finalscore;
                        geniai_grade_item_update($geniai, $grade);
                    }
                }
            }
        }
    }

    /**
     * Dynamic rubric formatting and LLM evaluation compile.
     *
     * @return string
     */
    private function generate_rubric_evaluation(): string {
        global $DB;

        $messages = $this->get_messages();
        $teacherreplies = [];
        $turn = 1;
        foreach ($messages as $message) {
            if ($message->sender === 'user') {
                $teacherreplies[] = "Turn " . $turn . ": " . $message->message_text;
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
            $response = \local_geniai\api::chat_completions($fullcontext);
            if (isset($response["choices"][0]["message"]["content"])) {
                $rawcontent = trim($response["choices"][0]["message"]["content"]);
                // Strip markdown code fences if LLM accidentally returns them
                $rawcontent = preg_replace('/^```(?:html)?\s*/i', '', $rawcontent);
                $rawcontent = preg_replace('/\s*```$/', '', $rawcontent);
                return trim($rawcontent);
            }
            return "<h3>Simulation Complete!</h3><p>Your responses have been saved and sent to Gradebook.</p>" .
                   "<p><em>Note: Automated rubric feedback is temporarily unavailable (API connection timeout).</em></p>";
        } catch (\Exception $e) {
            return "<h3>Simulation Complete!</h3><p>Your responses have been saved and sent to Gradebook.</p>";
        }
    }
}
