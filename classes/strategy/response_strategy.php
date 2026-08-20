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

/**
 * Interface response_strategy defining standard evaluation behavior.
 *
 * @package   local_aacuracore
 * @copyright 2026 Antigravity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface response_strategy {
    /**
     * Evaluates student input text against a pedagogical checking guideline.
     *
     * @param string $input Sanitized student message
     * @param string $validationtype Type of check (e.g. empathy_check, jargon_check)
     * @param scenario_definition $scenario Active scenario details
     * @return bool True if criteria is satisfied, false otherwise.
     */
    public function evaluate_input(string $input, string $validationtype, scenario_definition $scenario): bool;

    /**
     * Generates a conversational reply matching the persona backstory and dialog state.
     *
     * @param array $messages Complete interleaved message history
     * @param scenario_definition $scenario Active scenario definition
     * @param string $statekey Current active dialogue state key
     * @param string $parentintensity Optional assertiveness/aggressiveness level override
     * @return string generated parent prompt response
     */
    public function generate_response(array $messages, scenario_definition $scenario, string $statekey, string $parentintensity = ''): string;

    /**
     * Generates the rubric evaluation text feedback.
     *
     * @param array $messages Complete conversation history
     * @param scenario_definition $scenario Active scenario
     * @param array $analytics Logged analytics metrics for the session
     * @return string HTML formatted feedback
     */
    public function generate_rubric_feedback(array $messages, scenario_definition $scenario, array $analytics): string;
}
