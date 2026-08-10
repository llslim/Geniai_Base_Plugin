<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

global $DB, $CFG;

echo "============================================================\n";
echo "Creating User 'Steve' & Enrolling in SLP_CM_100\n";
echo "============================================================\n";

$steve = new stdClass();
$steve->username = 'steve';
$steve->password = 'Steve_secure_pass2026!';
$steve->firstname = 'Steve';
$steve->lastname = 'Student';
$steve->email = 'llslim+steve@gmail.com';
$steve->confirmed = 1;
$steve->mnethostid = $CFG->mnet_localhost_id;

$existing = $DB->get_record('user', ['username' => $steve->username]);
if ($existing) {
    $steve->id = $existing->id;
    echo "User Steve already exists (ID: {$steve->id})\n";
} else {
    $steve->id = user_create_user($steve, true, false);
    echo "Created user Steve (ID: {$steve->id})\n";
}

// Find course 'SLP_CM_100' or 'slp_cm_100'
$course = $DB->get_record_select('course', 'LOWER(shortname) = ?', ['slp_cm_100']);
if (!$course) {
    die("Error: Target course 'slp_cm_100' not found in database!\n");
}
echo "Found course: {$course->fullname} (ID: {$course->id})\n";

// Enroll Steve via Manual Enrollment plugin
$enrol = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
if (!$instance) {
    die("Error: Manual enrollment instance not found in course!\n");
}

$enrol->enrol_user($instance, $steve->id, 5); // Student role (ID: 5)
echo "Success: Enrolled Steve as a student in course.\n";
