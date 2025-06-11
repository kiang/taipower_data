<?php
$basePath = dirname(__DIR__);

// Emergency generators to monitor (using partial matching patterns)
$emergencyGeneratorPatterns = [
    '核二Gas1',
    '核二Gas2', 
    '核三Gas1',
    '核三Gas2',
    '台中Gas1&amp;2',
    '台中Gas3&amp;4',
    '興達#1',  // Will match 興達#1(註15), 興達#1(註12), etc.
    '興達#2',  // Will match 興達#2(註15), 興達#2(註12), etc.
    '興達#3',  // Will match 興達#3(註12), 興達#3(註15), etc.
    '興達#4',  // Will match 興達#4(註12), 興達#4(註15), etc.
    '大林#5'
];

/**
 * Check if a generator name matches any emergency generator pattern
 * @param string $generatorName The generator name to check
 * @param array $patterns Array of patterns to match against
 * @return string|false Returns the matched pattern or false if no match
 */
function matchesEmergencyGenerator($generatorName, $patterns) {
    foreach ($patterns as $pattern) {
        // For 興達 generators, use partial matching to handle dynamic notation
        if (strpos($pattern, '興達#') === 0) {
            if (strpos($generatorName, $pattern) === 0) {
                return $pattern;
            }
        } else {
            // For other generators, use exact matching
            if ($generatorName === $pattern) {
                return $pattern;
            }
        }
    }
    return false;
}

/**
 * Check if any emergency generators have non-zero/non-empty values
 * @param array $json The generator data JSON
 * @param array $emergencyGeneratorPatterns Array of patterns to match against
 * @return array Emergency generators with values
 */
function checkEmergencyGenerators($json, $emergencyGeneratorPatterns) {
    $activeEmergencyGenerators = [];
    
    foreach ($json['aaData'] as $line) {
        $generatorName = trim(strip_tags($line[2]));
        $currentOutput = trim($line[4]);
        
        $matchedPattern = matchesEmergencyGenerator($generatorName, $emergencyGeneratorPatterns);
        if ($matchedPattern) {
            // Check if output is not empty and not "0.0"
            if (!empty($currentOutput) && $currentOutput !== '0.0' && $currentOutput !== '0' && $currentOutput !== '-') {
                $activeEmergencyGenerators[] = [
                    'name' => $generatorName,  // Store actual name (with notation)
                    'pattern' => $matchedPattern,  // Store matched pattern for reference
                    'output' => $currentOutput,
                    'percentage' => trim($line[5]),
                    'status' => trim(strip_tags($line[6])),
                    'timestamp' => $json['']
                ];
            }
        }
    }
    
    return $activeEmergencyGenerators;
}

/**
 * Create backup for emergency generator data
 * @param array $emergencyData Emergency generator data
 * @param string $timestamp Timestamp from the data
 */
function createEmergencyBackup($emergencyData, $timestamp) {
    global $basePath;
    
    if (empty($emergencyData)) {
        return;
    }
    
    $t = strtotime($timestamp . ':00');
    $backupPath = $basePath . '/docs/emergency/' . date('Y/Ymd', $t);
    
    if (!file_exists($backupPath)) {
        mkdir($backupPath, 0755, true);
    }
    
    $backupFile = $backupPath . '/' . date('His', $t) . '.json';
    $backupData = [
        'timestamp' => $timestamp,
        'active_emergency_generators' => $emergencyData,
        'total_count' => count($emergencyData),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Update index file
    $indexFile = $backupPath . '/index.json';
    if (file_exists($indexFile)) {
        $index = json_decode(file_get_contents($indexFile), true);
    } else {
        $index = [];
    }
    
    $index[] = [
        'time' => date('His', $t),
        'generators' => array_column($emergencyData, 'name'),
        'count' => count($emergencyData)
    ];
    
    // Sort by time
    usort($index, function($a, $b) {
        return strcmp($a['time'], $b['time']);
    });
    
    file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "Emergency backup created: {$backupFile}\n";
    echo "Active emergency generators: " . implode(', ', array_column($emergencyData, 'name')) . "\n";
}

/**
 * Cleanup old emergency backup files (keep 90 days)
 */
function cleanupOldEmergencyBackups() {
    global $basePath;
    
    $emergencyPath = $basePath . '/docs/emergency';
    $daysToKeep = 90;
    $cutoff = strtotime("-{$daysToKeep} days");
    
    if (!is_dir($emergencyPath)) {
        return;
    }
    
    $years = glob($emergencyPath . '/*', GLOB_ONLYDIR);
    foreach ($years as $yearDir) {
        $dateDirs = glob($yearDir . '/*', GLOB_ONLYDIR);
        foreach ($dateDirs as $dateDir) {
            $dateBase = basename($dateDir);
            $dateObj = DateTime::createFromFormat('Ymd', $dateBase);
            if ($dateObj === false) continue;
            
            if ($dateObj->getTimestamp() < $cutoff) {
                $it = new RecursiveDirectoryIterator($dateDir, RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                foreach($files as $file) {
                    if ($file->isDir()) {
                        rmdir($file->getRealPath());
                    } else {
                        unlink($file->getRealPath());
                    }
                }
                rmdir($dateDir);
                echo "Cleaned up old emergency backup: {$dateDir}\n";
            }
        }
        
        // Remove empty year directories
        if (count(glob($yearDir . '/*')) === 0) {
            rmdir($yearDir);
        }
    }
}

// Main execution
$genaryFile = $basePath . '/docs/genary.json';

if (!file_exists($genaryFile)) {
    echo "Error: genary.json not found\n";
    exit(1);
}

$json = json_decode(file_get_contents($genaryFile), true);
if (!$json) {
    echo "Error: Invalid JSON data\n";
    exit(1);
}

$emergencyData = checkEmergencyGenerators($json, $emergencyGeneratorPatterns);

if (!empty($emergencyData)) {
    createEmergencyBackup($emergencyData, $json['']);
} else {
    echo "No emergency generators active at " . $json[''] . "\n";
}

// Cleanup old backups
cleanupOldEmergencyBackups();