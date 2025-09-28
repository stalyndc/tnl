#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../functions.php';

use App\Config\FeedRepository;

$expectedToken = getenv('FEED_ADMIN_TOKEN') ?: '';
$providedToken = null;

$argvCopy = $argv;
array_shift($argvCopy);

foreach ($argvCopy as $index => $argument) {
    if (str_starts_with($argument, '--token=')) {
        $providedToken = substr($argument, 8);
        unset($argvCopy[$index]);
    }
}

$argvCopy = array_values($argvCopy);

if ($expectedToken !== '') {
    if ($providedToken === null || !hash_equals($expectedToken, $providedToken)) {
        fwrite(STDERR, "Authentication failed. Provide --token=<token>.\n");
        exit(1);
    }
}

$command = $argvCopy[0] ?? 'help';
$repo = getFeedRepository();

try {
    switch ($command) {
        case 'list':
            handleList($repo);
            break;
        case 'enable':
        case 'disable':
            handleToggle($repo, $argvCopy, $command === 'enable');
            break;
        case 'add':
            handleAdd($repo, $argvCopy);
            break;
        case 'update':
            handleUpdate($repo, $argvCopy);
            break;
        default:
            printHelp();
            exit(1);
    }
} catch (\Exception $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

function handleList(FeedRepository $repo): void
{
    $feeds = $repo->allWithMeta();

    if (empty($feeds)) {
        echo "No feeds defined.\n";
        return;
    }

    foreach ($feeds as $feed) {
        $status = ($feed['enabled'] ?? true) ? 'enabled' : 'disabled';
        printf("- %s (%s) [%s]\n  %s\n", $feed['id'], $feed['name'], $status, $feed['url']);
    }
}

function handleToggle(FeedRepository $repo, array $args, bool $enabled): void
{
    $id = $args[1] ?? null;

    if (!$id) {
        fwrite(STDERR, "Usage: php scripts/feed-admin.php " . ($enabled ? 'enable' : 'disable') . " <id>\n");
        exit(1);
    }

    $repo->setEnabled($id, $enabled);
    printf("Feed '%s' is now %s.\n", $id, $enabled ? 'enabled' : 'disabled');
}

function handleAdd(FeedRepository $repo, array $args): void
{
    $id = $args[1] ?? null;
    $name = $args[2] ?? null;
    $url = $args[3] ?? null;
    $enabled = true;

    foreach ($args as $argument) {
        if ($argument === '--disabled') {
            $enabled = false;
        }
        if ($argument === '--enabled') {
            $enabled = true;
        }
    }

    if (!$id || !$name || !$url) {
        fwrite(STDERR, "Usage: php scripts/feed-admin.php add <id> <name> <url> [--enabled|--disabled]\n");
        exit(1);
    }

    $repo->add($id, $name, $url, $enabled);
    printf("Feed '%s' added (%s).\n", $id, $enabled ? 'enabled' : 'disabled');
}

function handleUpdate(FeedRepository $repo, array $args): void
{
    $id = $args[1] ?? null;
    $name = $args[2] ?? null;
    $url = $args[3] ?? null;

    if (!$id || !$name || !$url) {
        fwrite(STDERR, "Usage: php scripts/feed-admin.php update <id> <name> <url>\n");
        exit(1);
    }

    $repo->updateDetails($id, $name, $url);
    printf("Feed '%s' updated.\n", $id);
}

function printHelp(): void
{
    echo <<<TEXT
Feed admin CLI
Usage:
  php scripts/feed-admin.php list
  php scripts/feed-admin.php enable <id>
  php scripts/feed-admin.php disable <id>
  php scripts/feed-admin.php add <id> <name> <url> [--enabled|--disabled]
  php scripts/feed-admin.php update <id> <name> <url>

Provide --token=<value> if FEED_ADMIN_TOKEN is set in the environment.
TEXT;
}
