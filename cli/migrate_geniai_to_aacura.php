<?php
// This file is part of AACURA Core Engine for Moodle - http://moodle.org/
//
// CLI Migration Script: Upgrades legacy local_geniai and mod_geniai installations
// to local_aacura_core and mod_aacura_chat.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

cli_heading('AACURA Migration Utility: geniai -> aacura');

global $DB, $CFG;

$dbman = $DB->get_manager();

// 1. Migrate local_geniai tables -> local_aacura_core tables.
$localtables = [
    'local_geniai_sessions' => 'local_aacura_core_sessions',
    'local_geniai_scenarios' => 'local_aacura_core_scenarios',
    'local_geniai_evaluations' => 'local_aacura_core_evaluations',
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

// 2. Migrate activity table mod_geniai -> mod_aacura_chat.
if ($dbman->table_exists('geniai')) {
    if (!$dbman->table_exists('aacura_chat')) {
        cli_writeln("Renaming activity table 'geniai' -> 'aacura_chat'...");
        $table = new xmldb_table('geniai');
        $dbman->rename_table($table, 'aacura_chat');
    } else {
        cli_writeln("Target activity table 'aacura_chat' already exists.");
    }
}

// 3. Update Moodle modules registry for activity plugin.
$module = $DB->get_record('modules', ['name' => 'geniai']);
if ($module) {
    cli_writeln("Updating Moodle module registry from 'geniai' -> 'aacura_chat'...");
    $DB->set_field('modules', 'name', 'aacura_chat', ['id' => $module->id]);
}

// 4. Update plugin settings in mdl_config_plugins.
cli_writeln("Migrating plugin settings in config_plugins...");
$DB->execute("UPDATE {config_plugins} SET plugin = 'local_aacura_core' WHERE plugin = 'local_geniai'");
$DB->execute("UPDATE {config_plugins} SET plugin = 'mod_aacura_chat' WHERE plugin = 'mod_geniai'");

// 5. Purge site caches.
cli_writeln("Purging Moodle site caches...");
purge_all_caches();

cli_heading("Migration completed successfully!");
