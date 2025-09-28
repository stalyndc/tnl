<?php

namespace App\Config;

/**
 * Minimal environment loader supporting optional .env files.
 */
class Environment
{
    private static bool $bootstrapped = false;

    /**
     * Load environment variables from .env files if present.
     */
    public static function bootstrap(string $basePath): void
    {
        if (self::$bootstrapped) {
            return;
        }

        $candidates = [
            $basePath . '/.env',
            $basePath . '/.env.local'
        ];

        foreach ($candidates as $file) {
            if (is_readable($file)) {
                self::loadFile($file);
            }
        }

        self::$bootstrapped = true;
    }

    private static function loadFile(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, null);
            $key = trim($key);
            if ($key === '') {
                continue;
            }

            $value = $value !== null ? trim($value) : '';
            $value = self::stripQuotes($value);

            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private static function stripQuotes(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
