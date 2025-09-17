<?php
$basePath = dirname(__DIR__);

function processEnergyStorageData() {
    global $basePath;
    
    $genaryPath = $basePath . '/docs/genary';
    
    $allData = [];
    $processedCount = 0;
    $errorCount = 0;
    $createdFiles = 0;
    
    echo "Starting energy storage data processing...\n";
    echo "Scanning directory: {$genaryPath}\n";
    
    $jsonFiles = glob($genaryPath . '/*/*/*.json');
    
    echo "Found " . count($jsonFiles) . " files to process\n";
    
    foreach ($jsonFiles as $jsonFile) {
        $pathInfo = pathinfo($jsonFile);
        
        if ($pathInfo['filename'] === 'list' || $pathInfo['filename'] === 'pump') {
            continue;
        }
        
        $processedCount++;
        
        if ($processedCount % 1000 === 0) {
            echo "Processed {$processedCount} files...\n";
        }
        
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
            
            $timestamp = isset($json['']) ? $json[''] : '';
            if (empty($timestamp)) {
                $pathParts = explode('/', $jsonFile);
                $dateStr = $pathParts[count($pathParts) - 2];
                $timeStr = str_replace('.json', '', $pathParts[count($pathParts) - 1]);
                
                if (preg_match('/^(\d{8})$/', $dateStr) && preg_match('/^(\d{6})$/', $timeStr)) {
                    $timestamp = substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2) . ' ' . 
                                substr($timeStr, 0, 2) . ':' . substr($timeStr, 2, 2);
                }
            }
            
            if (empty($timestamp)) {
                continue;
            }
            
            $date = substr($timestamp, 0, 10);
            $year = substr($timestamp, 0, 4);
            $dateForPath = str_replace('-', '', $date);
            
            if (!isset($allData[$year])) {
                $allData[$year] = [];
            }
            if (!isset($allData[$year][$dateForPath])) {
                $allData[$year][$dateForPath] = [
                    'date' => $date,
                    'units' => []
                ];
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
                
                if (strpos($category, '儲能(Energy Storage System)') !== false) {
                    if (!isset($allData[$year][$dateForPath]['units'][$unitName])) {
                        $allData[$year][$dateForPath]['units'][$unitName] = [
                            'energy_storage' => [],
                            'energy_storage_load' => []
                        ];
                    }
                    $allData[$year][$dateForPath]['units'][$unitName]['energy_storage'][] = $outputValue;
                } elseif (strpos($category, '儲能負載(Energy Storage Load)') !== false) {
                    if (!isset($allData[$year][$dateForPath]['units'][$unitName])) {
                        $allData[$year][$dateForPath]['units'][$unitName] = [
                            'energy_storage' => [],
                            'energy_storage_load' => []
                        ];
                    }
                    $allData[$year][$dateForPath]['units'][$unitName]['energy_storage_load'][] = abs($outputValue);
                }
            }
            
        } catch (Exception $e) {
            $errorCount++;
            echo "Error processing {$jsonFile}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nCalculating daily sums and saving output files...\n";
    
    foreach ($allData as $year => $yearData) {
        foreach ($yearData as $dateForPath => $dayData) {
            $outputDir = $genaryPath . '/' . $year . '/' . $dateForPath;
            
            if (!file_exists($outputDir)) {
                continue;
            }
            
            $outputFile = $outputDir . '/pump.json';
            
            $unitsData = [];
            $totalStorage = 0;
            $totalLoad = 0;
            
            foreach ($dayData['units'] as $unitName => $unitData) {
                $storageSum = array_sum($unitData['energy_storage']);
                $loadSum = array_sum($unitData['energy_storage_load']);
                
                $unitsData[$unitName] = [
                    'energy_storage_sum' => round($storageSum, 2),
                    'energy_storage_count' => count($unitData['energy_storage']),
                    'energy_storage_load_sum' => round($loadSum, 2),
                    'energy_storage_load_count' => count($unitData['energy_storage_load'])
                ];
                
                $totalStorage += $storageSum;
                $totalLoad += $loadSum;
            }
            
            file_put_contents($outputFile, json_encode($unitsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $createdFiles++;
        }
        
        echo "Year {$year}: Created {$createdFiles} pump.json files\n";
        $createdFiles = 0;
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Processing completed!\n";
    echo "Total files processed: {$processedCount}\n";
    echo "Errors encountered: {$errorCount}\n";
    echo "Daily pump.json files created: " . count($allData[$year] ?? []) . "\n";
    echo str_repeat("=", 60) . "\n";
}


$startTime = microtime(true);

echo "Energy Storage (Pump) Data Counter\n";
echo str_repeat("=", 60) . "\n";

processEnergyStorageData();

$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);
echo "\nTotal execution time: {$executionTime} seconds\n";