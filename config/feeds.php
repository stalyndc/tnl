<?php

$jsonPath = __DIR__ . '/feeds.json';

if (!file_exists($jsonPath)) {
    return [];
}

$data = json_decode(file_get_contents($jsonPath), true);

if (!is_array($data)) {
    return [];
}

$feeds = [];
foreach ($data as $feed) {
    if (!isset($feed['id'])) {
        continue;
    }

    $feeds[$feed['id']] = [
        'name' => $feed['name'] ?? '',
        'url' => $feed['url'] ?? '',
        'enabled' => $feed['enabled'] ?? true
    ];
}

return $feeds;
