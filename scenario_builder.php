<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once("../../config.php");

require_login();
require_capability("local/aacura_core:manage", context_system::instance());

global $DB, $USER, $OUTPUT, $PAGE;

// Handle JSON File Upload Action
$action = optional_param('action', '', PARAM_ALPHA);
$message = '';
$messagetype = 'info';

if ($action === 'upload' && confirm_sesskey()) {
    if (!empty($_FILES['jsonfile']['tmp_name'])) {
        $jsoncontent = file_get_contents($_FILES['jsonfile']['tmp_name']);
        $data = json_decode($jsoncontent, true);

        if (json_last_error() === JSON_ERROR_NONE && !empty($data['scenario_id']) && !empty($data['persona']['name'])) {
            $scenariocode = clean_param($data['scenario_id'], PARAM_ALPHANUMEXT);
            $name = clean_param($data['persona']['name'], PARAM_TEXT);
            $description = clean_param($data['persona']['backstory'] ?? '', PARAM_TEXT);

            $record = $DB->get_record('local_aacura_core_custom_scenarios', ['scenariocode' => $scenariocode]);
            if ($record) {
                $record->name = $name;
                $record->description = $description;
                $record->json_data = $jsoncontent;
                $record->userid = $USER->id;
                $record->timemodified = time();
                $DB->update_record('local_aacura_core_custom_scenarios', $record);
                $message = "Scenario profile '{$name}' ({$scenariocode}) updated successfully!";
            } else {
                $record = new \stdClass();
                $record->scenariocode = $scenariocode;
                $record->name = $name;
                $record->description = $description;
                $record->json_data = $jsoncontent;
                $record->userid = $USER->id;
                $record->timecreated = time();
                $record->timemodified = time();
                $DB->insert_record('local_aacura_core_custom_scenarios', $record);
                $message = "New scenario profile '{$name}' ({$scenariocode}) registered successfully!";
            }
            $messagetype = 'success';
        } else {
            $message = 'Error: The uploaded file is not a valid AACURA Scenario JSON profile.';
            $messagetype = 'error';
        }
    }
}

// Handle Persona Deletion Action
if ($action === 'delete' && confirm_sesskey()) {
    $deletecode = required_param('scenariocode', PARAM_ALPHANUMEXT);
    $record = $DB->get_record('local_aacura_core_custom_scenarios', ['scenariocode' => $deletecode]);
    if ($record) {
        $DB->delete_records('local_aacura_core_custom_scenarios', ['id' => $record->id]);
        $message = "Custom scenario profile '{$record->name}' ({$deletecode}) was deleted successfully.";
        $messagetype = 'success';
    } else {
        $message = "Error: Scenario profile '{$deletecode}' not found.";
        $messagetype = 'error';
    }
}

// Fetch all registered custom scenarios from database
$customscenarios = $DB->get_records('local_aacura_core_custom_scenarios', null, 'name ASC');

$PAGE->set_context(context_system::instance());
$PAGE->set_url("/local/aacura_core/scenario_builder.php");
$PAGE->set_title(get_string("modulename", "local_aacura_core") . " - Scenario Builder & Management");
$PAGE->set_heading(get_string("modulename", "local_aacura_core") . " - Scenario Builder & Management");

echo $OUTPUT->header();

if (!empty($message)) {
    $alertclass = ($messagetype === 'success') ? 'alert-success' : 'alert-danger';
    echo '<div class="alert ' . $alertclass . ' alert-dismissible fade show" role="alert">' . s($message) . '</div>';
}

require_once(__DIR__ . "/scenario_builder.html");
echo $OUTPUT->footer();
