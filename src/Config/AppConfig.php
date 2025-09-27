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
            $path = __DIR__ . '/../../config/app.php';

            if (!file_exists($path)) {
                throw new Exception('Application configuration not found at ' . $path);
            }

            $config = require $path;

            if (!is_array($config)) {
                throw new Exception('Application configuration is invalid');
            }

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
}
