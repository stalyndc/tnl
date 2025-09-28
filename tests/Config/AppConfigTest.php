<?php

declare(strict_types=1);

namespace Tests\Config;

use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AppConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetStaticCache();
        putenv('APP_CACHE_TTL');
        putenv('APP_CACHE_CLEANUP_MAX_AGE');
        putenv('APP_HTTP_TIMEOUT');
        unset($_ENV['APP_CACHE_TTL'], $_ENV['APP_CACHE_CLEANUP_MAX_AGE'], $_ENV['APP_HTTP_TIMEOUT']);
        unset($_SERVER['APP_CACHE_TTL'], $_SERVER['APP_CACHE_CLEANUP_MAX_AGE'], $_SERVER['APP_HTTP_TIMEOUT']);
    }

    public function testUsesDefaultConfigValues(): void
    {
        $this->resetStaticCache();

        $this->assertSame(1800, AppConfig::cacheTtl());
        $this->assertSame(604800, AppConfig::cacheCleanupMaxAge());
        $this->assertSame(10, AppConfig::httpTimeout());
    }

    public function testEnvironmentOverridesAreApplied(): void
    {
        putenv('APP_CACHE_TTL=900');
        putenv('APP_CACHE_CLEANUP_MAX_AGE=3600');
        putenv('APP_HTTP_TIMEOUT=5');
        $_ENV['APP_CACHE_TTL'] = '900';
        $_ENV['APP_CACHE_CLEANUP_MAX_AGE'] = '3600';
        $_ENV['APP_HTTP_TIMEOUT'] = '5';

        $this->resetStaticCache();

        $this->assertSame(900, AppConfig::cacheTtl());
        $this->assertSame(3600, AppConfig::cacheCleanupMaxAge());
        $this->assertSame(5, AppConfig::httpTimeout());
    }

    private function resetStaticCache(): void
    {
        $ref = new ReflectionClass(AppConfig::class);
        $property = $ref->getProperty('config');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}

