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

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for AACURA Chatbot scenarios.
 *
 * @package     local_aacuracore
 * @category    test
 * @copyright   2026 Antigravity
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scenarios_test extends \advanced_testcase {

    /**
     * Test loading all default scenarios.
     */
    public function test_scenarios_loading() {
        $scenarios = ['anna', 'brianna', 'cathy', 'mary'];

        foreach ($scenarios as $s) {
            $sc = \local_aacuracore\scenario\scenario_loader::load($s, 0);
            
            $this->assertEquals($s, $sc->get_id(), "Scenario ID should match '{$s}'");
            $this->assertNotEmpty($sc->get_persona(), "Persona metadata should not be empty for '{$s}'");
            $this->assertNotEmpty($sc->get_learning_objectives(), "Learning objectives should not be empty for '{$s}'");
            
            $persona = $sc->get_persona();
            $this->assertArrayHasKey('name', $persona, "Persona should have a name");
            $this->assertArrayHasKey('backstory', $persona, "Persona should have a backstory");
            $this->assertArrayHasKey('communication_style', $persona, "Persona should have a communication style");
        }
    }

    /**
     * Validate conversational state transitions to ensure no broken routes exist.
     */
    public function test_scenario_state_transitions() {
        $scenarios = ['anna', 'brianna', 'cathy', 'mary'];

        foreach ($scenarios as $s) {
            $sc = \local_aacuracore\scenario\scenario_loader::load($s, 0);
            $states = $sc->get_states();
            
            $this->assertNotEmpty($states, "States list should not be empty for scenario '{$s}'");
            $this->assertArrayHasKey('START', $states, "Scenario '{$s}' must have a 'START' state");

            foreach ($states as $statekey => $node) {
                $this->assertArrayHasKey('bot_prompt', $node, "State '{$statekey}' in '{$s}' must have a bot_prompt");
                
                $criteria = $node['expected_criteria'] ?? null;
                if (!empty($criteria)) {
                    $this->assertArrayHasKey('validation_type', $criteria, "State '{$statekey}' expected_criteria must specify validation_type");
                    
                    if (isset($criteria['pass_route'])) {
                        $passroute = $criteria['pass_route'];
                        $this->assertArrayHasKey($passroute, $states, "PASS route '{$passroute}' from state '{$statekey}' in scenario '{$s}' must point to an existing state");
                    }
                    
                    if (isset($criteria['fail_route'])) {
                        $failroute = $criteria['fail_route'];
                        $this->assertArrayHasKey($failroute, $states, "FAIL route '{$failroute}' from state '{$statekey}' in scenario '{$s}' must point to an existing state");
                    }
                }
            }
        }
    }
}
