<?php

declare(strict_types=1);

namespace Tests\Config;

use App\Config\FeedRepository;
use PHPUnit\Framework\TestCase;

final class FeedRepositoryTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jsonPath = sys_get_temp_dir() . '/feeds-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($this->jsonPath, json_encode([
            [
                'id' => 'alpha',
                'name' => 'Alpha',
                'url' => 'https://example.com/alpha',
                'enabled' => true
            ],
            [
                'id' => 'beta',
                'name' => 'Beta',
                'url' => 'https://example.com/beta',
                'enabled' => false
            ]
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->jsonPath);
        parent::tearDown();
    }

    public function testAllReturnsEnabledFeeds(): void
    {
        $repo = new FeedRepository($this->jsonPath);

        $feeds = $repo->all();

        $this->assertArrayHasKey('alpha', $feeds);
        $this->assertArrayNotHasKey('beta', $feeds);
    }

    public function testSetEnabledPersistsChange(): void
    {
        $repo = new FeedRepository($this->jsonPath);
        $repo->setEnabled('beta', true);

        $feeds = $repo->all();
        $this->assertArrayHasKey('beta', $feeds);
    }

    public function testAddCreatesNewFeed(): void
    {
        $repo = new FeedRepository($this->jsonPath);
        $repo->add('gamma', 'Gamma', 'https://example.com/gamma', false);

        $all = $repo->allWithMeta();
        $ids = array_column($all, 'id');
        $this->assertContains('gamma', $ids);
    }

    public function testUpdateDetails(): void
    {
        $repo = new FeedRepository($this->jsonPath);
        $repo->updateDetails('alpha', 'Alpha Updated', 'https://example.com/alpha-updated');

        $all = $repo->allWithMeta();
        $alpha = array_values(array_filter($all, fn($feed) => $feed['id'] === 'alpha'))[0];
        $this->assertSame('Alpha Updated', $alpha['name']);
        $this->assertSame('https://example.com/alpha-updated', $alpha['url']);
    }
}
