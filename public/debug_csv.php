<?php

$csvPath = 'public/tunisia-real-estate.csv';

if (!file_exists($csvPath)) {
    echo "CSV file not found: $csvPath\n";
    exit(1);
}

$handle = fopen($csvPath, 'r');
if ($handle === false) {
    echo "Unable to open CSV file\n";
    exit(1);
}

// Read header
$header = fgetcsv($handle, 0, "\t");

echo "=== RAW HEADER ===\n";
var_dump($header);

echo "\n=== TRIMMED HEADER ===\n";
$trimmedHeader = array_map('trim', $header);
var_dump($trimmedHeader);

echo "\n=== HEADER DETAILS ===\n";
foreach ($trimmedHeader as $index => $column) {
    echo "Index $index: '$column' (length: " . strlen($column) . ")\n";
}

echo "\n=== BYTE REPRESENTATION ===\n";
foreach ($trimmedHeader as $index => $column) {
    echo "Index $index: " . bin2hex($column) . "\n";
}

echo "\n=== FIRST 3 DATA ROWS ===\n";
for ($i = 0; $i < 3; $i++) {
    $row = fgetcsv($handle, 0, "\t");
    if ($row === false) break;
    $row = array_map('trim', $row);
    echo "Row " . ($i + 1) . ": " . implode(' | ', $row) . "\n";
}

fclose($handle);
