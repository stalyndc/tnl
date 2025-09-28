<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Cache\CacheRepository;
use App\Config\FeedMetricsRepository;
use App\Http\FeedClient;
use App\Services\FeedAggregator;
use PHPUnit\Framework\TestCase;

final class FeedAggregatorTest extends TestCase
{
    private string $cacheDir;
    private string $configPath;
    private string $metricsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir() . '/newslog-test-' . bin2hex(random_bytes(4));
        if (!mkdir($concurrentDirectory = $this->cacheDir, 0777, true) && !is_dir($concurrentDirectory)) {
            $this->fail('Failed to create temporary cache directory');
        }

        $this->configPath = $this->cacheDir . '/feeds.json';
        file_put_contents($this->configPath, json_encode([
            [
                'id' => 'test-feed',
                'name' => 'Test Feed',
                'url' => 'https://example.com/feed',
                'enabled' => true
            ]
        ]));

        $this->metricsPath = $this->cacheDir . '/metrics.json';
    }

    protected function tearDown(): void
    {
        $files = glob($this->cacheDir . '/*.json') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @unlink($this->configPath);
        @unlink($this->metricsPath);
        @rmdir($this->cacheDir);
        parent::tearDown();
    }

    public function testAggregatesFeedsWhenCacheEmpty(): void
    {
        $feedRepository = new \App\Config\FeedRepository($this->configPath);
        $metricsRepository = new FeedMetricsRepository($this->metricsPath);

        $feedClient = new class extends FeedClient {
            public int $fetchCalls = 0;

            public function fetch(array $sources): array
            {
                $this->fetchCalls++;

                $rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test Feed</title>
    <item>
      <title>Sample Article</title>
      <link>https://example.com/article</link>
      <pubDate>Mon, 07 Oct 2024 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

                return [
                    'test-feed' => [
                        'content' => $rss,
                        'source' => [
                            'name' => 'Test Feed',
                            'url' => 'https://example.com/feed'
                        ]
                    ]
                ];
            }
        };

        $cacheRepository = new CacheRepository($this->cacheDir);

        $aggregator = new FeedAggregator(
            $feedRepository,
            $cacheRepository,
            $feedClient,
            $metricsRepository,
            cacheTtl: 1800,
            cacheCleanupMaxAge: 604800
        );

        $result = $aggregator->getAllFeeds(10, 0, true);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame('Test Feed', $result['items'][0]['source']);
        $this->assertSame(1, $feedClient->fetchCalls);
        $this->assertSame(1, $result['totalCount']);

        $metrics = $metricsRepository->all();
        $this->assertArrayHasKey('test-feed', $metrics);
        $this->assertSame(1, $metrics['test-feed']['success_count']);
    }

    public function testUsesCombinedCacheOnSubsequentCalls(): void
    {
        $feedRepository = new \App\Config\FeedRepository($this->configPath);
        $metricsRepository = new FeedMetricsRepository($this->metricsPath);

        $feedClient = new class extends FeedClient {
            public int $fetchCalls = 0;

            public function fetch(array $sources): array
            {
                $this->fetchCalls++;

                $rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test Feed</title>
    <item>
      <title>Cached Article</title>
      <link>https://example.com/cached</link>
      <pubDate>Mon, 07 Oct 2024 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

                return [
                    'test-feed' => [
                        'content' => $rss,
                        'source' => [
                            'name' => 'Test Feed',
                            'url' => 'https://example.com/feed'
                        ]
                    ]
                ];
            }
        };

        $cacheRepository = new CacheRepository($this->cacheDir);

        $aggregator = new FeedAggregator(
            $feedRepository,
            $cacheRepository,
            $feedClient,
            $metricsRepository,
            cacheTtl: 1800,
            cacheCleanupMaxAge: 604800
        );

        $aggregator->getAllFeeds();
        $aggregator->getAllFeeds();

        $this->assertSame(1, $feedClient->fetchCalls, 'Fetch should only occur once due to combined cache');

        $metrics = $metricsRepository->all();
        $this->assertSame(1, $metrics['test-feed']['success_count']);
    }
}

