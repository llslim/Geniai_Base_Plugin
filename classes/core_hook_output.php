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

/**
 * Class injector
 *
 * @package   local_aacura_core
 * @copyright 2024 Eduardo Kraus {@link http://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aacura_core;

defined('MOODLE_INTERNAL') || die;
require_once(__DIR__ . "/../lib.php");

use context_system;
use local_aacura_core\local\util\release;

/**
 * Class core_hook_output
 *
 * @package local_aacura_core
 */
class core_hook_output {
    /**
     * Function before_footer_html_generation
     *
     */
    public static function before_footer_html_generation() {
        self::local_aacura_core_addchat();
        self::local_aacura_core_addh5p();
    }

    /**
     * Function local_aacura_core_addchat
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private static function local_aacura_core_addchat() {
        global $DB, $OUTPUT, $PAGE, $COURSE, $USER, $SITE;

        if (get_config("local_aacura_core", "mode") == "none") {
            return;
        }

        if (!isset(get_config("local_aacura_core", "apikey")[5])) {
            return;
        } else if ($USER->id < 2) {
            return;
        }

        if (strpos($_SERVER["REQUEST_URI"], "mod/geniai/") >= 1) {
            return;
        }

        if (!$PAGE->get_popup_notification_allowed()) {
            return;
        }

        $context = context_system::instance();
        $capability = has_capability("local/aacura_core:manage", $context);
        if (!$capability) {
            $modules = explode(",", get_config("local_aacura_core", "modules"));
            foreach ($modules as $module) {
                if (strpos($_SERVER["REQUEST_URI"], "mod/{$module}/") >= 1) {
                    return;
                }
            }
        }

        $data = [
            "message_01" => get_string("message_01", "local_aacura_core", fullname($USER)),
            "manage_capability" => $capability,
            "geniainame" => get_config("local_aacura_core", "geniainame"),
            "mode" => get_config("local_aacura_core", "mode"),
            "talk_geniai" => get_string("talk_geniai", "local_aacura_core", get_config("local_aacura_core", "geniainame")),
        ];

        $geniainame = get_config("local_aacura_core", "geniainame");
        if (get_config("local_aacura_core", "mode") == "assistant") {
            if ($COURSE->id) {
                $course = $DB->get_record("course", ["id" => $COURSE->id]);
                $data["message_02"] = get_string(
                    "message_02_course",
                    "local_aacura_core",
                    ["geniainame" => $geniainame, "moodlename" => $SITE->fullname, "coursename" => $course->fullname]
                );
            } else {
                $data["message_02"] = get_string("message_02_home", "local_aacura_core", $geniainame);
            }
        } else {
            $data["message_02"] = get_string("message_02_geniai", "local_aacura_core", $geniainame);
        }

        echo $OUTPUT->render_from_template("local_aacura_core/chat", $data);
        $PAGE->requires->js_call_amd("local_aacura_core/chat", "init", [$COURSE->id, release::version()]);
    }

    /**
     * Function local_aacura_core_addh5p
     */
    private static function local_aacura_core_addh5p() {
        global $PAGE, $COURSE;

        if (isset($_SERVER["REQUEST_URI"])) {
            if (
                strpos($_SERVER["REQUEST_URI"], "contentbank/") ||
                strpos($_SERVER["REQUEST_URI"], "course/modedit.php")
            ) {
                $contextid = \context_course::instance($COURSE->id)->id;
                $PAGE->requires->strings_for_js(["h5p-manager", "h5p-manager-scorm"], "local_aacura_core");
                $PAGE->requires->js_call_amd("local_aacura_core/h5p", "init", [$contextid]);
            }
        }
    }
}
