<?php

declare(strict_types=1);

require_once BASE_PATH . '/lib/YouTubeClient.php';
require_once BASE_PATH . '/lib/AiService.php';
require_once BASE_PATH . '/lib/VideoRepository.php';

class IngestionService
{
    public function __construct(
        private readonly YouTubeClient $youtubeClient,
        private readonly AiService $aiService,
        private readonly VideoRepository $repository
    ) {
    }

    /**
     * Ingest videos from a keyword search.
     *
     * @return array{ingested: int, skipped: int, errors: array<int, string>, logs: array<int, string>}
     */
    public function ingestSearch(
        string $query,
        int $maxResults = 20,
        ?string $collectionName = null
    ): array {
        $result = $this->youtubeClient->searchVideos($query, $maxResults);
        $videoIds = array_map(
            fn (array $item) => $item['id']['videoId'] ?? null,
            $result['items'] ?? []
        );
        $videoIds = array_filter($videoIds);

        return $this->ingestVideoIds(
            $videoIds,
            $collectionName ? [$collectionName] : []
        );
    }

    /**
     * Ingest videos from a channel ID.
     */
    public function ingestChannel(
        string $channelId,
        int $maxResults = 20,
        ?string $collectionName = null
    ): array {
        $result = $this->youtubeClient->fetchChannelVideos($channelId, $maxResults);
        $videoIds = array_map(
            fn (array $item) => $item['id']['videoId'] ?? null,
            $result['items'] ?? []
        );
        $videoIds = array_filter($videoIds);

        return $this->ingestVideoIds(
            $videoIds,
            $collectionName ? [$collectionName] : []
        );
    }

    /**
     * @param array<int, string> $videoIds
     * @param array<int, string> $baseCollections
     *
     * @return array{ingested: int, skipped: int, errors: array<int, string>, logs: array<int, string>}
     */
    private function ingestVideoIds(array $videoIds, array $baseCollections = []): array
    {
        $details = $this->youtubeClient->fetchVideoDetails($videoIds);
        $errors = [];
        $logs = [];
        $ingested = 0;
        $skipped = 0;

        foreach ($details['items'] ?? [] as $item) {
            try {
                $youtubeId = $item['id'] ?? null;
                if (!$youtubeId) {
                    $skipped++;
                    continue;
                }

                $snippet = $item['snippet'] ?? [];
                $title = $snippet['title'] ?? 'Untitled';
                $description = $snippet['description'] ?? '';
                $channelTitle = $snippet['channelTitle'] ?? null;
                $publishedAt = $snippet['publishedAt'] ?? null;
                $tags = $snippet['tags'] ?? [];

                $ai = $this->aiService->analyzeVideo($title, $description);
                $collections = array_unique(array_filter(array_merge(
                    $baseCollections,
                    [$ai['category']]
                )));

                $payload = [
                    'youtube_id' => $youtubeId,
                    'title' => $title,
                    'description' => $description,
                    'channel_title' => $channelTitle,
                    'published_at' => $publishedAt,
                    'thumbnails' => $snippet['thumbnails'] ?? [],
                    'duration' => $item['contentDetails']['duration'] ?? null,
                    'tags' => $tags,
                    'ai_category' => $ai['category'],
                    'ai_summary' => $ai['summary'],
                    'ai_topics' => $ai['topics'],
                ];

                $this->repository->upsertVideo($payload, $collections);
                $ingested++;
                $logs[] = sprintf('Ingested %s — %s', $youtubeId, $title);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $skipped += max(0, count($videoIds) - $ingested - count($errors));

        return [
            'ingested' => $ingested,
            'skipped' => $skipped,
            'errors' => $errors,
            'logs' => $logs,
        ];
    }
}

