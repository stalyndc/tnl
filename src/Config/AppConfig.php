<?php

namespace App\Config;

use Exception;

class AppConfig
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function data(): array
    {
        if (self::$config === null) {
            $basePath = realpath(__DIR__ . '/../../');
            Environment::bootstrap($basePath ?: __DIR__ . '/../../');

            $path = ($basePath ?: __DIR__ . '/../../') . '/config/app.php';

            if (!file_exists($path)) {
                throw new Exception('Application configuration not found at ' . $path);
            }

            $config = require $path;

            if (!is_array($config)) {
                throw new Exception('Application configuration is invalid');
            }

            $config = self::applyEnvironmentOverrides($config);

            self::$config = $config;
        }

        return self::$config;
    }

    public static function cacheTtl(): int
    {
        $data = self::data();
        return (int) ($data['cache']['ttl'] ?? 1800);
    }

    public static function cacheCleanupMaxAge(): int
    {
        $data = self::data();
        return (int) ($data['cache']['cleanup_max_age'] ?? 604800);
    }

    public static function httpTimeout(): int
    {
        $data = self::data();
        return (int) ($data['http']['timeout'] ?? 10);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function applyEnvironmentOverrides(array $config): array
    {
        $overrides = [
            'cache.ttl' => getenv('APP_CACHE_TTL'),
            'cache.cleanup_max_age' => getenv('APP_CACHE_CLEANUP_MAX_AGE'),
            'http.timeout' => getenv('APP_HTTP_TIMEOUT')
        ];

        foreach ($overrides as $path => $value) {
            if ($value === false || $value === null || $value === '') {
                continue;
            }

            $segments = explode('.', $path);
            $ref =& $config;
            $lastIndex = count($segments) - 1;

            foreach ($segments as $index => $segment) {
                if ($index === $lastIndex) {
                    $ref[$segment] = (int) $value;
                    break;
                }

                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }

                $ref =& $ref[$segment];
            }
        }

        return $config;
    }
}
