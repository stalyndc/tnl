<?php

namespace App\Cache;

use Exception;

class CacheRepository
{
    private string $cacheDirectory;

    public function __construct(string $cacheDirectory)
    {
        $this->cacheDirectory = rtrim($cacheDirectory, DIRECTORY_SEPARATOR);
    }

    public function getDirectory(): string
    {
        return $this->cacheDirectory;
    }

    /**
     * Ensure cache directory exists.
     */
    public function ensureStorage(): void
    {
        if (!is_dir($this->cacheDirectory)) {
            if (!mkdir($this->cacheDirectory, 0755, true) && !is_dir($this->cacheDirectory)) {
                throw new Exception('Failed to create cache directory: ' . $this->cacheDirectory);
            }
        }
    }

    public function getCombinedCachePath(): string
    {
        return $this->cacheDirectory . DIRECTORY_SEPARATOR . 'combined_feed.json';
    }

    public function getSourceCachePath(string $id): string
    {
        $safeId = preg_replace('/[^a-z0-9\-_.]/i', '-', $id);
        return $this->cacheDirectory . DIRECTORY_SEPARATOR . 'feed_' . $safeId . '.json';
    }

    public function readCombined(int $ttlSeconds): ?array
    {
        $path = $this->getCombinedCachePath();
        return $this->readCacheFile($path, $ttlSeconds);
    }

    public function readSource(string $id, int $ttlSeconds): ?array
    {
        $path = $this->getSourceCachePath($id);
        return $this->readCacheFile($path, $ttlSeconds);
    }

    public function writeCombined(array $data): void
    {
        $this->writeCacheFile($this->getCombinedCachePath(), $data);
    }

    public function writeSource(string $id, array $data): void
    {
        $this->writeCacheFile($this->getSourceCachePath($id), $data);
    }

    public function cleanup(int $maxAgeSeconds): void
    {
        if (!is_dir($this->cacheDirectory)) {
            return;
        }

        $pattern = $this->cacheDirectory . DIRECTORY_SEPARATOR . '*.json';
        foreach (glob($pattern) ?: [] as $file) {
            if (filemtime($file) < time() - $maxAgeSeconds) {
                if (!unlink($file)) {
                    \Logger::warning('Failed to delete old cache file', ['file' => $file]);
                }
            }
        }
    }

    private function readCacheFile(string $path, int $ttlSeconds): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        if (time() - filemtime($path) >= $ttlSeconds) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        if (!isset($data['timestamp'])) {
            $data['timestamp'] = filemtime($path);
        }

        return $data;
    }

    private function writeCacheFile(string $path, array $data): void
    {
        $data['timestamp'] = $data['timestamp'] ?? time();
        if (file_put_contents($path, json_encode($data)) === false) {
            \Logger::error('Failed to write cache file', ['file' => $path]);
        }
    }
}
