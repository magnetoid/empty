<?php
declare(strict_types=1);

namespace Lib;

use RuntimeException;

final class Synchronizer
{
    private VideoRepository $repository;
    private AIContentGenerator $generator;
    private ?YouTubeClient $client;

    public function __construct(
        VideoRepository $repository,
        ?YouTubeClient $client = null,
        ?AIContentGenerator $generator = null
    ) {
        $this->repository = $repository;
        $this->client = $client;
        $this->generator = $generator ?? new AIContentGenerator();
    }

    /**
     * Synchronize videos for all sources or a specific source ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function synchronize(?int $sourceId = null, int $maxResults = 40): array
    {
        $sources = $sourceId !== null
            ? array_filter([ $this->repository->findSource($sourceId) ])
            : $this->repository->allSources();

        if (empty($sources)) {
            return [];
        }

        $client = $this->client;
        if (!$client) {
            $client = new YouTubeClient();
        }

        $results = [];

        foreach ($sources as $source) {
            $syncResult = [
                'source' => $source,
                'synced' => 0,
                'skipped' => 0,
                'errors' => [],
            ];

            try {
                $videos = $client->fetchVideosForSource($source, $maxResults);
            } catch (RuntimeException $exception) {
                $syncResult['errors'][] = $exception->getMessage();
                $results[] = $syncResult;
                continue;
            }

            foreach ($videos as $video) {
                if (empty($video['video_id'])) {
                    $syncResult['skipped']++;
                    continue;
                }

                try {
                    $ai = $this->generator->generate($video, $source['ai_topic'] ?? null);
                } catch (\Throwable $exception) {
                    $ai = [
                        'summary' => $video['description'] ?? '',
                        'tags' => [],
                        'category' => $source['ai_topic'] ?? 'General',
                    ];
                    $syncResult['errors'][] = 'AI enrichment fallback: ' . $exception->getMessage();
                }

                $this->repository->upsertVideo([
                    'source_id' => $source['id'] ?? null,
                    'video_id' => $video['video_id'],
                    'title' => $video['title'] ?? '',
                    'description' => $video['description'] ?? '',
                    'thumbnail_url' => $video['thumbnail_url'] ?? null,
                    'channel_title' => $video['channel_title'] ?? '',
                    'published_at' => $video['published_at'] ?? null,
                    'duration' => $video['duration'] ?? null,
                    'ai_summary' => $ai['summary'],
                    'ai_tags' => $ai['tags'],
                    'ai_category' => $ai['category'],
                ]);

                $syncResult['synced']++;
            }

            $this->repository->markSourceFetched((int) $source['id']);
            $results[] = $syncResult;
        }

        return $results;
    }
}
