<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Cache\CacheRepository;
use App\Http\FeedClient;
use App\Services\FeedAggregator;
use PHPUnit\Framework\TestCase;

class FeedAggregatorTest extends TestCase
{
    private string $cacheDir;
    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir() . '/newslog-test-' . bin2hex(random_bytes(4));
        if (!mkdir($concurrentDirectory = $this->cacheDir, 0777, true) && !is_dir($concurrentDirectory)) {
            $this->fail('Failed to create temporary cache directory');
        }

        $this->configPath = $this->cacheDir . '/feeds.php';
        file_put_contents($this->configPath, <<<PHP
<?php
return [
    'test-feed' => [
        'name' => 'Test Feed',
        'url' => 'https://example.com/feed'
    ]
];
PHP);
    }

    protected function tearDown(): void
    {
        $files = glob($this->cacheDir . '/*.json') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @unlink($this->configPath);
        @rmdir($this->cacheDir);
        parent::tearDown();
    }

    public function testAggregatesFeedsWhenCacheEmpty(): void
    {
        $feedRepository = new \App\Config\FeedRepository($this->configPath);

        $feedClient = new class extends FeedClient {
            public int $fetchCalls = 0;

            public function __construct()
            {
            }

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
            cacheTtl: 1800,
            cacheCleanupMaxAge: 604800
        );

        $result = $aggregator->getAllFeeds(10, 0, true);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame('Test Feed', $result['items'][0]['source']);
        $this->assertSame(1, $feedClient->fetchCalls);
        $this->assertSame(1, $result['totalCount']);
    }

    public function testUsesCombinedCacheOnSubsequentCalls(): void
    {
        $feedRepository = new \App\Config\FeedRepository($this->configPath);

        $feedClient = new class extends FeedClient {
            public int $fetchCalls = 0;

            public function __construct()
            {
            }

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
            cacheTtl: 1800,
            cacheCleanupMaxAge: 604800
        );

        $aggregator->getAllFeeds();

        $result = $aggregator->getAllFeeds();

        $this->assertSame(1, $feedClient->fetchCalls, 'Fetch should only occur once due to combined cache');
        $this->assertSame('Cached Article', $result['items'][0]['title']);
    }
}
