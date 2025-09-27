<?php

/**
 * The News Log - Core Functions (Service Layer Edition)
 */

require_once __DIR__ . '/includes/simple-logger.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Cache\CacheRepository;
use App\Config\AppConfig;
use App\Config\FeedRepository;
use App\Http\FeedClient;
use App\Services\FeedAggregator;
use App\Support\ContentFormatter;
use App\Support\TimeFormatter;

function getFeedRepository(): FeedRepository
{
    static $repository = null;

    if ($repository === null) {
        $repository = new FeedRepository(__DIR__ . '/config/feeds.php');
    }

    return $repository;
}

function getCacheRepository(): CacheRepository
{
    static $cacheRepository = null;

    if ($cacheRepository === null) {
        $cacheRepository = new CacheRepository(__DIR__ . '/storage/cache');
    }

    return $cacheRepository;
}

function getFeedClient(): FeedClient
{
    static $client = null;

    if ($client === null) {
        try {
            $timeout = AppConfig::httpTimeout();
        } catch (Exception $e) {
            Logger::warning('Failed to read HTTP timeout from config, using default', [
                'error' => $e->getMessage()
            ]);
            $timeout = 10;
        }

        $client = new FeedClient($timeout);
    }

    return $client;
}

function getFeedAggregator(): FeedAggregator
{
    static $aggregator = null;

    if ($aggregator === null) {
        try {
            $cacheTtl = AppConfig::cacheTtl();
            $cleanup = AppConfig::cacheCleanupMaxAge();
        } catch (Exception $e) {
            Logger::warning('Failed to read cache config, using defaults', [
                'error' => $e->getMessage()
            ]);
            $cacheTtl = 1800;
            $cleanup = 604800;
        }

        $aggregator = new FeedAggregator(
            getFeedRepository(),
            getCacheRepository(),
            getFeedClient(),
            $cacheTtl,
            $cleanup
        );
    }

    return $aggregator;
}

function getCacheDirectory(): string
{
    return getCacheRepository()->getDirectory();
}

function getFeedSources(): array
{
    try {
        return getFeedRepository()->all();
    } catch (Exception $e) {
        Logger::error('Failed to load feed sources', [
            'error' => $e->getMessage()
        ]);

        return [];
    }
}

function getAllFeeds($limit = 10, $offset = 0, $getTotalCount = false): array
{
    try {
        return getFeedAggregator()->getAllFeeds((int) $limit, (int) $offset, (bool) $getTotalCount);
    } catch (Exception $e) {
        Logger::error('Critical error in getAllFeeds', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'timestamp' => time(),
            'error' => 'Failed to fetch feeds. Please try again later.',
            'items' => [],
            'hasMore' => false
        ];
    }
}

function cleanupOldCacheFiles($maxAge = 604800)
{
    try {
        getCacheRepository()->cleanup((int) $maxAge);
    } catch (Exception $e) {
        Logger::error('Error cleaning up cache files', [
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

function cleanTitle($string)
{
    return ContentFormatter::cleanTitle((string) $string);
}

function formatTimestamp($timestamp)
{
    return TimeFormatter::relativeToNow((int) $timestamp);
}
