<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

global $DB;

echo "============================================================\n";
echo "Simulating Steve's Chat Session (FAIL PATH) and Verifying Gradebook\n";
echo "============================================================\n";

// 1. Load User Steve
$steve = $DB->get_record('user', ['username' => 'steve']);
if (!$steve) {
    die("Error: User Steve not found.\n");
}
echo "Loaded student Steve (ID: {$steve->id})\n";

// 2. Load Course SLP_CM_100 (ID: 2)
$course = $DB->get_record_select('course', 'LOWER(shortname) = ?', ['slp_cm_100']);
if (!$course) {
    die("Error: Course slp_cm_100 not found!\n");
}
echo "Loaded course: {$course->fullname} (ID: {$course->id})\n";

// 3. Find aacurachat activity instance
$aacurachat = $DB->get_record('aacurachat', ['course' => $course->id]);
if (!$aacurachat) {
    die("Error: aacurachat activity instance not found in this course!\n");
}
$cm = get_coursemodule_from_instance('aacurachat', $aacurachat->id);
if (!$cm) {
    die("Error: Course module not found for aacurachat instance!\n");
}
echo "Loaded aacurachat activity: '{$aacurachat->name}' (Instance ID: {$aacurachat->id}, CMID: {$cm->id}, Scenario: {$aacurachat->scenariocode})\n";

// 4. Instantiate bot_engine
$engine = new \local_aacuracore\bot_engine($steve->id, $course->id, $cm->id, $aacurachat->scenariocode);

// 5. Reset session for a clean test run
echo "Resetting Steve's chat session...\n";
$engine->reset_session();

// 6. Send Turn 1: Should FAIL empathy_check (transitions START -> ESCALATION)
echo "Sending Turn 1: 'Whatever. This is what we have.'\n";
$reply1 = $engine->process_user_turn("Whatever. This is what we have.");
echo "Bot Reply 1:\n-------------------------\n{$reply1}\n-------------------------\n\n";

// 7. Send Turn 2: Should FAIL de_escalation_check (transitions ESCALATION -> FAIL_STATE)
echo "Sending Turn 2: 'I don't care, talk to the principal.'\n";
$reply2 = $engine->process_user_turn("I don't care, talk to the principal.");
echo "Bot Reply 2 (Terminal):\n-------------------------\n{$reply2}\n-------------------------\n\n";

// 8. Query Gradebook to verify saving
echo "Querying Gradebook to check Steve's saved score...\n";
$gradeitem = $DB->get_record('grade_items', ['courseid' => $course->id, 'iteminstance' => $aacurachat->id, 'itemmodule' => 'aacurachat']);

if (!$gradeitem) {
    echo "WARNING: No grade item found for this activity!\n";
} else {
    echo "Found Grade Item ID: {$gradeitem->id} (Module: {$gradeitem->itemmodule})\n";
    $grade = $DB->get_record('grade_grades', ['itemid' => $gradeitem->id, 'userid' => $steve->id]);
    if ($grade) {
        echo "SUCCESS! Steve's grade is saved in Gradebook.\n";
        echo "Stated Raw Grade: " . ($grade->finalgrade !== null ? $grade->finalgrade : 'NULL') . "\n";
        echo "Grade Date Modified: " . date('Y-m-d H:i:s', $grade->timemodified) . "\n";
    } else {
        echo "FAILED: No grade record found for Steve under this grade item!\n";
    }
}
