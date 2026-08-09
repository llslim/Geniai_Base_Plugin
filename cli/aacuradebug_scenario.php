<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
global $DB, $USER, $CFG;

// Ensure we run as the debug user (ID 3 or username 'debug')
$debuguser = $DB->get_record('user', ['username' => 'debug']);
if (!$debuguser) {
    die("Error: 'debug' user not found. Please run the user creation script first.\n");
}
$USER = $debuguser;

// Parse command line options
$shortopts = "";
$longopts = [
    "scenario:",      // e.g. --scenario=anna, --scenario=all
    "enable-debug",   // Turns developer debugging ON
    "disable-debug",  // Turns developer debugging OFF
    "check-configs",  // Verifies database and AI provider settings
    "phpunit",        // Triggers PHPUnit tests
    "all"             // Runs everything (enable-debug, check-configs, all scenarios, phpunit)
];
$options = getopt($shortopts, $longopts);

echo "============================================================\n";
echo "AACURA Chatbot Scenario Graph Crawler & Route Verification\n";
echo "Running as user: {$USER->username} (ID: {$USER->id})\n";
echo "============================================================\n\n";

/**
 * Recursively crawl conversation routes from a starting state.
 */
function crawl_conversation_routes($statekey, $scenario, $visited = [], $path = []) {
    $node = $scenario->get_state_node($statekey);
    $path[] = $statekey;
    
    if (!$node) {
        return [[
            'path' => $path,
            'status' => 'FAIL',
            'detail' => "Error: State '{$statekey}' is referenced but not defined."
        ]];
    }

    $prompt = $node['bot_prompt'] ?? '';
    $criteria = $node['expected_criteria'] ?? null;

    if (empty($criteria)) {
        return [[
            'path' => $path,
            'status' => 'TERMINAL',
            'detail' => "Terminal state reached. Bot prompt: \"{$prompt}\""
        ]];
    }

    $visited[$statekey] = true;
    $routes = [];

    // Analyze transitions
    $validation = $criteria['validation_type'] ?? 'unknown_validation';
    $passroute = $criteria['pass_route'] ?? null;
    $failroute = $criteria['fail_route'] ?? null;

    if ($passroute) {
        if (isset($visited[$passroute])) {
            $routes[] = [
                'path' => array_merge($path, [$passroute]),
                'status' => 'LOOP',
                'detail' => "Loop back to '{$passroute}' via PASS route ({$validation})"
            ];
        } else {
            $routes = array_merge($routes, crawl_conversation_routes($passroute, $scenario, $visited, $path));
        }
    }

    if ($failroute) {
        if (isset($visited[$failroute])) {
            $routes[] = [
                'path' => array_merge($path, [$failroute]),
                'status' => 'LOOP',
                'detail' => "Loop back to '{$failroute}' via FAIL route ({$validation})"
            ];
        } else {
            $routes = array_merge($routes, crawl_conversation_routes($failroute, $scenario, $visited, $path));
        }
    }

    return $routes;
}

/**
 * Check DB and AI provider configurations.
 */
function check_ai_configs() {
    global $DB;
    echo "=== [CONFIG CHECK] AI Provider & Placements ===\n";
    if (!class_exists('\\core_ai\\manager')) {
        echo " [FAIL] core_ai\\manager class not found. AI subsystem not supported.\n\n";
        return;
    }

    $manager = new \core_ai\manager($DB);
    $providers = $manager->get_provider_records();
    $enabled = array_filter($providers, fn($p) => !empty($p->enabled));
    echo "  Registered Providers: " . count($providers) . " (Enabled: " . count($enabled) . ")\n";

    foreach ($enabled as $p) {
        $inst = $manager->get_provider_instances(['id' => $p->id]);
        $inst = reset($inst);
        if ($inst) {
            echo "  Provider: {$inst->name} ({$p->provider})\n";
            if (empty($inst->actionconfig)) {
                echo "    [WARNING] No actions configured for this provider.\n";
            } else {
                foreach ($inst->actionconfig as $action => $conf) {
                    $status = ($conf['enabled'] ?? false) ? 'ENABLED' : 'DISABLED';
                    $model = $conf['settings']['model'] ?? 'none';
                    echo "    * Action: {$action} -> {$status} (Model: {$model})\n";
                }
            }
        }
    }
    echo "================================================\n\n";
}

/**
 * Toggle developer debugging settings.
 */
function toggle_debugging($enable) {
    if ($enable) {
        set_config('debug', '32767');
        set_config('debugdisplay', '1');
        echo ">>> Moodle debugging enabled at DEVELOPER level (debug=32767, debugdisplay=1).\n\n";
    } else {
        set_config('debug', '0');
        set_config('debugdisplay', '0');
        echo ">>> Moodle debugging disabled (debug=0, debugdisplay=0).\n\n";
    }
}

/**
 * Run PHPUnit unit tests.
 */
function run_phpunit() {
    global $CFG;
    echo "=== [PHPUNIT] Running Unit Tests ===\n";
    $phpunit = $CFG->dirroot . '/vendor/bin/phpunit';
    if (file_exists($phpunit)) {
        $cmd = "cd {$CFG->dirroot} && vendor/bin/phpunit local/aacuracore/tests/scenarios_test.php 2>&1";
        echo "Running command: {$cmd}\n";
        passthru($cmd);
    } else {
        echo " [FAIL] PHPUnit executable not found at: {$phpunit}\n";
        echo "        Ensure dev dependencies are installed.\n";
    }
    echo "====================================\n\n";
}

// Determine what steps to execute
$runall = isset($options['all']);
$runconfigs = $runall || isset($options['check-configs']);
$runphpunit = $runall || isset($options['phpunit']);

// Handle debugging toggles
if (isset($options['enable-debug'])) {
    toggle_debugging(true);
}
if (isset($options['disable-debug'])) {
    toggle_debugging(false);
}

// Run config verification
if ($runconfigs) {
    check_ai_configs();
}

// Crawl Scenarios
$scenariocode = $options['scenario'] ?? ($runall ? 'all' : null);
if ($scenariocode || (!$runconfigs && !$runphpunit && !isset($options['enable-debug']) && !isset($options['disable-debug']))) {
    // If no specific options chosen, default to running all scenarios
    if (empty($scenariocode)) {
        $scenariocode = 'all';
    }

    $scenarios = ($scenariocode === 'all') ? ['anna', 'brianna', 'cathy', 'mary'] : [$scenariocode];
    
    foreach ($scenarios as $s) {
        try {
            $sc = \local_aacuracore\scenario\scenario_loader::load($s, 0);
            $persona = $sc->get_persona();
            
            echo "------------------------------------------------------------\n";
            echo "Scenario ID: " . strtoupper($s) . "\n";
            echo "Persona Name: " . ($persona['name'] ?? 'Unknown') . "\n";
            echo "Initial Mood: " . ($persona['initial_mood'] ?? 'Unknown') . "\n";
            echo "Communication Style: " . ($persona['communication_style'] ?? 'None') . "\n";
            echo "Learning Objectives: " . implode(', ', $sc->get_learning_objectives()) . "\n";
            echo "------------------------------------------------------------\n";
            
            $allroutes = crawl_conversation_routes('START', $sc);
            
            echo "Discovered " . count($allroutes) . " distinct conversation routes:\n\n";
            foreach ($allroutes as $idx => $route) {
                $pathstr = implode(' -> ', $route['path']);
                echo "  Route #" . ($idx + 1) . " [{$route['status']}]:\n";
                echo "    Path:   {$pathstr}\n";
                echo "    Result: {$route['detail']}\n\n";
            }
            
        } catch (\Exception $e) {
            echo " [FAIL] Could not load scenario '{$s}': " . $e->getMessage() . "\n\n";
        }
    }
}

// Run PHPUnit tests
if ($runphpunit) {
    run_phpunit();
}
