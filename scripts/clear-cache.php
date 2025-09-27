#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

$baseDir = realpath(__DIR__ . '/..');
$cacheDir = $baseDir . '/storage/cache';
$logDir = $baseDir . '/storage/logs';

function removeFiles(string $directory, string $pattern): int
{
    $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pattern) ?: [];
    $count = 0;

    foreach ($files as $file) {
        if (@unlink($file)) {
            $count++;
        }
    }

    return $count;
}

if (!is_dir($cacheDir)) {
    echo "Cache directory not found: {$cacheDir}\n";
} else {
    $deleted = removeFiles($cacheDir, '*.json');
    echo "Removed {$deleted} cache files\n";
}

if (!is_dir($logDir)) {
    echo "Log directory not found: {$logDir}\n";
} else {
    $deleted = removeFiles($logDir, '*.log');
    echo "Removed {$deleted} log files\n";
}

echo "Done.\n";
