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

defined('MOODLE_INTERNAL') || die;

use local_geniai\scenario\scenario_definition;
use local_geniai\scenario\scenario_loader;
use local_geniai\strategy\response_strategy;
use local_geniai\strategy\generative_ai_api_strategy;
use local_geniai\strategy\deterministic_tree_strategy;
use local_geniai\state\bot_state;

/**
 * Class bot_engine coordinating state machines, databases, and strategies.
 *
 * @package   local_geniai
 * @copyright 2026 Antigravity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bot_engine {
    /** @var \stdClass Active Moodle session database record */
    private $sessionrecord;

    /** @var scenario_definition Active roleplay scenario configuration */
    private $scenario;

    /** @var response_strategy Selected response evaluation strategy */
    private $strategy;

    /** @var int Course module context ID */
    private $cmid;

    /**
     * Constructor.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $cmid
     * @param string $scenariocode
     */
    public function __construct(int $userid, int $courseid, int $cmid, string $scenariocode) {
        $this->cmid = $cmid;
        $this->scenario = scenario_loader::load($scenariocode, $cmid);
        $this->strategy = $this->resolve_strategy();
        $this->sessionrecord = $this->lookup_or_create_session($userid, $courseid, $scenariocode);
    }

    /**
     * Resolves the active evaluation strategy depending on Moodle global configurations.
     *
     * @return response_strategy
     */
    private function resolve_strategy(): response_strategy {
        $strategy = get_config('local_geniai', 'engine_strategy');
        $bearer = get_config('local_geniai', 'api_bearer_token');

        if ($strategy === 'external_llm' && !empty($bearer)) {
            return new generative_ai_api_strategy();
        }
        return new deterministic_tree_strategy();
    }

    /**
     * Looks up an existing active session or creates a new one in the database.
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

            // Seed initial bot prompt into messages if scenario defines START state prompt
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
     * @return response_strategy
     */
    public function get_strategy(): response_strategy {
        return $this->strategy;
    }

    /**
     * Gets the active Moodle database session record.
     *
     * @return \stdClass
     */
    public function get_session_record(): \stdClass {
        return $this->sessionrecord;
    }

    /**
     * Retrieves the interleaved message history from the database.
     *
     * @return array
     */
    public function get_messages(): array {
        global $DB;
        return $DB->get_records('local_geniai_messages', ['sessionid' => $this->sessionrecord->id], 'timestamp ASC');
    }

    /**
     * Gets total turns processed in the current session.
     *
     * @return int
     */
    public function get_turn_count(): int {
        global $DB;
        return $DB->count_records('local_geniai_messages', ['sessionid' => $this->sessionrecord->id, 'sender' => 'user']);
    }

    /**
     * Instantiates the current concrete state pattern class.
     *
     * @return bot_state
     */
    public function get_current_state(): bot_state {
        $stateclass = '\\local_geniai\\state\\state_' . strtolower($this->sessionrecord->current_state);
        if (class_exists($stateclass)) {
            return new $stateclass();
        }
        return new \local_geniai\state\state_start();
    }

    /**
     * Registers a new text message in the database history.
     *
     * @param string $sender 'user' or 'system'
     * @param string $message
     * @return int Inserted message ID
     */
    public function log_message(string $sender, string $message): int {
        global $DB;

        $msg = new \stdClass();
        $msg->sessionid = $this->sessionrecord->id;
        $msg->sender = $sender;
        $msg->message_text = $message;
        $msg->timestamp = time();

        return $DB->insert_record('local_geniai_messages', $msg);
    }

    /**
     * Logs an evaluative metric scoring.
     *
     * @param string $metrictype Check type name
     * @param float $value Pass = 1.00, Fail = 0.00
     */
    public function log_analytics(string $metrictype, float $value): void {
        global $DB;

        $analytic = new \stdClass();
        $analytic->sessionid = $this->sessionrecord->id;
        $analytic->metric_type = $metrictype;
        $analytic->metric_value = $value;

        $DB->insert_record('local_geniai_analytics', $analytic);
    }

    /**
     * Clears all message logs and resets the session back to START.
     */
    public function reset_session(): void {
        global $DB;

        $DB->delete_records('local_geniai_messages', ['sessionid' => $this->sessionrecord->id]);
        $DB->delete_records('local_geniai_analytics', ['sessionid' => $this->sessionrecord->id]);

        $this->sessionrecord->current_state = 'START';
        $this->sessionrecord->timemodified = time();
        $DB->update_record('local_geniai_sessions', $this->sessionrecord);

        // Seed initial bot prompt into messages if scenario defines START state prompt
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

        // Log the student's message
        $this->log_message('user', $cleanedmessage);

        // 2. State & Intent validation routing
        $currentstate = $this->get_current_state();
        $nextstatekey = $currentstate->process_input($cleanedmessage, $this);

        // Update database session state
        $this->sessionrecord->current_state = $nextstatekey;
        $this->sessionrecord->timemodified = time();
        $DB->update_record('local_geniai_sessions', $this->sessionrecord);

        $turncount = $this->get_turn_count();

        // 3. Check for final Turn 10 Rubric grading completion
        if ($turncount >= 10 || $nextstatekey === 'RESOLUTION' || $nextstatekey === 'FAIL_STATE') {
            $feedback = $this->generate_rubric_evaluation();
            $this->log_message('system', $feedback);

            // Sync performance metrics straight to Gradebook via trigger
            $this->trigger_gradebook_sync();

            // Auto reset/clear active state variables
            $this->sessionrecord->current_state = 'START';
            $DB->update_record('local_geniai_sessions', $this->sessionrecord);

            return $feedback;
        }

        // 4. Regular response generation using active strategy
        $messages = $this->get_messages();
        $botreply = $this->strategy->generate_response($messages, $this->scenario, $nextstatekey);

        // Log parent response in history
        $this->log_message('system', $botreply);

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

        // Formulate feedback compile prompt for OpenAI
        $fullcontext = [
            [
                "role" => "system",
                "content" => "You are evaluating a simulated parent-teacher conversation.\n\n" .
                             "Below are only the teacher's replies (from role: `user`).\n" .
                             "Do NOT evaluate any system or parent messages — ONLY evaluate the teacher replies.\n\n" .
                             "Rubric:\n" . $formattedrubric . "\n\n" .
                             "Feedback Format:\n" .
                             "🎯 Your goal is to group feedback into the 4 steps of LAFF:\n" .
                             "1. **Listen, empathize, and communicate respect**\n" .
                             "2. **Ask questions and ask permission to take notes**\n" .
                             "3. **Focus on the issue**\n" .
                             "4. **Find a first step**\n\n" .
                             "🧮 Scoring:\n" .
                             "- Start from 10 points.\n" .
                             "- Award 1 point for each clearly demonstrated rubric-aligned move.\n" .
                             "- Do not show point deductions.\n" .
                             "- Instead, if something was missed, write it as a Missed opportunity: .\n" .
                             "- Mention the turn number (teacher turn) in parentheses.\n\n" .
                             "📝 Output Format:\n" .
                             "Start with - **Grade - X out of 10** in bold.\n" .
                             "For each LAFF step, use a heading (bold, clear name of the step).\n" .
                             "Under each, list the bullets:\n" .
                             "- Earned ✅ 1 pt for ___ (turn #)\n" .
                             "- Missed opportunity: 💡 ___\n\n" .
                             "🧑‍🏫 End with:\n" .
                             "- Total score: X out of 10\n" .
                             "- A warm thank-you message\n" .
                             "- Suggest to click **Clear Chat** button to restart if needed\n\n" .
                             "Make the tone of the entire feedback response emoji-filled, kind and supportive.",
            ],
        ];

        foreach ($teacherreplies as $reply) {
            $fullcontext[] = ["role" => "user", "content" => $reply];
        }

        try {
            $response = api::chat_completions($fullcontext);
            if (isset($response["choices"][0]["message"]["content"])) {
                return trim($response["choices"][0]["message"]["content"]);
            }
            return "<h3>Simulation Complete!</h3><p>Your responses have been saved and sent to Gradebook.</p>" .
                   "<p><em>Note: Automated rubric feedback is temporarily unavailable (API connection timeout).</em></p>";
        } catch (\Exception $e) {
            return "<h3>Simulation Complete!</h3><p>Your responses have been saved and sent to Gradebook.</p>";
        }
    }
}
