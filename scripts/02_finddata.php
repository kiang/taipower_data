<?php
$basePath = dirname(__DIR__);

$sources = ['燃煤(Coal)', '汽電共生(Co-Gen)', '民營電廠-燃煤(IPP-Coal)', '燃氣(LNG)', '民營電廠-燃氣(IPP-LNG)', '燃油(Oil)', '輕油(Diesel)'];
$fire = [];
foreach (glob($basePath . '/docs/genary/*/*/*.json') as $jsonFile) {
    $p = pathinfo($jsonFile);
    if ($p['filename'] !== 'list') {
        $sum = 0;
        $json = json_decode(file_get_contents($jsonFile), true);
        foreach ($json['aaData'] as $line) {
            if (false !== strpos($line[2], '小計')) {
                $parts = explode('(', substr($line[4], 0, -2));
                $line[0] = trim(strip_tags($line[0]));
                if(in_array($line[0], $sources)) {
                    $sum += $parts[1];
                }
            }
        }
        if($sum > 100) {
            $fire[$json['']] = $sum;
        }
    }
}

print_r($fire);