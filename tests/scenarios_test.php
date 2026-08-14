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

    /**
     * Test prompt_template loading and prompt_renderer substitution.
     */
    public function test_prompt_template_loading() {
        $scenarios = ['anna', 'brianna', 'cathy', 'mary'];

        foreach ($scenarios as $s) {
            $sc = \local_aacuracore\scenario\scenario_loader::load($s, 0);

            // get_prompt_template() returns null for static profiles (no JSON file in test env).
            // When null, the DEFAULT_TEMPLATE is used as fallback.
            $template = $sc->get_prompt_template();
            $this->assertNull($template, "Static profile '{$s}' should have null prompt_template");

            // Verify the default template constant is defined and non-empty.
            $default = \local_aacuracore\prompt_renderer::DEFAULT_TEMPLATE;
            $this->assertNotEmpty($default);
            $this->assertStringContainsString('{{persona_name}}', $default);
            $this->assertStringContainsString('{{backstory}}', $default);
            $this->assertStringContainsString('{{pronoun}}', $default);
            $this->assertStringContainsString('{{statekey}}', $default);
            $this->assertStringContainsString('{{stateprompt}}', $default);

            // Render the default template for each state and verify all placeholders resolve.
            foreach (['START', 'EXPLORATION', 'ESCALATION', 'CONFUSION', 'RESOLUTION', 'FAIL_STATE'] as $statekey) {
                $rendered = \local_aacuracore\prompt_renderer::render($default, $sc, $statekey);
                $this->assertNotEmpty($rendered, "Rendered prompt should not be empty for state '{$statekey}'");
                $this->assertStringNotContainsString('{{', $rendered,
                    "All placeholders should be resolved for state '{$statekey}' in '{$s}'");
                $this->assertStringContainsString($sc->get_persona()['name'], $rendered,
                    "Rendered prompt should contain persona name for state '{$statekey}' in '{$s}'");
            }
        }
    }

    /**
     * Test prompt_template via from_array() with a custom JSON structure.
     */
    public function test_prompt_template_from_array() {
        $data = [
            'scenario_id' => 'custom_test',
            'persona' => [
                'name' => 'Test Persona',
                'child_preferred_pronoun' => 'they/them',
                'backstory' => 'Test backstory for unit test.',
                'initial_mood' => 'calm',
                'communication_style' => 'Polite and cooperative.',
            ],
            'learning_objectives' => ['active_listening'],
            'states' => [
                'START' => [
                    'bot_prompt' => 'Hello, I need help.',
                    'expected_criteria' => [
                        'validation_type' => 'empathy_check',
                        'pass_route' => 'RESOLUTION',
                        'fail_route' => 'FAIL_STATE',
                    ],
                ],
                'RESOLUTION' => [
                    'bot_prompt' => 'Thank you.',
                    'expected_criteria' => null,
                ],
                'FAIL_STATE' => [
                    'bot_prompt' => 'Goodbye.',
                    'expected_criteria' => null,
                ],
            ],
            'prompt_template' => 'You are {{persona_name}}. Mood: {{initial_mood}}. State: {{statekey}}. Prompt: {{stateprompt}}',
        ];

        $sc = \local_aacuracore\scenario\scenario_loader::from_array($data);

        // Verify prompt_template is loaded.
        $this->assertNotNull($sc->get_prompt_template());
        $this->assertStringContainsString('{{persona_name}}', $sc->get_prompt_template());

        // Render and verify substitutions.
        $rendered = \local_aacuracore\prompt_renderer::render($sc->get_prompt_template(), $sc, 'START');
        $this->assertStringContainsString('Test Persona', $rendered);
        $this->assertStringContainsString('calm', $rendered);
        $this->assertStringContainsString('START', $rendered);
        $this->assertStringContainsString('Hello, I need help.', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }

    /**
     * Test that absent prompt_template falls back gracefully.
     */
    public function test_prompt_template_absent() {
        $data = [
            'scenario_id' => 'no_template',
            'persona' => [
                'name' => 'No Template Persona',
                'child_preferred_pronoun' => 'he/him',
                'backstory' => 'No template test.',
                'initial_mood' => 'neutral',
                'communication_style' => 'Direct.',
            ],
            'learning_objectives' => [],
            'states' => [
                'START' => [
                    'bot_prompt' => 'Testing.',
                    'expected_criteria' => null,
                ],
            ],
            // No prompt_template key.
        ];

        $sc = \local_aacuracore\scenario\scenario_loader::from_array($data);
        $this->assertNull($sc->get_prompt_template(),
            'prompt_template should be null when absent from JSON');

        // Rendering with null template should fall back gracefully in the strategy code.
        // The render() method itself requires a string; the strategy classes handle
        // the null → DEFAULT_TEMPLATE fallback before calling render().
    }
}
