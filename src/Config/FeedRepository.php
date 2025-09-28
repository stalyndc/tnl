<?php

namespace App\Config;

use Exception;

class FeedRepository
{
    private string $jsonPath;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $feeds = null;

    public function __construct(string $jsonPath)
    {
        $this->jsonPath = $jsonPath;
    }

    /**
     * Get all enabled feeds keyed by id.
     *
     * @return array<string, array<string, string>>
     * @throws Exception
     */
    public function all(): array
    {
        $feeds = $this->loadFeeds();

        $enabled = [];
        foreach ($feeds as $feed) {
            if (!($feed['enabled'] ?? true)) {
                continue;
            }

            $enabled[$feed['id']] = [
                'name' => $feed['name'] ?? '',
                'url' => $feed['url'] ?? ''
            ];
        }

        return $enabled;
    }

    /**
     * Return raw feed definitions including disabled entries.
     *
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function allWithMeta(): array
    {
        return $this->loadFeeds();
    }

    /**
     * Persist a new feed definition.
     */
    public function add(string $id, string $name, string $url, bool $enabled = true): void
    {
        $feeds = $this->loadFeeds();

        foreach ($feeds as $feed) {
            if ($feed['id'] === $id) {
                throw new Exception('Feed with id ' . $id . ' already exists');
            }
        }

        $feeds[] = [
            'id' => $id,
            'name' => $name,
            'url' => $url,
            'enabled' => $enabled
        ];

        $this->persist($feeds);
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $feeds = $this->loadFeeds();
        $updated = false;

        foreach ($feeds as &$feed) {
            if ($feed['id'] === $id) {
                $feed['enabled'] = $enabled;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            throw new Exception('Feed with id ' . $id . ' not found');
        }

        $this->persist($feeds);
    }

    private function loadFeeds(): array
    {
        if ($this->feeds !== null) {
            return $this->feeds;
        }

        if (file_exists($this->jsonPath)) {
            $decoded = json_decode(file_get_contents($this->jsonPath), true);
            if (is_array($decoded)) {
                $this->feeds = $decoded;
                return $this->feeds;
            }
        }

        $legacyPath = preg_replace('/\.json$/', '.php', $this->jsonPath);
        if ($legacyPath && file_exists($legacyPath)) {
            $legacy = require $legacyPath;
            if (is_array($legacy)) {
                $feeds = [];
                foreach ($legacy as $id => $feed) {
                    $feeds[] = [
                        'id' => $id,
                        'name' => $feed['name'] ?? '',
                        'url' => $feed['url'] ?? '',
                        'enabled' => $feed['enabled'] ?? true
                    ];
                }

                $this->persist($feeds);
                $this->feeds = $feeds;

                return $this->feeds;
            }
        }

        throw new Exception('Feed configuration is invalid');
    }

    private function persist(array $feeds): void
    {
        if (!file_exists(dirname($this->jsonPath))) {
            if (!mkdir(dirname($this->jsonPath), 0755, true) && !is_dir(dirname($this->jsonPath))) {
                throw new Exception('Failed to create directory for feeds configuration');
            }
        }

        if (file_put_contents($this->jsonPath, json_encode($feeds, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            throw new Exception('Failed to write feed configuration');
        }

        $this->feeds = $feeds;
    }
}
