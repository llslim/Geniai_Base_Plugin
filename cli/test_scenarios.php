<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
global $DB, $USER;

// Ensure we run as the debug user (ID 3 or username 'debug')
$debuguser = $DB->get_record('user', ['username' => 'debug']);
if (!$debuguser) {
    die("Error: 'debug' user not found. Please run the user creation script first.\n");
}
$USER = $debuguser;

echo "============================================================\n";
echo "AACURA Chatbot Scenario Graph Crawler & Route Verification\n";
echo "Running as user: {$USER->username} (ID: {$USER->id})\n";
echo "============================================================\n\n";

$scenarios = ['anna', 'brianna', 'cathy', 'mary'];

/**
 * Recursively crawl conversation routes from a starting state.
 *
 * @param string $statekey The current state key
 * @param \local_aacuracore\scenario\scenario_definition $scenario Scenario object
 * @param array $visited States visited on the current path (prevents infinite loops)
 * @param array $path Accumulated path of states
 * @return array List of all found routes
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
