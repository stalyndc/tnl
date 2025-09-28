<?php

namespace App\Services;

use App\Cache\CacheRepository;
use App\Config\FeedRepository;
use App\Config\FeedMetricsRepository;
use App\Http\FeedClient;
use App\Support\ContentFormatter;
use Exception;
use SimpleXMLElement;

class FeedAggregator
{
    private FeedRepository $feedRepository;
    private CacheRepository $cacheRepository;
    private FeedClient $feedClient;
    private FeedMetricsRepository $metricsRepository;
    private int $cacheTtl;
    private int $cacheCleanupMaxAge;

    public function __construct(
        FeedRepository $feedRepository,
        CacheRepository $cacheRepository,
        FeedClient $feedClient,
        FeedMetricsRepository $metricsRepository,
        int $cacheTtl,
        int $cacheCleanupMaxAge
    ) {
        $this->feedRepository = $feedRepository;
        $this->cacheRepository = $cacheRepository;
        $this->feedClient = $feedClient;
        $this->metricsRepository = $metricsRepository;
        $this->cacheTtl = $cacheTtl;
        $this->cacheCleanupMaxAge = $cacheCleanupMaxAge;
    }

    /**
     * Fetch and aggregate feed data with pagination.
     *
     * @return array<string, mixed>
     */
    public function getAllFeeds(int $limit = 10, int $offset = 0, bool $includeTotal = false): array
    {
        $result = [
            'timestamp' => null,
            'items' => [],
            'hasMore' => false,
            'offset' => $offset,
            'limit' => $limit
        ];

        try {
            $sources = $this->feedRepository->all();
            $this->cacheRepository->ensureStorage();
        } catch (Exception $e) {
            \Logger::error('Critical error preparing feeds', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResult('Failed to fetch feeds. Please try again later.');
        }

        try {
            $this->cacheRepository->cleanup($this->cacheCleanupMaxAge);
        } catch (Exception $e) {
            \Logger::warning('Failed to clean up old cache files', [
                'error' => $e->getMessage()
            ]);
        }

        $combinedCache = $this->cacheRepository->readCombined($this->cacheTtl);
        if ($combinedCache && !empty($combinedCache['items'])) {
            $totalItems = count($combinedCache['items']);
            $result['timestamp'] = $combinedCache['timestamp'] ?? time();
            $result['items'] = array_slice($combinedCache['items'], $offset, $limit);
            $result['hasMore'] = ($offset + $limit) < $totalItems;

            if ($includeTotal) {
                $result['totalCount'] = $totalItems;
            }

            return $result;
        }

        $sourceData = [];
        $sourcesToFetch = [];

        foreach ($sources as $id => $source) {
            $cached = $this->cacheRepository->readSource($id, $this->cacheTtl);
            if ($cached && !empty($cached['items'])) {
                $sourceData[$id] = $cached;
                continue;
            }

            $sourcesToFetch[$id] = $source;
        }

        if (!empty($sourcesToFetch)) {
            $responses = $this->feedClient->fetch($sourcesToFetch);

            foreach ($responses as $id => $response) {
                $content = $response['content'] ?? '';
                $source = $response['source'] ?? [];
                $httpCode = isset($response['http_code']) ? (int) $response['http_code'] : 200;
                $transportError = $response['error'] ?? null;

                if ($transportError !== null) {
                    $this->metricsRepository->recordFailure($id, $transportError);
                    \Logger::warning('Transport error while fetching feed source', [
                        'source' => $source['name'] ?? $id,
                        'url' => $source['url'] ?? null,
                        'error' => $transportError
                    ]);
                    continue;
                }

                if ($httpCode >= 400 || $httpCode === 0) {
                    $reason = sprintf('HTTP %d', $httpCode);
                    $this->metricsRepository->recordFailure($id, $reason, $httpCode);
                    \Logger::warning('Non-success HTTP status received from feed source', [
                        'source' => $source['name'] ?? $id,
                        'url' => $source['url'] ?? null,
                        'status' => $httpCode
                    ]);
                    continue;
                }

                if ($content === false || $content === '') {
                    $this->metricsRepository->recordFailure($id, 'Empty response', $httpCode);
                    \Logger::warning('Empty content from source', [
                        'source' => $source['name'] ?? $id,
                        'url' => $source['url'] ?? null
                    ]);
                    continue;
                }

                $parsed = $this->parseFeedContent($content, $id, $source);
                if (!empty($parsed['items'])) {
                    $sourceData[$id] = $parsed;
                    $this->cacheRepository->writeSource($id, $parsed);
                    $this->metricsRepository->recordSuccess($id, $httpCode);
                } else {
                    $this->metricsRepository->recordFailure($id, 'Parsed feed returned no items', $httpCode);
                }
            }
        }

        $allItems = [];
        foreach ($sourceData as $data) {
            if (!empty($data['items'])) {
                $allItems = array_merge($allItems, $data['items']);
            }
        }

        $allItems = $this->deduplicateItems($allItems);

        usort($allItems, function (array $a, array $b) {
            $timeA = $a['timestamp'] ?? 0;
            $timeB = $b['timestamp'] ?? 0;
            return $timeB <=> $timeA;
        });

        $combinedData = [
            'timestamp' => time(),
            'items' => $allItems
        ];

        $this->cacheRepository->writeCombined($combinedData);

        $totalItems = count($allItems);
        $result['timestamp'] = $combinedData['timestamp'];
        $result['items'] = array_slice($allItems, $offset, $limit);
        $result['hasMore'] = ($offset + $limit) < $totalItems;

        if ($includeTotal) {
            $result['totalCount'] = $totalItems;
        }

        return $result;
    }

    /**
     * Parse feed XML content into normalized structure.
     *
     * @param string $content
     * @param string $id
     * @param array<string, string> $source
     * @return array<string, mixed>
     */
    private function parseFeedContent(string $content, string $id, array $source): array
    {
        try {
            if (!preg_match('/<\?xml/', $content)) {
                $this->metricsRepository->recordFailure($id, 'Malformed XML (missing declaration)');
                \Logger::warning('Invalid XML format from source', [
                    'source' => $source['name'] ?? $id,
                    'url' => $source['url'] ?? null,
                    'content_preview' => substr($content, 0, 100)
                ]);

                return ['timestamp' => time(), 'items' => []];
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($content);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors(false);

            if (!$xml instanceof SimpleXMLElement) {
                $errorMessages = array_map(static fn($error) => $error->message, $errors);
                $this->metricsRepository->recordFailure($id, 'XML parse error');
                \Logger::error('Failed to parse XML from source', [
                    'source' => $source['name'] ?? $id,
                    'errors' => $errorMessages
                ]);

                return ['timestamp' => time(), 'items' => []];
            }

            $items = $this->extractItemsFromXml($xml, $id, $source);

            return [
                'timestamp' => time(),
                'items' => $items
            ];
        } catch (Exception $e) {
            $this->metricsRepository->recordFailure($id, $e->getMessage());
            \Logger::error('Error processing feed', [
                'source' => $source['name'] ?? $id,
                'error' => $e->getMessage()
            ]);

            return ['timestamp' => time(), 'items' => []];
        }
    }

    /**
     * @param SimpleXMLElement $xml
     * @param string $id
     * @param array<string, string> $source
     * @return array<int, array<string, mixed>>
     */
    private function extractItemsFromXml(SimpleXMLElement $xml, string $id, array $source): array
    {
        $items = [];

        if (isset($xml->channel) && isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = $this->normalizeItem($item, $id, $source);
            }
        }

        if (empty($items) && isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $items[] = $this->normalizeAtomItem($entry, $id, $source);
            }
        }

        return array_values(array_filter($items, static function ($item) {
            return !empty($item['title']) && !empty($item['link']);
        }));
    }

    /**
     * @param SimpleXMLElement $item
     * @param string $id
     * @param array<string, string> $source
     * @return array<string, mixed>
     */
    private function normalizeItem(SimpleXMLElement $item, string $id, array $source): array
    {
        $pubDate = (string) $item->pubDate;
        $timestamp = strtotime($pubDate) ?: time();

        if (!$timestamp) {
            \Logger::warning('Invalid date format in feed', [
                'source' => $source['name'] ?? $id,
                'pubDate' => $pubDate
            ]);
            $timestamp = time();
        }

        return [
            'title' => ContentFormatter::cleanTitle((string) $item->title),
            'link' => (string) $item->link,
            'pubDate' => $pubDate,
            'timestamp' => $timestamp,
            'source' => $source['name'] ?? $id,
            'sourceId' => $id,
            'sources' => [[
                'id' => $id,
                'name' => $source['name'] ?? $id
            ]]
        ];
    }

    /**
     * @param SimpleXMLElement $item
     * @param string $id
     * @param array<string, string> $source
     * @return array<string, mixed>
     */
    private function normalizeAtomItem(SimpleXMLElement $item, string $id, array $source): array
    {
        $link = '';
        if (isset($item->link['href'])) {
            $link = (string) $item->link['href'];
        }

        $pubDate = (string) ($item->published ?? $item->updated ?? '');
        $timestamp = strtotime($pubDate) ?: time();

        if (!$timestamp) {
            \Logger::warning('Invalid date format in Atom feed', [
                'source' => $source['name'] ?? $id,
                'pubDate' => $pubDate
            ]);
            $timestamp = time();
        }

        return [
            'title' => ContentFormatter::cleanTitle((string) $item->title),
            'link' => $link,
            'pubDate' => $pubDate,
            'timestamp' => $timestamp,
            'source' => $source['name'] ?? $id,
            'sourceId' => $id,
            'sources' => [[
                'id' => $id,
                'name' => $source['name'] ?? $id
            ]]
        ];
    }

    /**
     * Merge duplicate articles originating from multiple feeds.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateItems(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $key = $this->buildDedupeKey($item);

            if (!isset($grouped[$key])) {
                $grouped[$key] = $item;
                continue;
            }

            $existing =& $grouped[$key];

            $existing['sources'][] = [
                'id' => $item['sourceId'] ?? $item['source'] ?? 'unknown',
                'name' => $item['source'] ?? ($item['sourceId'] ?? 'unknown')
            ];

            if (($item['timestamp'] ?? 0) > ($existing['timestamp'] ?? 0)) {
                $existing['timestamp'] = $item['timestamp'] ?? $existing['timestamp'];
                $existing['link'] = $item['link'] ?? $existing['link'];
                $existing['source'] = $item['source'] ?? $existing['source'];
                $existing['sourceId'] = $item['sourceId'] ?? $existing['sourceId'];
            }
        }

        return array_values(array_map(function (array $item) {
            $item['sources'] = $this->uniqueSources($item['sources'] ?? []);
            return $item;
        }, $grouped));
    }

    /**
     * @param array<int, array<string, string>> $sources
     * @return array<int, array<string, string>>
     */
    private function uniqueSources(array $sources): array
    {
        $seen = [];
        $unique = [];

        foreach ($sources as $source) {
            $id = $source['id'] ?? $source['name'] ?? uniqid('source_', true);
            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $unique[] = $source;
        }

        return $unique;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function buildDedupeKey(array $item): string
    {
        $link = trim((string) ($item['link'] ?? ''));
        if ($link !== '') {
            return 'link:' . strtolower($link);
        }

        $title = strtolower(trim((string) ($item['title'] ?? '')));
        $title = preg_replace('/[^a-z0-9]+/i', '-', $title ?? '');

        return 'title:' . $title;
    }

    /**
     * Build a standard error response.
     *
     * @return array<string, mixed>
     */
    private function errorResult(string $message): array
    {
        return [
            'timestamp' => time(),
            'error' => $message,
            'items' => [],
            'hasMore' => false
        ];
    }
}
