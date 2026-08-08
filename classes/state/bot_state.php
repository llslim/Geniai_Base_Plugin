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

namespace local_aacuracore\state;

defined('MOODLE_INTERNAL') || die;

use local_aacuracore\bot_engine;

/**
 * Abstract class representing a dialogue state in the chatbot state machine.
 *
 * @package   local_aacuracore
 * @copyright 2026 Antigravity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class bot_state {
    /**
     * Gets the unique state key (e.g., 'START', 'EXPLORATION', etc.)
     *
     * @return string
     */
    abstract public function get_key(): string;

    /**
     * Processes student input, conducts verification against expected criteria,
     * and performs the state transition.
     *
     * @param string $input Sanitized student message
     * @param bot_engine $engine The active dialogue state machine coordinator
     * @return string Next state key to transition to
     */
    public function process_input(string $input, bot_engine $engine): string {
        $node = $engine->get_scenario()->get_state_node($this->get_key());
        if (!$node || empty($node['expected_criteria'])) {
            // End state or no transition rules, stay in current state
            return $this->get_key();
        }

        $criteria = $node['expected_criteria'];
        $validationtype = $criteria['validation_type'];
        $passroute = $criteria['pass_route'];
        $failroute = $criteria['fail_route'];

        // Evaluate validation criteria against response strategy
        $strategy = $engine->get_strategy();
        $isvalid = $strategy->evaluate_input($input, $validationtype, $engine->get_scenario());

        // Store analytics marker
        $engine->log_analytics($validationtype, $isvalid ? 1.00 : 0.00);

        return $isvalid ? $passroute : $failroute;
    }

    /**
     * Returns the parent prompt for this state.
     *
     * @param bot_engine $engine
     * @return string
     */
    public function get_prompt(bot_engine $engine): string {
        $node = $engine->get_scenario()->get_state_node($this->get_key());
        if ($node && !empty($node['bot_prompt'])) {
            return $node['bot_prompt'];
        }
        return '';
    }
}
