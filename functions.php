<?php

/**
 * The News Log - Core Functions
 * Optimized with async cURL multi-handle feed fetching
 * Updated with pagination support for "Load More" feature
 * Enhanced with error logging
 */

// Include the SimpleLogger
require_once 'includes/simple-logger.php';

/**
 * Load a configuration file with simple memoization
 *
 * @param string $relativePath Path relative to project root
 * @return mixed Configuration data
 * @throws Exception When file is missing or invalid
 */
function loadConfig($relativePath)
{
    static $configCache = [];

    $path = __DIR__ . '/' . ltrim($relativePath, '/');

    if (isset($configCache[$path])) {
        return $configCache[$path];
    }

    if (!file_exists($path)) {
        throw new Exception('Configuration file not found: ' . $path);
    }

    $config = require $path;

    if ($config === false || $config === null) {
        throw new Exception('Configuration file returned no data: ' . $path);
    }

    $configCache[$path] = $config;

    return $config;
}

/**
 * Get absolute path to the cache directory
 *
 * @return string
 */
function getCacheDirectory()
{
    return __DIR__ . '/cache';
}

/**
 * Get available feed sources
 * 
 * @return array List of feed sources
 */
function getFeedSources()
{
    static $sources = null;

    if ($sources !== null) {
        return $sources;
    }

    $loaded = loadConfig('config/feeds.php');

    if (!is_array($loaded) || empty($loaded)) {
        throw new Exception('Feed configuration is invalid');
    }

    $sources = $loaded;

    return $sources;
}

/**
 * Get feed data from multiple sources asynchronously with pagination support
 * 
 * @param int $limit Number of items to return per page
 * @param int $offset Starting offset for pagination
 * @param bool $getTotalCount Whether to include total count in the result
 * @return array Combined feed data with pagination info
 */
function getAllFeeds($limit = 10, $offset = 0, $getTotalCount = false)
{
    try {
        $sources = getFeedSources();
        $result = [
            'timestamp' => null,
            'items' => [],
            'hasMore' => false,
            'offset' => $offset,
            'limit' => $limit
        ];

        // Create cache directory if it doesn't exist
        $cacheDir = getCacheDirectory();

        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                Logger::error('Failed to create cache directory');
                throw new Exception('Failed to create cache directory');
            }
        }

        // Clean up old cache files (older than 7 days)
        try {
            cleanupOldCacheFiles();
        } catch (Exception $e) {
            Logger::warning('Failed to clean up old cache files', ['error' => $e->getMessage()]);
            // Continue execution despite cleanup failure
        }

        // Check if we have a valid combined cache
        $combinedCacheFile = getCacheDirectory() . '/combined_feed.json';
        $cacheTime = 30 * 60; // 30 minutes cache

        // For pagination, we need all items before slicing
        // So we first check if we have a complete cache
        if (file_exists($combinedCacheFile) && (time() - filemtime($combinedCacheFile) < $cacheTime)) {
            $cachedData = json_decode(file_get_contents($combinedCacheFile), true);

            if ($cachedData && !empty($cachedData['items'])) {
                if (!isset($cachedData['timestamp'])) {
                    $cachedData['timestamp'] = filemtime($combinedCacheFile);
                }

                // Calculate timestamp from cache metadata
                $result['timestamp'] = $cachedData['timestamp'];

                // Get total count before slicing for pagination
                $totalItems = count($cachedData['items']);
                
                // Slice the cached data according to offset and limit
                $result['items'] = array_slice($cachedData['items'], $offset, $limit);
                
                // Check if there are more items
                $result['hasMore'] = ($offset + $limit) < $totalItems;
                
                // Return total count if requested
                if ($getTotalCount) {
                    $result['totalCount'] = $totalItems;
                }
                
                return $result;
            }
        }

        // Set up multi-curl for parallel requests
        $mh = curl_multi_init();
        $curlHandles = [];
        $feedContent = [];
        $freshData = [];

        // Prepare curl handles for each source
        foreach ($sources as $id => $source) {
            // First, check for cached data for this source
            $cacheFile = getCacheDirectory() . '/feed_' . $id . '.json';
            
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
                // Use cached data for this source
                try {
                    $cachedData = json_decode(file_get_contents($cacheFile), true);
                    if ($cachedData && !empty($cachedData['items'])) {
                        $freshData[$id] = $cachedData;
                        continue; // Skip fetching this source
                    }
                } catch (Exception $e) {
                    Logger::warning('Failed to read cache file for ' . $id, ['error' => $e->getMessage()]);
                    // Continue with fetching this source
                }
            }
            
            // Set up curl for this source
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $source['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_multi_add_handle($mh, $ch);
            
            $curlHandles[$id] = $ch;
        }

        // Execute all curl handles in parallel
        if (!empty($curlHandles)) {
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh); // wait for activity
            } while ($running > 0);

            // Get content from all handles
            foreach ($curlHandles as $id => $ch) {
                $feedContent[$id] = [
                    'content' => curl_multi_getcontent($ch),
                    'source' => $sources[$id]
                ];
                
                // Get error if any
                $error = curl_error($ch);
                if ($error) {
                    Logger::error("cURL error for {$sources[$id]['name']}", [
                        'error' => $error,
                        'code' => curl_errno($ch),
                        'url' => $sources[$id]['url']
                    ]);
                }
                
                curl_multi_remove_handle($mh, $ch);
            }
            
            curl_multi_close($mh);
        }

        // Process all feed content
        foreach ($feedContent as $id => $data) {
            $source = $data['source'];
            $content = $data['content'];
            
            if (!$content) {
                Logger::warning("Empty content from source: {$source['name']}", ['url' => $source['url']]);
                continue; // Skip if empty content
            }
            
            try {
                // Check for malformed XML before parsing
                if (!preg_match('/<\?xml/', $content)) {
                    Logger::warning("Invalid XML format from source: {$source['name']}", [
                        'url' => $source['url'],
                        'content_preview' => substr($content, 0, 100)
                    ]);
                    continue; // Skip if not valid XML
                }
                
                // Use libxml error handling
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($content);
                
                if (!$xml) {
                    $errors = libxml_get_errors();
                    libxml_clear_errors();
                    $errorMessages = [];
                    foreach ($errors as $error) {
                        $errorMessages[] = $error->message;
                    }
                    
                    Logger::error("Failed to parse XML from source: {$source['name']}", [
                        'errors' => $errorMessages,
                        'url' => $source['url']
                    ]);
                    continue; // Skip if invalid XML
                }
                
                $sourceData = [
                    'timestamp' => time(),
                    'items' => []
                ];
                
                // Parse items from standard RSS
                if (isset($xml->channel) && isset($xml->channel->item)) {
                    $items = $xml->channel->item;
                    
                    if (count($items) > 0) {
                        foreach ($items as $item) {
                            // Parse publication date
                            $pubDate = (string)$item->pubDate;
                            $timestamp = strtotime($pubDate);
                            
                            if (!$timestamp) {
                                Logger::warning("Invalid date format in feed: {$source['name']}", [
                                    'pubDate' => $pubDate
                                ]);
                                $timestamp = time(); // Default to current time
                            }
                            
                            $sourceData['items'][] = [
                                'title' => cleanTitle((string)$item->title),
                                'link' => (string)$item->link,
                                'pubDate' => $pubDate,
                                'timestamp' => $timestamp,
                                'source' => $source['name'],
                                'sourceId' => $id
                            ];
                        }
                    }
                }
                
                // Try Atom format if no items found
                if (empty($sourceData['items']) && isset($xml->entry)) {
                    $items = $xml->entry;
                    
                    if (count($items) > 0) {
                        foreach ($items as $item) {
                            $link = '';
                            if (isset($item->link['href'])) {
                                $link = (string)$item->link['href'];
                            }
                            
                            $pubDate = isset($item->published) ? (string)$item->published : (string)$item->updated;
                            $timestamp = strtotime($pubDate);
                            
                            if (!$timestamp) {
                                Logger::warning("Invalid date format in Atom feed: {$source['name']}", [
                                    'pubDate' => $pubDate
                                ]);
                                $timestamp = time(); // Default to current time
                            }
                            
                            $sourceData['items'][] = [
                                'title' => cleanTitle((string)$item->title),
                                'link' => $link,
                                'pubDate' => $pubDate,
                                'timestamp' => $timestamp,
                                'source' => $source['name'],
                                'sourceId' => $id
                            ];
                        }
                    }
                }
                
                // Cache the source data
                if (!empty($sourceData['items'])) {
                    $cacheFile = getCacheDirectory() . '/feed_' . $id . '.json';
                    if (!file_put_contents($cacheFile, json_encode($sourceData))) {
                        Logger::error("Failed to write cache file for {$source['name']}", [
                            'file' => $cacheFile
                        ]);
                    }
                    $freshData[$id] = $sourceData;
                } else {
                    Logger::warning("No items found in feed: {$source['name']}", [
                        'url' => $source['url']
                    ]);
                }
            } catch (Exception $e) {
                // Log the error
                Logger::error("Error processing feed {$source['name']}", [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                continue;
            }
        }

        // Add data from freshly fetched sources
        $allItems = [];
        foreach ($freshData as $sourceData) {
            if (!empty($sourceData['items'])) {
                $allItems = array_merge($allItems, $sourceData['items']);
            }
        }

        // Sort all items by date (newest first)
        usort($allItems, function ($a, $b) {
            $timeA = isset($a['timestamp']) ? $a['timestamp'] : 0;
            $timeB = isset($b['timestamp']) ? $b['timestamp'] : 0;
            return $timeB - $timeA;
        });
        
        // Store all items in combined cache for future pagination requests
        $combinedData = [
            'timestamp' => time(),
            'items' => $allItems
        ];

        if (!file_put_contents($combinedCacheFile, json_encode($combinedData))) {
            Logger::error("Failed to write combined cache file", [
                'file' => $combinedCacheFile
            ]);
        }
        
        // Get total count for pagination info
        $totalItems = count($allItems);
        
        // Apply pagination
        $result['items'] = array_slice($allItems, $offset, $limit);

        // Set result timestamp from combined cache
        $result['timestamp'] = $combinedData['timestamp'];
        
        // Check if there are more items
        $result['hasMore'] = ($offset + $limit) < $totalItems;
        
        // Return total count if requested
        if ($getTotalCount) {
            $result['totalCount'] = $totalItems;
        }

        return $result;
    } catch (Exception $e) {
        // Log the critical error
        Logger::error("Critical error in getAllFeeds", [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Return error result
        return [
            'timestamp' => time(),
            'error' => 'Failed to fetch feeds. Please try again later.',
            'items' => [],
            'hasMore' => false
        ];
    }
}

/**
 * Clean up old cache files to prevent disk space issues
 * 
 * @param int $maxAge Maximum age of cache files in seconds (default: 7 days)
 */
function cleanupOldCacheFiles($maxAge = 604800) // 7 days = 60*60*24*7
{
    try {
        $cacheDir = getCacheDirectory();
        if (is_dir($cacheDir)) {
            $files = glob(rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json');
            foreach ($files as $file) {
                if (filemtime($file) < time() - $maxAge) {
                    if (!unlink($file)) {
                        Logger::warning("Failed to delete old cache file", [
                            'file' => $file
                        ]);
                    }
                }
            }
        }
    } catch (Exception $e) {
        Logger::error("Error cleaning up cache files", [
            'error' => $e->getMessage()
        ]);
        throw $e; // Re-throw to allow caller to handle it
    }
}

/**
 * Clean up HTML entities in a string
 * 
 * @param string $string String to clean
 * @return string Cleaned string
 */
function cleanTitle($string) {
    try {
        // First, directly replace common problematic HTML entities
        $replacements = [
            '&#8217;' => "'",
            '&#8216;' => "'",
            '&#8220;' => '"',
            '&#8221;' => '"',
            '&#8211;' => '-',
            '&#8212;' => '--',
            '&apos;' => "'",
            '&quot;' => '"',
            '&amp;' => '&'
        ];
        
        $string = str_replace(array_keys($replacements), array_values($replacements), $string);
        
        // Then decode any remaining entities
        $string = html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Finally, check if there are still any &#XXXX; entities and convert them
        $string = preg_replace_callback('/&#(\d+);/', function($matches) {
            return mb_chr($matches[1], 'UTF-8');
        }, $string);
        
        return $string;
    } catch (Exception $e) {
        Logger::warning("Error cleaning title", [
            'error' => $e->getMessage(),
            'string' => substr($string, 0, 50) . (strlen($string) > 50 ? '...' : '')
        ]);
        
        // Return original string if cleaning fails
        return $string;
    }
}

/**
 * Format timestamp into relative time
 * 
 * @param int $timestamp Unix timestamp
 * @return string Formatted relative time
 */
function formatTimestamp($timestamp)
{
    $current = time();
    $diff = $current - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 172800) {
        return 'Yesterday';
    } else {
        $days = floor($diff / 86400);
        return $days . ' days ago';
    }
}
