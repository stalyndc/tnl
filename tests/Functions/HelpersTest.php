<?php

declare(strict_types=1);

namespace Tests\Functions;

use App\Cache\CacheRepository;
use App\Config\FeedMetricsRepository;
use App\Config\FeedRepository;
use App\Core\Container;
use App\Http\FeedClient;
use App\Services\FeedAggregator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../functions.php';

class DummyFeedClient extends FeedClient
{
    public int $fetchCalls = 0;

    public function __construct(private string $rss)
    {
    }

    public function fetch(array $sources): array
    {
        $this->fetchCalls++;

        return [
            'test-feed' => [
                'content' => $this->rss,
                'source' => [
                    'name' => 'Test Feed',
                    'url' => 'https://example.com/feed'
                ]
            ]
        ];
    }
}

class HelpersTest extends TestCase
{
    private string $cacheDir;
    private string $configPath;
    private string $metricsPath;
    private DummyFeedClient $feedClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir() . '/newslog-functions-' . bin2hex(random_bytes(4));
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

        $rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test Feed</title>
    <item>
      <title>Helpers Article</title>
      <link>https://example.com/helpers</link>
      <pubDate>Mon, 07 Oct 2024 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

        $this->feedClient = new DummyFeedClient($rss);

        $container = appContainer();

        $configPath = $this->configPath;
        $metricsPath = $this->metricsPath;

        $container->set(FeedRepository::class, function (Container $c) use ($configPath) {
            return new FeedRepository($configPath);
        });

        $cacheRepository = new CacheRepository($this->cacheDir);
        $container->set(CacheRepository::class, function (Container $c) use ($cacheRepository) {
            return $cacheRepository;
        });

        $container->set(FeedMetricsRepository::class, function (Container $c) use ($metricsPath) {
            return new FeedMetricsRepository($metricsPath);
        });

        $container->set(FeedClient::class, function (Container $c) {
            return $this->feedClient;
        });

        $container->set(FeedAggregator::class, function (Container $c) use ($cacheRepository, $metricsPath, $configPath) {
            return new FeedAggregator(
                new FeedRepository($configPath),
                $cacheRepository,
                $this->feedClient,
                new FeedMetricsRepository($metricsPath),
                cacheTtl: 1800,
                cacheCleanupMaxAge: 604800
            );
        });
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

    public function testGetAllFeedsUsesContainerServices(): void
    {
        $result = getAllFeeds(10, 0, true);

        $this->assertSame('Helpers Article', $result['items'][0]['title']);
        $this->assertSame(1, $this->feedClient->fetchCalls);
    }

    public function testGetFeedSourcesReturnsConfiguredFeeds(): void
    {
        $sources = getFeedSources();
        $this->assertArrayHasKey('test-feed', $sources);
        $this->assertSame('Test Feed', $sources['test-feed']['name']);
    }
}

