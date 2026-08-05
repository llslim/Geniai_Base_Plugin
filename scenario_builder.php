<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once("../../config.php");

require_login();
require_capability("local/geniai:manage", context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url("/local/geniai/scenario_builder.php");
$PAGE->set_title(get_string("modulename", "local_geniai") . " - Scenario Builder");
$PAGE->set_heading(get_string("modulename", "local_geniai") . " - Scenario Builder");

echo $OUTPUT->header();
require_once(__DIR__ . "/scenario_builder.html");
echo $OUTPUT->footer();
