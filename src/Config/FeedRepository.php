<?php

namespace App\Config;

use Exception;

class FeedRepository
{
    private string $configPath;

    /** @var array<string, array<string, string>>|null */
    private ?array $feeds = null;

    public function __construct(string $configPath)
    {
        $this->configPath = $configPath;
    }

    /**
     * Get all feed definitions from configuration.
     *
     * @return array<string, array<string, string>>
     * @throws Exception When configuration file is missing or invalid.
     */
    public function all(): array
    {
        if ($this->feeds !== null) {
            return $this->feeds;
        }

        if (!file_exists($this->configPath)) {
            throw new Exception('Feed configuration not found at ' . $this->configPath);
        }

        $feeds = require $this->configPath;

        if (!is_array($feeds) || empty($feeds)) {
            throw new Exception('Feed configuration is invalid');
        }

        $this->feeds = $feeds;

        return $this->feeds;
    }
}
