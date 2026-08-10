<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

global $DB;

echo "============================================================\n";
echo "Simulating Steve's Chat Session and Verifying Gradebook\n";
echo "============================================================\n";

// 1. Load User Steve
$steve = $DB->get_record('user', ['username' => 'steve']);
if (!$steve) {
    die("Error: User Steve not found. Run create_and_enroll_steve.php first!\n");
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

// 6. Send Turn 1
echo "Sending Turn 1: 'Hello Anna, I hear you. Sarah's communication is very important, and we want to support you.'\n";
$reply1 = $engine->process_user_turn("Hello Anna, I hear you. Sarah's communication is very important, and we want to support you.");
echo "Bot Reply 1:\n-------------------------\n{$reply1}\n-------------------------\n\n";

// 7. Send Turn 2
echo "Sending Turn 2: 'We can use simple symbols on the iPad and show the grandparents how it works.'\n";
$reply2 = $engine->process_user_turn("We can use simple symbols on the iPad and show the grandparents how it works.");
echo "Bot Reply 2 (Terminal):\n-------------------------\n{$reply2}\n-------------------------\n\n";

// 8. Query Gradebook to verify saving
echo "Querying Gradebook to check Steve's saved score...\n";
$gradeitem = $DB->get_record('grade_items', ['courseid' => $course->id, 'iteminstance' => $aacurachat->id, 'itemmodule' => 'aacurachat']);
if (!$gradeitem) {
    // Try historical 'geniai' name just in case the grade item is still mapped to the old module name
    $gradeitem = $DB->get_record('grade_items', ['courseid' => $course->id, 'iteminstance' => $aacurachat->id, 'itemmodule' => 'geniai']);
}

if (!$gradeitem) {
    echo "WARNING: No grade item found for this activity in grade_items table!\n";
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
