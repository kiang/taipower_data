<?php
$basePath = dirname(__DIR__);

function processTodayEnergyStorageData() {
    global $basePath;
    
    $genaryPath = $basePath . '/docs/genary';
    $today = date('Y-m-d');
    $year = date('Y');
    $dateForPath = date('Ymd');
    
    $todayPath = $genaryPath . '/' . $year . '/' . $dateForPath;
    
    if (!is_dir($todayPath)) {
        echo "No data directory found for today: {$todayPath}\n";
        return false;
    }
    
    $jsonFiles = glob($todayPath . '/*.json');
    $dataFiles = array_filter($jsonFiles, function($file) {
        $filename = basename($file, '.json');
        return $filename !== 'list' && $filename !== 'pump' && preg_match('/^\d{6}$/', $filename);
    });
    
    if (empty($dataFiles)) {
        echo "No data files found for today in: {$todayPath}\n";
        return false;
    }
    
    echo "Processing today's energy storage data: {$today}\n";
    echo "Found " . count($dataFiles) . " files to process\n";
    
    $dayData = ['units' => []];
    $processedCount = 0;
    $errorCount = 0;
    
    foreach ($dataFiles as $jsonFile) {
        $processedCount++;
        
        try {
            $jsonContent = file_get_contents($jsonFile);
            if ($jsonContent === false) {
                $errorCount++;
                continue;
            }
            
            $json = json_decode($jsonContent, true);
            if (!$json || !isset($json['aaData'])) {
                $errorCount++;
                continue;
            }
            
            foreach ($json['aaData'] as $line) {
                if (!is_array($line) || count($line) < 7) {
                    continue;
                }
                
                $category = strip_tags($line[0]);
                $unitName = isset($line[2]) ? trim(strip_tags($line[2])) : '';
                $currentOutput = isset($line[4]) ? trim($line[4]) : '';
                
                if ($unitName === '小計' || empty($unitName)) {
                    continue;
                }
                
                $outputValue = 0.0;
                if (!empty($currentOutput) && $currentOutput !== '-' && is_numeric($currentOutput)) {
                    $outputValue = floatval($currentOutput);
                }
                
                // Only count if value is greater than 0
                if ($outputValue > 0) {
                    if (strpos($category, '儲能(Energy Storage System)') !== false) {
                        if (!isset($dayData['units'][$unitName])) {
                            $dayData['units'][$unitName] = [
                                'energy_storage' => [],
                                'energy_storage_load' => []
                            ];
                        }
                        $dayData['units'][$unitName]['energy_storage'][] = $outputValue;
                    } elseif (strpos($category, '儲能負載(Energy Storage Load)') !== false) {
                        if (!isset($dayData['units'][$unitName])) {
                            $dayData['units'][$unitName] = [
                                'energy_storage' => [],
                                'energy_storage_load' => []
                            ];
                        }
                        $dayData['units'][$unitName]['energy_storage_load'][] = $outputValue;
                    }
                }
            }
            
        } catch (Exception $e) {
            $errorCount++;
            echo "Error processing {$jsonFile}: " . $e->getMessage() . "\n";
        }
    }
    
    if (empty($dayData['units'])) {
        echo "No energy storage data found for today\n";
        return false;
    }
    
    // Calculate daily sums and create output
    $unitsData = [];
    
    foreach ($dayData['units'] as $unitName => $unitData) {
        $storageSum = array_sum($unitData['energy_storage']);
        $loadSum = array_sum($unitData['energy_storage_load']);
        
        $unitsData[$unitName] = [
            'energy_storage_sum' => round($storageSum, 2),
            'energy_storage_count' => count($unitData['energy_storage']),
            'energy_storage_load_sum' => round($loadSum, 2),
            'energy_storage_load_count' => count($unitData['energy_storage_load'])
        ];
    }
    
    $outputFile = $todayPath . '/pump.json';
    $success = file_put_contents($outputFile, json_encode($unitsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($success) {
        echo "Updated: {$outputFile}\n";
        echo "Processed {$processedCount} files with {$errorCount} errors\n";
        echo "Found " . count($unitsData) . " energy storage units\n";
        return true;
    } else {
        echo "Failed to write: {$outputFile}\n";
        return false;
    }
}

// Main execution
echo "Daily Energy Storage (Pump) Data Processor\n";
echo str_repeat("=", 50) . "\n";

$success = processTodayEnergyStorageData();

if ($success) {
    echo "Daily pump data processing completed successfully\n";
} else {
    echo "Daily pump data processing failed\n";
}