#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Lib\Synchronizer;
use Lib\VideoRepository;

$options = getopt('', ['source::', 'limit::']);
$sourceId = isset($options['source']) ? (int) $options['source'] : null;
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 40;

$repository = new VideoRepository();
$synchronizer = new Synchronizer($repository);

try {
    $results = $synchronizer->synchronize($sourceId, $limit);
} catch (Throwable $exception) {
    fwrite(STDERR, '[error] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

foreach ($results as $result) {
    $source = $result['source'];
    $label = $source['label'] ?? ($source['identifier'] ?? 'Unknown source');

    echo PHP_EOL . 'Source: ' . $label . PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;
    echo 'Synced videos: ' . $result['synced'] . PHP_EOL;
    if ($result['skipped']) {
        echo 'Skipped videos: ' . $result['skipped'] . PHP_EOL;
    }
    if (!empty($result['errors'])) {
        echo 'Warnings:' . PHP_EOL;
        foreach ($result['errors'] as $message) {
            echo ' - ' . $message . PHP_EOL;
        }
    }
}

if (empty($results)) {
    echo 'No sources configured yet. Add sources via the admin dashboard.' . PHP_EOL;
}

echo PHP_EOL;
