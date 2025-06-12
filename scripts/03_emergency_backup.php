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
    
    // Update monthly index
    updateMonthlyEmergencyIndex($t);
    
    // Update main monthly index
    updateMainMonthlyIndex();
    
    echo "Emergency backup created: {$backupFile}\n";
    echo "Active emergency generators: " . implode(', ', array_column($emergencyData, 'name')) . "\n";
}

/**
 * Update monthly index when new emergency data is added
 * @param int $timestamp Unix timestamp of the emergency event
 */
function updateMonthlyEmergencyIndex($timestamp) {
    global $basePath;
    
    $date = date('Ymd', $timestamp);
    $year = date('Y', $timestamp);
    $yearMonth = date('Ym', $timestamp);
    
    $emergencyPath = $basePath . '/docs/emergency';
    $yearDir = $emergencyPath . '/' . $year;
    $dateDir = $yearDir . '/' . $date;
    
    // Check if the date directory has emergency data
    if (!is_dir($dateDir)) {
        return;
    }
    
    $emergencyFiles = glob($dateDir . '/*.json');
    $hasEmergencyData = false;
    
    foreach ($emergencyFiles as $file) {
        if (basename($file) !== 'index.json') {
            $hasEmergencyData = true;
            break;
        }
    }
    
    if (!$hasEmergencyData) {
        return;
    }
    
    // Read or create monthly index
    $monthlyIndexFile = $yearDir . '/' . $yearMonth . '.json';
    $monthlyIndex = [];
    
    if (file_exists($monthlyIndexFile)) {
        $monthlyData = json_decode(file_get_contents($monthlyIndexFile), true);
        if ($monthlyData && isset($monthlyData['dates'])) {
            $monthlyIndex = $monthlyData['dates'];
        }
    }
    
    // Check if this date already exists in monthly index
    $dateExists = false;
    foreach ($monthlyIndex as $dayData) {
        if ($dayData['date'] === $date) {
            $dateExists = true;
            break;
        }
    }
    
    if (!$dateExists) {
        // Get index.json data for this date
        $indexFile = $dateDir . '/index.json';
        $dayData = [
            'date' => $date,
            'formatted_date' => date('Y-m-d', $timestamp)
        ];
        
        if (file_exists($indexFile)) {
            $indexData = json_decode(file_get_contents($indexFile), true);
            if ($indexData && is_array($indexData)) {
                $dayData['events'] = count($indexData);
                $dayData['times'] = array_column($indexData, 'time');
                $dayData['total_generators'] = array_sum(array_column($indexData, 'count'));
                
                // Get unique generator names
                $allGenerators = [];
                foreach ($indexData as $event) {
                    if (isset($event['generators']) && is_array($event['generators'])) {
                        $allGenerators = array_merge($allGenerators, $event['generators']);
                    }
                }
                $dayData['unique_generators'] = array_unique($allGenerators);
            }
        } else {
            $dayData['events'] = count($emergencyFiles) - 1; // Exclude index.json
        }
        
        $monthlyIndex[] = $dayData;
        
        // Sort by date
        usort($monthlyIndex, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        // Write updated monthly index
        $monthlyIndexData = [
            'year_month' => $yearMonth,
            'year' => substr($yearMonth, 0, 4),
            'month' => substr($yearMonth, 4, 2),
            'total_days' => count($monthlyIndex),
            'dates' => $monthlyIndex,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($monthlyIndexFile, json_encode($monthlyIndexData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

/**
 * Update the main monthly index file that summarizes all months
 */
function updateMainMonthlyIndex() {
    global $basePath;
    
    $emergencyPath = $basePath . '/docs/emergency';
    $mainIndexFile = $emergencyPath . '/monthly_index.json';
    
    $months = [];
    
    // Scan for year directories
    $yearDirs = glob($emergencyPath . '/[0-9][0-9][0-9][0-9]');
    
    foreach ($yearDirs as $yearDir) {
        $year = basename($yearDir);
        
        // Find all monthly JSON files
        $monthlyFiles = glob($yearDir . '/[0-9][0-9][0-9][0-9][0-9][0-9].json');
        
        foreach ($monthlyFiles as $monthlyFile) {
            $fileName = basename($monthlyFile, '.json');
            
            if (preg_match('/^(\d{4})(\d{2})$/', $fileName, $matches)) {
                $year = $matches[1];
                $month = $matches[2];
                
                // Read the monthly file to get total_days
                $monthlyData = json_decode(file_get_contents($monthlyFile), true);
                $totalDays = 0;
                
                if ($monthlyData && isset($monthlyData['total_days'])) {
                    $totalDays = $monthlyData['total_days'];
                } elseif ($monthlyData && isset($monthlyData['dates'])) {
                    $totalDays = count($monthlyData['dates']);
                }
                
                $months[] = [
                    'year_month' => $fileName,
                    'year' => $year,
                    'month' => $month,
                    'total_days' => $totalDays,
                    'file' => 'docs/emergency/' . $year . '/' . $fileName . '.json'
                ];
            }
        }
    }
    
    // Sort by year_month
    usort($months, function($a, $b) {
        return strcmp($a['year_month'], $b['year_month']);
    });
    
    $mainIndex = [
        'total_months' => count($months),
        'months' => $months,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($mainIndexFile, json_encode($mainIndex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "Main monthly index updated: {$mainIndexFile}\n";
}

/**
 * Emergency backup cleanup is disabled - keeping all data forever
 * This function is retained for potential future use but does nothing
 */
function cleanupOldEmergencyBackups() {
    // Emergency backup data is now kept permanently
    // No cleanup is performed to preserve historical emergency records
    echo "Emergency backup cleanup disabled - keeping all data permanently\n";
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