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

namespace local_aacuracore\scenario;

defined('MOODLE_INTERNAL') || die;

/**
 * Class representing a structured roleplay scenario configuration.
 *
 * @package   local_aacuracore
 * @copyright 2026 Antigravity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scenario_definition {
    /** @var string Unique identifier for this scenario */
    private $id;

    /** @var array Persona metadata (name, backstory, communication_style) */
    private $persona;

    /** @var array List of pedagogical learning objectives */
    private $learningobjectives;

    /** @var array Map of state details */
    private $states;

    /**
     * Constructor.
     *
     * @param string $id
     * @param array $persona
     * @param array $learningobjectives
     * @param array $states
     */
    public function __construct(string $id, array $persona, array $learningobjectives, array $states) {
        $this->id = $id;
        $this->persona = $persona;
        $this->learningobjectives = $learningobjectives;
        $this->states = $states;
    }

    /**
     * Gets the unique identifier for this scenario.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Gets the persona configuration metadata.
     *
     * @return array
     */
    public function get_persona(): array {
        return $this->persona;
    }

    /**
     * Gets the pedagogical learning objectives list.
     *
     * @return array
     */
    public function get_learning_objectives(): array {
        return $this->learningobjectives;
    }

    /**
     * Gets all states configured.
     *
     * @return array
     */
    public function get_states(): array {
        return $this->states;
    }

    /**
     * Gets details for a specific state node.
     *
     * @param string $statekey
     * @return array|null
     */
    public function get_state_node(string $statekey): ?array {
        return $this->states[$statekey] ?? null;
    }

    /**
     * Alias for get_state_node to support state lookups.
     *
     * @param string $statekey
     * @return array|null
     */
    public function get_state(string $statekey): ?array {
        return $this->get_state_node($statekey);
    }
}
