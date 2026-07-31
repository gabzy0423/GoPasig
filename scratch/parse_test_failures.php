<?php

$logPath = 'C:\\Users\\Acer\\.gemini\\antigravity-ide\\brain\\4d4dc69e-c458-4814-a874-352a616e23ba\\.system_generated\\tasks\\task-889.log';
$content = file_get_contents($logPath);

preg_match_all('/FAILED\s+(Tests\\\\[^\s]+)\s+>\s+([^\n]+)/', $content, $matches, PREG_SET_ORDER);

echo "Total Failed Tests Found: " . count($matches) . "\n\n";

$failures = [];
foreach ($matches as $match) {
    $failures[] = [
        'class' => trim($match[1]),
        'method' => trim($match[2]),
    ];
}

file_put_contents('scratch/failure_list.json', json_encode($failures, JSON_PRETTY_PRINT));
echo json_encode($failures, JSON_PRETTY_PRINT);
