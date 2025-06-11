<?php
$basePath = dirname(__DIR__);

/**
 * Display recent emergency generator activations
 */
function displayEmergencyLogs($daysBack = 7) {
    global $basePath;
    
    $emergencyPath = $basePath . '/docs/emergency';
    
    if (!is_dir($emergencyPath)) {
        echo "No emergency logs found.\n";
        return;
    }
    
    $cutoff = strtotime("-{$daysBack} days");
    $logs = [];
    
    // Scan for emergency log files
    $years = glob($emergencyPath . '/*', GLOB_ONLYDIR);
    foreach ($years as $yearDir) {
        $dateDirs = glob($yearDir . '/*', GLOB_ONLYDIR);
        foreach ($dateDirs as $dateDir) {
            $dateBase = basename($dateDir);
            $dateObj = DateTime::createFromFormat('Ymd', $dateBase);
            if ($dateObj === false) continue;
            
            if ($dateObj->getTimestamp() >= $cutoff) {
                $jsonFiles = glob($dateDir . '/*.json');
                foreach ($jsonFiles as $jsonFile) {
                    if (basename($jsonFile) !== 'index.json') {
                        $data = json_decode(file_get_contents($jsonFile), true);
                        if ($data) {
                            $logs[] = $data;
                        }
                    }
                }
            }
        }
    }
    
    // Sort by timestamp
    usort($logs, function($a, $b) {
        return strcmp($a['timestamp'], $b['timestamp']);
    });
    
    if (empty($logs)) {
        echo "No emergency activations found in the last {$daysBack} days.\n";
        return;
    }
    
    echo "Emergency Generator Activations (Last {$daysBack} days):\n";
    echo str_repeat("=", 60) . "\n";
    
    foreach ($logs as $log) {
        echo "Time: {$log['timestamp']}\n";
        echo "Active Generators ({$log['total_count']}):\n";
        foreach ($log['active_emergency_generators'] as $gen) {
            echo "  - {$gen['name']}: {$gen['output']} MW ({$gen['percentage']})\n";
            if (!empty($gen['status'])) {
                echo "    Status: {$gen['status']}\n";
            }
        }
        echo str_repeat("-", 40) . "\n";
    }
}

// Command line arguments
$daysBack = isset($argv[1]) ? (int)$argv[1] : 7;

displayEmergencyLogs($daysBack);