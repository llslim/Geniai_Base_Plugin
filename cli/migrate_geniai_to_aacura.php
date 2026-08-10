<?php
// This file is part of AACURA Core Engine for Moodle - http://moodle.org/
//
// CLI Migration Script: Upgrades legacy local_geniai and mod_geniai installations
// to local_aacuracore and mod_aacurachat.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

cli_heading('AACURA Migration Utility: geniai -> aacura');

global $DB, $CFG;

$dbman = $DB->get_manager();

// 1. Migrate local_geniai tables -> local_aacuracore tables.
$localtables = [
    'local_geniai_sessions' => 'local_aacuracore_sessions',
    'local_geniai_scenarios' => 'local_aacuracore_scenarios',
    'local_geniai_evaluations' => 'local_aacuracore_evaluations',
];

foreach ($localtables as $oldtable => $newtable) {
    if ($dbman->table_exists($oldtable)) {
        if (!$dbman->table_exists($newtable)) {
            cli_writeln("Renaming table '$oldtable' -> '$newtable'...");
            $table = new xmldb_table($oldtable);
            $dbman->rename_table($table, $newtable);
        } else {
            cli_writeln("Target table '$newtable' already exists. Copying legacy records...");
            $records = $DB->get_records($oldtable);
            foreach ($records as $record) {
                if (!$DB->record_exists($newtable, ['id' => $record->id])) {
                    $DB->insert_record_raw($newtable, (array)$record);
                }
            }
        }
    }
}

// 2. Migrate activity table mod_geniai -> mod_aacurachat.
if ($dbman->table_exists('geniai')) {
    if (!$dbman->table_exists('aacurachat')) {
        cli_writeln("Renaming activity table 'geniai' -> 'aacurachat'...");
        $table = new xmldb_table('geniai');
        $dbman->rename_table($table, 'aacurachat');
    } else {
        cli_writeln("Target activity table 'aacurachat' already exists.");
    }
}

// 3. Update Moodle modules registry for activity plugin.
$module = $DB->get_record('modules', ['name' => 'geniai']);
if ($module) {
    cli_writeln("Updating Moodle module registry from 'geniai' -> 'aacurachat'...");
    $DB->set_field('modules', 'name', 'aacurachat', ['id' => $module->id]);
}

// 4. Update plugin settings in mdl_config_plugins.
cli_writeln("Migrating plugin settings in config_plugins...");
$DB->execute("UPDATE {config_plugins} SET plugin = 'local_aacuracore' WHERE plugin = 'local_geniai'");
$DB->execute("UPDATE {config_plugins} SET plugin = 'mod_aacurachat' WHERE plugin = 'mod_geniai'");

// 5. Purge site caches.
cli_writeln("Purging Moodle site caches...");
purge_all_caches();

cli_heading("Migration completed successfully!");
