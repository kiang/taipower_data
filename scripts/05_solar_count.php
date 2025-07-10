<?php
$basePath = dirname(__DIR__);

/**
 * Calculate daily solar power generation sums for the last 60 days
 * Output format: CSV with date and sum in 度 (kWh) fields
 * Each data point represents 10 minutes, so MW * (10/60) = MWh, then * 1000 = kWh (度)
 */

/**
 * Extract solar power data from a single JSON file
 * @param string $filePath Path to the JSON file
 * @return float Total solar power generation in 度 (kWh) for 10-minute period
 */
function extractSolarPowerFromFile($filePath) {
    if (!file_exists($filePath)) {
        return 0.0;
    }
    
    $jsonData = json_decode(file_get_contents($filePath), true);
    if (!$jsonData || !isset($jsonData['aaData'])) {
        return 0.0;
    }
    
    $solarTotal = 0.0;
    
    foreach ($jsonData['aaData'] as $row) {
        // Check if this is a solar power entry
        if (isset($row[0]) && strpos($row[0], 'solar') !== false) {
            // Extract the power output value (4th column)
            if (isset($row[4])) {
                $output = trim($row[4]);
                // Convert to float, handling N/A and other non-numeric values
                if (is_numeric($output)) {
                    // Convert MW to kWh (度): MW * (10/60) hours * 1000 = kWh
                    $solarTotal += floatval($output) * (10.0/60.0) * 1000.0;
                }
            }
        }
    }
    
    return $solarTotal;
}

/**
 * Calculate daily solar power sum for a specific date
 * @param string $date Date in YYYYMMDD format
 * @return float Daily solar power sum in 度 (kWh)
 */
function calculateDailySolarSum($date) {
    global $basePath;
    
    $year = substr($date, 0, 4);
    $dateDir = $basePath . '/docs/genary/' . $year . '/' . $date;
    
    if (!is_dir($dateDir)) {
        return 0.0;
    }
    
    $dailySum = 0.0;
    $files = glob($dateDir . '/*.json');
    
    foreach ($files as $file) {
        // Skip list.json and other non-data files
        if (basename($file) === 'list.json') {
            continue;
        }
        
        $solarEnergy = extractSolarPowerFromFile($file);
        $dailySum += $solarEnergy;
    }
    
    return $dailySum;
}

/**
 * Get the last 60 days of dates
 * @return array Array of dates in YYYYMMDD format
 */
function getLast60Days() {
    $dates = [];
    $currentDate = new DateTime();
    
    for ($i = 0; $i < 60; $i++) {
        $dates[] = $currentDate->format('Ymd');
        $currentDate->modify('-1 day');
    }
    
    return array_reverse($dates); // Return in chronological order
}

/**
 * Format date for CSV output
 * @param string $date Date in YYYYMMDD format
 * @return string Formatted date (YYYY-MM-DD)
 */
function formatDate($date) {
    return substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
}

// Main execution
echo "Calculating daily solar power generation sums for the last 60 days...\n";

$dates = getLast60Days();
$solarData = [];

foreach ($dates as $date) {
    $dailySum = calculateDailySolarSum($date);
    $solarData[] = [
        'date' => formatDate($date),
        'sum_kwh' => $dailySum
    ];
    
    echo "Date: " . formatDate($date) . " - Solar Sum: " . number_format($dailySum, 0) . " 度\n";
}

// Create CSV output
$csvFile = $basePath . '/docs/solar.csv';
$csvContent = "date,sum_kwh\n";

foreach ($solarData as $row) {
    $csvContent .= $row['date'] . ',' . number_format($row['sum_kwh'], 0, '.', '') . "\n";
}

file_put_contents($csvFile, $csvContent);

echo "\nCSV file created: {$csvFile}\n";
echo "Total records: " . count($solarData) . "\n";

// Display summary statistics
$totalSum = array_sum(array_column($solarData, 'sum_kwh'));
$averageSum = $totalSum / count($solarData);
$maxSum = max(array_column($solarData, 'sum_kwh'));
$minSum = min(array_column($solarData, 'sum_kwh'));

echo "\nSummary Statistics:\n";
echo "Total Solar Generation (60 days): " . number_format($totalSum, 0) . " 度\n";
echo "Average Daily Generation: " . number_format($averageSum, 0) . " 度\n";
echo "Maximum Daily Generation: " . number_format($maxSum, 0) . " 度\n";
echo "Minimum Daily Generation: " . number_format($minSum, 0) . " 度\n";