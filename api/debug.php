<?php
// Simple debug script to test API functionality
header('Content-Type: text/plain');

echo "PHP Version: " . phpversion() . "\n\n";

// Test file paths
echo "File paths:\n";
echo "Current file: " . __FILE__ . "\n";
echo "functions.php exists: " . (file_exists('../functions.php') ? 'Yes' : 'No') . "\n";
echo "simple-logger.php exists: " . (file_exists('../includes/simple-logger.php') ? 'Yes' : 'No') . "\n";

// Test directory permissions
echo "\nDirectory permissions:\n";
echo "API directory writable: " . (is_writable('.') ? 'Yes' : 'No') . "\n";
echo "Root directory writable: " . (is_writable('..') ? 'Yes' : 'No') . "\n";

// Create logs directory if it doesn't exist
$logsDir = '../storage/logs';
if (!is_dir($logsDir)) {
    echo "Creating logs directory: ";
    echo mkdir($logsDir, 0755, true) ? "Success" : "Failed";
    echo "\n";
}
echo "Logs directory exists: " . (is_dir($logsDir) ? 'Yes' : 'No') . "\n";
echo "Logs directory writable: " . (is_writable($logsDir) ? 'Yes' : 'No') . "\n";

echo "\nFinished debugging";
?>
