<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

global $DB;

echo "=== DIAGNOSTICS: Course & Activities ===\n";

// Get course
$course = $DB->get_record_select('course', 'LOWER(shortname) = ?', ['slp_cm_100']);
if (!$course) {
    die("Course slp_cm_100 not found\n");
}
echo "Course: {$course->fullname} (ID: {$course->id})\n";

// Get modules
$modules = $DB->get_records('modules');
echo "Installed Modules:\n";
foreach ($modules as $m) {
    echo " - {$m->name}\n";
}

// Find course modules in this course
$cms = $DB->get_records('course_modules', ['course' => $course->id]);
echo "Course Modules in course:\n";
foreach ($cms as $cm) {
    $modname = $DB->get_field('modules', 'name', ['id' => $cm->module]);
    $instance = $DB->get_record($modname, ['id' => $cm->instance]);
    $instancename = $instance ? $instance->name : 'Unknown';
    echo " - CMID: {$cm->id}, Mod: {$modname}, Instance ID: {$cm->instance}, Name: '{$instancename}'\n";
}

// Steve's grades if any
$steve = $DB->get_record('user', ['username' => 'steve']);
if ($steve) {
    echo "Steve's User ID: {$steve->id}\n";
    // Check grade items for this course
    $gradeitems = $DB->get_records('grade_items', ['courseid' => $course->id]);
    foreach ($gradeitems as $item) {
        echo "Grade Item: ID={$item->id}, Name='{$item->itemname}', Type='{$item->itemtype}', Module='{$item->itemmodule}'\n";
        $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $steve->id]);
        if ($grade) {
            echo "  -> Steve's Grade: " . ($grade->finalgrade !== null ? $grade->finalgrade : 'NULL') . "\n";
        } else {
            echo "  -> No grade entry for Steve\n";
        }
    }

    // Dump all messages for Steve's active session
    $session = $DB->get_record('local_aacuracore_sessions', ['userid' => $steve->id, 'courseid' => $course->id]);
    if ($session) {
        echo "Steve's Session ID: {$session->id}, State: {$session->current_state}\n";
        $messages = $DB->get_records('local_aacuracore_messages', ['sessionid' => $session->id], 'timestamp ASC, id ASC');
        echo "Logged Messages:\n";
        foreach ($messages as $msg) {
            echo " [{$msg->sender}] {$msg->message_text}\n";
        }
        $analytics = $DB->get_records('local_aacuracore_analytics', ['sessionid' => $session->id], 'id ASC');
        echo "Logged Analytics:\n";
        foreach ($analytics as $an) {
            echo " - Metric: {$an->metric_type}, Value: {$an->metric_value}\n";
        }
    } else {
        echo "No session record found for Steve\n";
    }
} else {
    echo "Steve not found\n";
}
