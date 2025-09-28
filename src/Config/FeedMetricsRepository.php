<?php

namespace App\Config;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

class FeedMetricsRepository
{
    private string $path;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cache = null;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->load();
    }

    public function recordSuccess(string $id): void
    {
        $metrics = $this->load();
        $entry = $metrics[$id] ?? $this->defaultEntry($id);

        $entry['success_count'] = (int) ($entry['success_count'] ?? 0) + 1;
        $entry['last_success'] = $this->now();
        $entry['consecutive_failures'] = 0;

        $metrics[$id] = $entry;
        $this->persist($metrics);
    }

    public function recordFailure(string $id, string $reason = ''): void
    {
        $metrics = $this->load();
        $entry = $metrics[$id] ?? $this->defaultEntry($id);

        $entry['failure_count'] = (int) ($entry['failure_count'] ?? 0) + 1;
        $entry['last_failure'] = $this->now();
        $entry['consecutive_failures'] = (int) ($entry['consecutive_failures'] ?? 0) + 1;
        if ($reason !== '') {
            $entry['last_error'] = $reason;
        }

        $metrics[$id] = $entry;
        $this->persist($metrics);
    }

    public function reset(string $id): void
    {
        $metrics = $this->load();
        if (isset($metrics[$id])) {
            $metrics[$id] = $this->defaultEntry($id);
            $this->persist($metrics);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (!file_exists($this->path)) {
            $this->cache = [];
            return $this->cache;
        }

        $decoded = json_decode(file_get_contents($this->path), true);
        if (is_array($decoded)) {
            $this->cache = $decoded;
        } else {
            $this->cache = [];
        }

        return $this->cache;
    }

    private function persist(array $metrics): void
    {
        $directory = dirname($this->path);
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new Exception('Failed to create metrics directory');
            }
        }

        if (file_put_contents($this->path, json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            throw new Exception('Failed to write feed metrics file');
        }

        $this->cache = $metrics;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultEntry(string $id): array
    {
        return [
            'id' => $id,
            'success_count' => 0,
            'failure_count' => 0,
            'last_success' => null,
            'last_failure' => null,
            'consecutive_failures' => 0,
            'last_error' => null
        ];
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}

