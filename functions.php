<?php

/**
 * The News Log - Core Functions (Service Layer Edition)
 */

require_once __DIR__ . '/includes/simple-logger.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Cache\CacheRepository;
use App\Core\Container;
use App\Config\AppConfig;
use App\Config\FeedRepository;
use App\Http\FeedClient;
use App\Services\FeedAggregator;
use App\Support\ContentFormatter;
use App\Support\TimeFormatter;

function appContainer(): Container
{
    static $container = null;

    if ($container === null) {
        $container = new Container();
        registerDefaultServices($container);
    }

    return $container;
}

function registerDefaultServices(Container $container): void
{
    $container->set(FeedRepository::class, function (Container $c) {
        return new FeedRepository(__DIR__ . '/config/feeds.json');
    });

    $container->set(CacheRepository::class, function (Container $c) {
        return new CacheRepository(__DIR__ . '/storage/cache');
    });

    $container->set(FeedClient::class, function (Container $c) {
        try {
            $timeout = AppConfig::httpTimeout();
        } catch (\Exception $e) {
            Logger::warning('Failed to read HTTP timeout from config, using default', [
                'error' => $e->getMessage()
            ]);
            $timeout = 10;
        }

        return new FeedClient($timeout);
    });

    $container->set(FeedAggregator::class, function (Container $c) {
        try {
            $cacheTtl = AppConfig::cacheTtl();
            $cleanup = AppConfig::cacheCleanupMaxAge();
        } catch (\Exception $e) {
            Logger::warning('Failed to read cache config, using defaults', [
                'error' => $e->getMessage()
            ]);
            $cacheTtl = 1800;
            $cleanup = 604800;
        }

        return new FeedAggregator(
            $c->get(FeedRepository::class),
            $c->get(CacheRepository::class),
            $c->get(FeedClient::class),
            $cacheTtl,
            $cleanup
        );
    });
}

function getFeedRepository(): FeedRepository
{
    return appContainer()->get(FeedRepository::class);
}

function getCacheRepository(): CacheRepository
{
    return appContainer()->get(CacheRepository::class);
}

function getFeedClient(): FeedClient
{
    return appContainer()->get(FeedClient::class);
}

function getFeedAggregator(): FeedAggregator
{
    return appContainer()->get(FeedAggregator::class);
}

function getCacheDirectory(): string
{
    return getCacheRepository()->getDirectory();
}

function getFeedSources(): array
{
    try {
        return getFeedRepository()->all();
    } catch (\Exception $e) {
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
    } catch (\Exception $e) {
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
    } catch (\Exception $e) {
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
