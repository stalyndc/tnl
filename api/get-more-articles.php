<?php
/**
 * The News Log - Pagination API Endpoint
 * Simplified version without logger dependency
 */

// Include required files
require_once '../functions.php';

// Set appropriate headers for JSON response
header('Content-Type: application/json');

try {
    // Validate pagination parameters
    $offset = isset($_GET['offset']) ? filter_var($_GET['offset'], FILTER_VALIDATE_INT) : 0;
    $limit = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT) : 10;
    
    // Ensure values are valid
    if ($offset === false || $offset < 0) {
        $offset = 0;
    }
    
    if ($limit === false || $limit <= 0 || $limit > 50) {
        $limit = 10; // Default to 10 items
    }
    
    // Fetch more articles with pagination
    $feedData = getAllFeeds($limit, $offset);
    
    // Output as JSON
    echo json_encode($feedData);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'error' => 'An error occurred while loading articles. Please try again later.',
        'timestamp' => time(),
        'items' => [],
        'hasMore' => false
    ]);
}

exit;