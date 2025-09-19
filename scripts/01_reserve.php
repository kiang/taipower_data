<?php
/**
 * Script to download loadpara.txt and generate reserve.json
 * Extracts loadInfo (today's prediction) and loadInfoYday (yesterday's actual data)
 */

// Configuration
$baseDir = dirname(__DIR__);
$loadParaUrl = 'https://www.taipower.com.tw/d006/loadGraph/loadGraph/data/loadpara.txt';

// Get current year
$year = date('Y');
$outputDir = $baseDir . '/docs/genary/' . $year;

// Create output directory if it doesn't exist
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

/**
 * Download file from URL
 */
function downloadFile($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Failed to download from $url. HTTP Code: $httpCode");
    }
    
    return $data;
}

/**
 * Parse loadpara.txt to extract loadInfo and loadInfoYday
 */
function parseLoadPara($content) {
    $result = [
        'today' => [],
        'yesterday' => []
    ];
    
    // Extract loadInfo (today's data)
    if (preg_match('/var loadInfo = \[(.*?)\];/s', $content, $matches)) {
        $loadInfoStr = $matches[1];
        // Extract values between quotes
        preg_match_all('/"([^"]*)"/', $loadInfoStr, $values);
        if (!empty($values[1])) {
            $result['today'] = $values[1];
        }
    }
    
    // Extract loadInfoYday (yesterday's data)
    if (preg_match('/var loadInfoYday = \[(.*?)\];/s', $content, $matches)) {
        $loadInfoYdayStr = $matches[1];
        // Extract values between quotes
        preg_match_all('/"([^"]*)"/', $loadInfoYdayStr, $values);
        if (!empty($values[1])) {
            $result['yesterday'] = $values[1];
        }
    }
    
    return $result;
}

/**
 * Convert ROC date to standard date format
 */
function convertROCDate($rocDateStr) {
    if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $rocDateStr, $matches)) {
        $year = intval($matches[1]) + 1911; // Convert ROC year to AD
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        return "$year-$month-$day";
    }
    return null;
}

/**
 * Generate JSON data from parsed loadpara
 */
function generateJsonData($loadParaData, $existingData = []) {
    // Start with existing data or empty array
    $jsonData = $existingData;
    
    // Process today's data (loadInfo)
    if (!empty($loadParaData['today']) && count($loadParaData['today']) >= 4) {
        // Parse today's date from comment field
        $todayDate = convertROCDate($loadParaData['today'][3]);
        
        if ($todayDate) {
            // Remove commas from numeric values
            $loadLow = str_replace(',', '', $loadParaData['today'][0]);
            $loadHigh = str_replace(',', '', $loadParaData['today'][1]);
            $supplyPredict = str_replace(',', '', $loadParaData['today'][2]);
            $comment = $loadParaData['today'][3];
            
            // Find if this date already exists
            $existingIndex = -1;
            foreach ($jsonData as $index => $entry) {
                if ($entry['date'] === $todayDate) {
                    $existingIndex = $index;
                    break;
                }
            }
            
            $todayEntry = [
                'date' => $todayDate,
                'load_low' => floatval($loadLow),
                'load_high' => floatval($loadHigh),
                'supply_predict' => floatval($supplyPredict),
                'comment' => $comment
            ];
            
            if ($existingIndex >= 0) {
                // Update existing entry
                $jsonData[$existingIndex] = array_merge($jsonData[$existingIndex], $todayEntry);
            } else {
                // Add new entry
                $jsonData[] = $todayEntry;
            }
        }
    }
    
    // Process yesterday's data (loadInfoYday)
    if (!empty($loadParaData['yesterday']) && count($loadParaData['yesterday']) >= 4) {
        // Parse yesterday's date
        $yesterdayDate = convertROCDate($loadParaData['yesterday'][3]);
        
        if ($yesterdayDate) {
            // Remove commas from numeric values
            $realLoad = str_replace(',', '', $loadParaData['yesterday'][0]);
            $realSupply = str_replace(',', '', $loadParaData['yesterday'][1]);
            $realReserveCap = $loadParaData['yesterday'][2]; // This is already a percentage
            
            // Find if this date already exists
            $existingIndex = -1;
            foreach ($jsonData as $index => $entry) {
                if ($entry['date'] === $yesterdayDate) {
                    $existingIndex = $index;
                    break;
                }
            }
            
            $yesterdayActuals = [
                'real_load' => floatval($realLoad),
                'real_supply' => floatval($realSupply),
                'real_reserve_cap' => floatval($realReserveCap)
            ];
            
            if ($existingIndex >= 0) {
                // Update existing entry with actual values
                $jsonData[$existingIndex] = array_merge($jsonData[$existingIndex], $yesterdayActuals);
            } else {
                // Create new entry with yesterday's date and actual values only
                $jsonData[] = array_merge([
                    'date' => $yesterdayDate
                ], $yesterdayActuals);
            }
        }
    }
    
    // Sort by date
    usort($jsonData, function($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
    
    return $jsonData;
}

try {
    echo "Starting Taipower reserve data processing...\n";
    
    // Download loadpara.txt
    echo "Downloading loadpara.txt...\n";
    $loadParaTxt = downloadFile($loadParaUrl);
    echo "Downloaded loadpara.txt successfully\n";
    
    // Parse loadpara.txt
    echo "Parsing loadpara.txt...\n";
    $loadParaData = parseLoadPara($loadParaTxt);
    
    if (!empty($loadParaData['today'])) {
        echo "Today's data found: " . implode(', ', $loadParaData['today']) . "\n";
    }
    if (!empty($loadParaData['yesterday'])) {
        echo "Yesterday's data found: " . implode(', ', $loadParaData['yesterday']) . "\n";
    }
    
    // Load existing JSON data if it exists
    $outputFile = $outputDir . '/reserve.json';
    $existingData = [];
    if (file_exists($outputFile)) {
        $existingContent = file_get_contents($outputFile);
        $existingData = json_decode($existingContent, true) ?: [];
        echo "Loaded " . count($existingData) . " existing records\n";
    }
    
    // Generate JSON data
    echo "Generating JSON data...\n";
    $jsonData = generateJsonData($loadParaData, $existingData);
    
    // Save to output file
    $jsonContent = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($outputFile, $jsonContent);
    echo "Data saved to $outputFile\n";
    echo "Total records: " . count($jsonData) . "\n";
    
    // Display the latest entries
    if (count($jsonData) > 0) {
        echo "\nLatest entries:\n";
        $latestEntries = array_slice($jsonData, -2);
        foreach ($latestEntries as $entry) {
            echo "- " . $entry['date'] . ": ";
            if (isset($entry['load_low'])) {
                echo "Predicted (low: " . $entry['load_low'] . ", high: " . $entry['load_high'] . ", supply: " . $entry['supply_predict'] . ")";
            }
            if (isset($entry['real_load'])) {
                echo " Actual (load: " . $entry['real_load'] . ", supply: " . $entry['real_supply'] . ", reserve: " . $entry['real_reserve_cap'] . "%)";
            }
            echo "\n";
        }
    }
    
    echo "\nProcessing completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}