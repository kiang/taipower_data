<?php
// Script to generate detailed HTML report for 興達新CC#1 and 興達新CC#2 power generation
// Creates visual timeline showing operating hours for each unit

$dataDir = '/home/kiang/public_html/taipower_data/docs/genary/2025';
$reportDir = '/home/kiang/public_html/taipower_data/docs/reports';

// Ensure report directory exists
if (!file_exists($reportDir)) {
    mkdir($reportDir, 0755, true);
}

// Data structure to store all operation data
$operationData = [
    'CC1' => [], // 興達新CC#1 data by date and hour
    'CC2' => []  // 興達新CC#2 data by date and hour
];

$statistics = [
    'CC1' => [
        'totalPower' => 0,
        'workingHours' => 0,
        'maxPower' => 0,
        'avgPower' => 0,
        'records' => 0
    ],
    'CC2' => [
        'totalPower' => 0,
        'workingHours' => 0,
        'maxPower' => 0,
        'avgPower' => 0,
        'records' => 0
    ]
];

$dateRange = [
    'start' => null,
    'end' => null
];

function processJsonFile($filePath, &$operationData, &$statistics) {
    if (!file_exists($filePath)) {
        return;
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        return;
    }
    
    $data = json_decode($content, true);
    if ($data === null || !isset($data['aaData']) || !is_array($data['aaData'])) {
        return;
    }
    
    // Extract date and time from filename
    $filename = basename($filePath);
    $dateDir = basename(dirname($filePath));
    
    if (preg_match('/(\d{6})\.json/', $filename, $matches)) {
        $timeStr = $matches[1];
        $hour = intval(substr($timeStr, 0, 2));
        $minute = intval(substr($timeStr, 2, 2));
        $date = substr($dateDir, 0, 4) . '-' . substr($dateDir, 4, 2) . '-' . substr($dateDir, 6, 2);
        
        foreach ($data['aaData'] as $record) {
            if (!is_array($record) || count($record) < 5) {
                continue;
            }
            
            $unitName = trim($record[2]);
            $generatedPower = floatval($record[4]);
            
            // Check for 興達新CC#1
            if (strpos($unitName, '興達新CC#1') !== false) {
                if (!isset($operationData['CC1'][$date])) {
                    $operationData['CC1'][$date] = array_fill(0, 24, ['power' => 0, 'count' => 0]);
                }
                
                $operationData['CC1'][$date][$hour]['power'] += $generatedPower;
                $operationData['CC1'][$date][$hour]['count']++;
                
                $statistics['CC1']['totalPower'] += $generatedPower;
                $statistics['CC1']['records']++;
                
                if ($generatedPower > 0) {
                    $statistics['CC1']['workingHours'] += (10/60);
                    if ($generatedPower > $statistics['CC1']['maxPower']) {
                        $statistics['CC1']['maxPower'] = $generatedPower;
                    }
                }
            }
            
            // Check for 興達新CC#2
            if (strpos($unitName, '興達新CC#2') !== false) {
                if (!isset($operationData['CC2'][$date])) {
                    $operationData['CC2'][$date] = array_fill(0, 24, ['power' => 0, 'count' => 0]);
                }
                
                $operationData['CC2'][$date][$hour]['power'] += $generatedPower;
                $operationData['CC2'][$date][$hour]['count']++;
                
                $statistics['CC2']['totalPower'] += $generatedPower;
                $statistics['CC2']['records']++;
                
                if ($generatedPower > 0) {
                    $statistics['CC2']['workingHours'] += (10/60);
                    if ($generatedPower > $statistics['CC2']['maxPower']) {
                        $statistics['CC2']['maxPower'] = $generatedPower;
                    }
                }
            }
        }
    }
}

// Process all JSON files
echo "Processing data files...\n";
if (is_dir($dataDir)) {
    $dateDirs = scandir($dataDir);
    
    foreach ($dateDirs as $dateDir) {
        if ($dateDir === '.' || $dateDir === '..') {
            continue;
        }
        
        $datePath = $dataDir . '/' . $dateDir;
        if (!is_dir($datePath)) {
            continue;
        }
        
        // Update date range
        $date = substr($dateDir, 0, 4) . '-' . substr($dateDir, 4, 2) . '-' . substr($dateDir, 6, 2);
        if ($dateRange['start'] === null || $date < $dateRange['start']) {
            $dateRange['start'] = $date;
        }
        if ($dateRange['end'] === null || $date > $dateRange['end']) {
            $dateRange['end'] = $date;
        }
        
        $jsonFiles = glob($datePath . '/*.json');
        foreach ($jsonFiles as $jsonFile) {
            processJsonFile($jsonFile, $operationData, $statistics);
        }
    }
}

// Calculate averages
if ($statistics['CC1']['records'] > 0) {
    $statistics['CC1']['avgPower'] = $statistics['CC1']['totalPower'] / $statistics['CC1']['records'];
}
if ($statistics['CC2']['records'] > 0) {
    $statistics['CC2']['avgPower'] = $statistics['CC2']['totalPower'] / $statistics['CC2']['records'];
}

// Sort dates
ksort($operationData['CC1']);
ksort($operationData['CC2']);

// Generate HTML report
$htmlContent = '<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>興達新CC#1 & CC#2 運轉報告 (' . $dateRange['start'] . ' - ' . $dateRange['end'] . ')</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .date-range {
            font-size: 1.2em;
            margin-top: 10px;
            opacity: 0.95;
        }
        
        .summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
            background: #f8f9fa;
        }
        
        .unit-summary {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .unit-title {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }
        
        .cc1-title { color: #667eea; border-color: #667eea; }
        .cc2-title { color: #764ba2; border-color: #764ba2; }
        
        .stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #212529;
        }
        
        .timeline-section {
            padding: 30px;
        }
        
        .timeline-title {
            font-size: 1.8em;
            margin-bottom: 20px;
            color: #212529;
        }
        
        .timeline-container {
            margin-bottom: 30px;
        }
        
        .date-timeline {
            margin-bottom: 20px;
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .date-label {
            font-weight: bold;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .unit-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .unit-label {
            width: 80px;
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .hour-blocks {
            display: flex;
            gap: 2px;
            flex: 1;
        }
        
        .hour-block {
            flex: 1;
            height: 25px;
            border-radius: 3px;
            position: relative;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .hour-block:hover {
            transform: scale(1.1);
            z-index: 10;
        }
        
        .hour-block.off {
            background: #e9ecef;
        }
        
        .hour-block.cc1-on {
            background: linear-gradient(135deg, #667eea 0%, #7c8ff0 100%);
        }
        
        .hour-block.cc2-on {
            background: linear-gradient(135deg, #764ba2 0%, #8e5bb6 100%);
        }
        
        .hour-labels {
            display: flex;
            gap: 2px;
            margin-left: 80px;
            margin-top: 5px;
        }
        
        .hour-label {
            flex: 1;
            text-align: center;
            font-size: 0.7em;
            color: #adb5bd;
        }
        
        .tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #212529;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
            margin-bottom: 5px;
        }
        
        .hour-block:hover .tooltip {
            opacity: 1;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .legend-color {
            width: 30px;
            height: 20px;
            border-radius: 5px;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            background: #f8f9fa;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>興達新CC#1 & CC#2 運轉報告</h1>
            <div class="date-range">📅 ' . $dateRange['start'] . ' 至 ' . $dateRange['end'] . '</div>
        </div>
        
        <div class="summary">
            <div class="unit-summary">
                <div class="unit-title cc1-title">⚡ 興達新CC#1</div>
                <div class="stat-grid">
                    <div class="stat-item">
                        <div class="stat-label">總發電量</div>
                        <div class="stat-value">' . number_format($statistics['CC1']['totalPower'], 2) . ' MW</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">總能量</div>
                        <div class="stat-value">' . number_format($statistics['CC1']['totalPower'] / 6, 2) . ' MWh</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">運轉時數</div>
                        <div class="stat-value">' . number_format($statistics['CC1']['workingHours'], 2) . ' 小時</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">運轉天數</div>
                        <div class="stat-value">' . number_format($statistics['CC1']['workingHours'] / 24, 2) . ' 天</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">最大功率</div>
                        <div class="stat-value">' . number_format($statistics['CC1']['maxPower'], 2) . ' MW</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">平均功率</div>
                        <div class="stat-value">' . number_format($statistics['CC1']['avgPower'], 2) . ' MW</div>
                    </div>
                </div>
            </div>
            
            <div class="unit-summary">
                <div class="unit-title cc2-title">⚡ 興達新CC#2</div>
                <div class="stat-grid">
                    <div class="stat-item">
                        <div class="stat-label">總發電量</div>
                        <div class="stat-value">' . number_format($statistics['CC2']['totalPower'], 2) . ' MW</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">總能量</div>
                        <div class="stat-value">' . number_format($statistics['CC2']['totalPower'] / 6, 2) . ' MWh</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">運轉時數</div>
                        <div class="stat-value">' . number_format($statistics['CC2']['workingHours'], 2) . ' 小時</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">運轉天數</div>
                        <div class="stat-value">' . number_format($statistics['CC2']['workingHours'] / 24, 2) . ' 天</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">最大功率</div>
                        <div class="stat-value">' . number_format($statistics['CC2']['maxPower'], 2) . ' MW</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">平均功率</div>
                        <div class="stat-value">' . number_format($statistics['CC2']['avgPower'], 2) . ' MW</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="timeline-section">
            <h2 class="timeline-title">📊 每日運轉時間軸</h2>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #667eea 0%, #7c8ff0 100%);"></div>
                    <span>CC#1 運轉中</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(135deg, #764ba2 0%, #8e5bb6 100%);"></div>
                    <span>CC#2 運轉中</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #e9ecef;"></div>
                    <span>未運轉</span>
                </div>
            </div>
            
            <div class="timeline-container">';

// Generate timeline for each date
$allDates = array_unique(array_merge(array_keys($operationData['CC1']), array_keys($operationData['CC2'])));
sort($allDates);

foreach ($allDates as $date) {
    $htmlContent .= '
                <div class="date-timeline">
                    <div class="date-label">📅 ' . $date . '</div>
                    
                    <div class="unit-row">
                        <div class="unit-label">CC#1</div>
                        <div class="hour-blocks">';
    
    // Generate CC#1 hour blocks
    for ($hour = 0; $hour < 24; $hour++) {
        $cc1Power = isset($operationData['CC1'][$date][$hour]) ? $operationData['CC1'][$date][$hour]['power'] : 0;
        $cc1Count = isset($operationData['CC1'][$date][$hour]) ? $operationData['CC1'][$date][$hour]['count'] : 0;
        $cc1Avg = $cc1Count > 0 ? ($cc1Power / $cc1Count) : 0;
        
        $class = $cc1Power > 0 ? 'cc1-on' : 'off';
        $tooltip = $cc1Power > 0 ? sprintf("%02d:00 - 平均 %.1f MW", $hour, $cc1Avg) : sprintf("%02d:00 - 未運轉", $hour);
        
        $htmlContent .= '
                            <div class="hour-block ' . $class . '">
                                <div class="tooltip">' . $tooltip . '</div>
                            </div>';
    }
    
    $htmlContent .= '
                        </div>
                    </div>
                    
                    <div class="unit-row">
                        <div class="unit-label">CC#2</div>
                        <div class="hour-blocks">';
    
    // Generate CC#2 hour blocks
    for ($hour = 0; $hour < 24; $hour++) {
        $cc2Power = isset($operationData['CC2'][$date][$hour]) ? $operationData['CC2'][$date][$hour]['power'] : 0;
        $cc2Count = isset($operationData['CC2'][$date][$hour]) ? $operationData['CC2'][$date][$hour]['count'] : 0;
        $cc2Avg = $cc2Count > 0 ? ($cc2Power / $cc2Count) : 0;
        
        $class = $cc2Power > 0 ? 'cc2-on' : 'off';
        $tooltip = $cc2Power > 0 ? sprintf("%02d:00 - 平均 %.1f MW", $hour, $cc2Avg) : sprintf("%02d:00 - 未運轉", $hour);
        
        $htmlContent .= '
                            <div class="hour-block ' . $class . '">
                                <div class="tooltip">' . $tooltip . '</div>
                            </div>';
    }
    
    $htmlContent .= '
                        </div>
                    </div>
                    
                    <div class="hour-labels">';
    
    // Add hour labels
    for ($hour = 0; $hour < 24; $hour++) {
        if ($hour % 3 == 0) {
            $htmlContent .= '<div class="hour-label">' . $hour . '</div>';
        } else {
            $htmlContent .= '<div class="hour-label"></div>';
        }
    }
    
    $htmlContent .= '
                    </div>
                </div>';
}

$htmlContent .= '
            </div>
        </div>
        
        <div class="footer">
            <p>報告生成時間：' . date('Y-m-d H:i:s') . '</p>
            <p>資料來源：台電即時電力資訊 | 每筆記錄代表10分鐘發電量</p>
        </div>
    </div>
</body>
</html>';

// Save HTML report
$reportFile = $reportDir . '/cc_' . str_replace('-', '', $dateRange['start']) . '_' . str_replace('-', '', $dateRange['end']) . '.html';
file_put_contents($reportFile, $htmlContent);

echo "\n=== 報告生成完成 ===\n";
echo "報告檔案：{$reportFile}\n";
echo "日期範圍：{$dateRange['start']} 至 {$dateRange['end']}\n";
echo "CC#1 總發電量：" . number_format($statistics['CC1']['totalPower'], 2) . " MW\n";
echo "CC#2 總發電量：" . number_format($statistics['CC2']['totalPower'], 2) . " MW\n";
echo "CC#1 運轉時數：" . number_format($statistics['CC1']['workingHours'], 2) . " 小時\n";
echo "CC#2 運轉時數：" . number_format($statistics['CC2']['workingHours'], 2) . " 小時\n";

?>