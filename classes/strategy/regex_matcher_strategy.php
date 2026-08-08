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

/**
 * Class regex_matcher_strategy implementing pattern-based dialogue evaluations.
 *
 * @package   local_aacura_core
 * @copyright 2026 Antigravity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class regex_matcher_strategy implements response_strategy {
    /**
     * Pattern-based checks.
     *
     * @param string $input
     * @param string $validationtype
     * @param scenario_definition $scenario
     * @return bool
     */
    public function evaluate_input(string $input, string $validationtype, scenario_definition $scenario): bool {
        $cleaninput = strtolower(trim($input));

        switch ($validationtype) {
            case 'empathy_check':
                // Check for standard empathetic or validating key phrases
                $patterns = ['sorry', 'understand', 'frustrat', 'guilt', 'help', 'support', 'hear you', 'appreciate', 'know it\'s hard'];
                foreach ($patterns as $pattern) {
                    if (strpos($cleaninput, $pattern) !== false) {
                        return true;
                    }
                }
                return false;

            case 'jargon_check':
                // Check if student used common SLP jargon (AAC, SGD, IEP)
                $jargonterms = ['aac', 'sgd', 'iep', 'apraxia', 'speech-generating device'];
                $usedjargon = false;
                foreach ($jargonterms as $term) {
                    if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $cleaninput)) {
                        $usedjargon = true;
                        break;
                    }
                }

                // If they used jargon, check if they offered a definition or explanation
                if ($usedjargon) {
                    $explanationwords = ['stand', 'mean', 'explain', 'device', 'which is', 'is a', 'program', 'refer to'];
                    foreach ($explanationwords as $word) {
                        if (strpos($cleaninput, $word) !== false) {
                            return true; // Used jargon but explained it!
                        }
                    }
                    return false; // Used unexplained jargon
                }
                return true; // No jargon used, passes jargon check!

            case 'de_escalation_check':
                // Check for de-escalating or calming vocabulary
                $calmpatterns = ['calm', 'apologiz', 'please', 'together', 'team', 'listen', 'help', 'collaborat', 'partner'];
                foreach ($calmpatterns as $pattern) {
                    if (strpos($cleaninput, $pattern) !== false) {
                        return true;
                    }
                }
                return false;

            case 'clarification_check':
                // Check for explanation, definition or clear examples
                $claritypatterns = ['example', 'mean', 'explain', 'tell', 'show', 'detail', 'instance'];
                foreach ($claritypatterns as $pattern) {
                    if (strpos($cleaninput, $pattern) !== false) {
                        return true;
                    }
                }
                return false;

            default:
                return true;
        }
    }

    /**
     * Fallback deterministic conversational responses if API is down or not used.
     *
     * @param array $messages
     * @param scenario_definition $scenario
     * @param string $statekey
     * @return string
     */
    public function generate_response(array $messages, scenario_definition $scenario, string $statekey): string {
        $node = $scenario->get_state_node($statekey);
        if ($node && !empty($node['bot_prompt'])) {
            return $node['bot_prompt'];
        }
        return 'I see. Please continue.';
    }
}
