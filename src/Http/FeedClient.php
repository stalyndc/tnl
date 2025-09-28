<?php

namespace App\Http;

class FeedClient
{
    private int $timeout;

    public function __construct(int $timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * Fetch feeds in parallel using cURL multi.
     *
     * @param array<string, array<string, string>> $sources
     * @return array<string, array{content: string|false, source: array<string, string>}> 
     */
    public function fetch(array $sources): array
    {
        if (empty($sources)) {
            return [];
        }

        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($sources as $id => $source) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $source['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

            curl_multi_add_handle($multiHandle, $ch);
            $handles[$id] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        $results = [];
        foreach ($handles as $id => $handle) {
            $content = curl_multi_getcontent($handle);
            $errorCode = curl_errno($handle);
            $errorMessage = $errorCode !== 0 ? curl_error($handle) : null;
            $info = curl_getinfo($handle);
            $httpCode = isset($info['http_code']) ? (int) $info['http_code'] : 0;

            $results[$id] = [
                'content' => $content,
                'source' => $sources[$id],
                'http_code' => $httpCode,
                'error' => $errorMessage,
            ];

            if ($errorMessage !== null) {
                \Logger::error('cURL error', [
                    'error' => $errorMessage,
                    'code' => $errorCode,
                    'url' => $sources[$id]['url'] ?? ''
                ]);
            }

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        return $results;
    }
}
