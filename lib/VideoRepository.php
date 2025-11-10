<?php

declare(strict_types=1);

require_once BASE_PATH . '/bootstrap.php';

class VideoRepository
{
    public function upsertVideo(array $payload, array $collections = []): array
    {
        $pdo = db();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO videos (
                    youtube_id, title, description, channel_title, published_at,
                    thumbnails, duration, tags, ai_category, ai_summary, ai_topics,
                    collections_cache, created_at, updated_at
                ) VALUES (
                    :youtube_id, :title, :description, :channel_title, :published_at,
                    :thumbnails, :duration, :tags, :ai_category, :ai_summary, :ai_topics,
                    :collections_cache, :created_at, :updated_at
                )
                ON CONFLICT(youtube_id) DO UPDATE SET
                    title = excluded.title,
                    description = excluded.description,
                    channel_title = excluded.channel_title,
                    published_at = excluded.published_at,
                    thumbnails = excluded.thumbnails,
                    duration = excluded.duration,
                    tags = excluded.tags,
                    ai_category = excluded.ai_category,
                    ai_summary = excluded.ai_summary,
                    ai_topics = excluded.ai_topics,
                    collections_cache = excluded.collections_cache,
                    updated_at = excluded.updated_at'
            );

            $now = gmdate('c');
            $stmt->execute([
                ':youtube_id' => $payload['youtube_id'],
                ':title' => $payload['title'],
                ':description' => $payload['description'],
                ':channel_title' => $payload['channel_title'] ?? null,
                ':published_at' => $payload['published_at'] ?? null,
                ':thumbnails' => json_encode($payload['thumbnails'] ?? [], JSON_UNESCAPED_SLASHES),
                ':duration' => $payload['duration'] ?? null,
                ':tags' => json_encode($payload['tags'] ?? [], JSON_UNESCAPED_UNICODE),
                ':ai_category' => $payload['ai_category'] ?? null,
                ':ai_summary' => $payload['ai_summary'] ?? null,
                ':ai_topics' => json_encode($payload['ai_topics'] ?? [], JSON_UNESCAPED_UNICODE),
                ':collections_cache' => json_encode(array_values($collections), JSON_UNESCAPED_UNICODE),
                ':created_at' => $payload['created_at'] ?? $now,
                ':updated_at' => $now,
            ]);

            $videoId = $this->findIdByYoutubeId($payload['youtube_id']);

            if (!empty($collections)) {
                $this->syncCollections($videoId, $collections);
            }

            $pdo->commit();

            return $this->findById($videoId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function findById(int $id): array
    {
        $stmt = db()->prepare('SELECT * FROM videos WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $record = $stmt->fetch();

        if (!$record) {
            throw new RuntimeException('Video not found.');
        }

        return $this->hydrate($record);
    }

    public function findByYoutubeId(string $youtubeId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM videos WHERE youtube_id = :youtube_id');
        $stmt->execute([':youtube_id' => $youtubeId]);
        $record = $stmt->fetch();

        return $record ? $this->hydrate($record) : null;
    }

    public function findIdByYoutubeId(string $youtubeId): int
    {
        $stmt = db()->prepare('SELECT id FROM videos WHERE youtube_id = :youtube_id');
        $stmt->execute([':youtube_id' => $youtubeId]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("Video with youtube_id {$youtubeId} not found.");
        }

        return (int) $id;
    }

    /**
     * Fetch homepage collections grouped by AI category.
     *
     * @return array<int, array{name: string, slug: string, videos: array<int, array>, hero?: array}>
     */
    public function getHomepageData(): array
    {
        $pdo = db();
        $stmt = $pdo->query(
            'SELECT * FROM videos WHERE ai_category IS NOT NULL
             ORDER BY published_at DESC'
        );

        $groups = [];
        while ($row = $stmt->fetch()) {
            $video = $this->hydrate($row);
            $category = $video['ai_category'] ?: 'Fresh Picks';
            $slug = slugify($category);

            if (!isset($groups[$slug])) {
                $groups[$slug] = [
                    'name' => $category,
                    'slug' => $slug,
                    'videos' => [],
                ];
            }

            $groups[$slug]['videos'][] = $video;
        }

        return array_values(array_map(function ($group) {
            $group['hero'] = $group['videos'][0] ?? null;
            return $group;
        }, $groups));
    }

    /**
     * Return the most recently ingested videos.
     *
     * @return array<int, array>
     */
    public function getRecentVideos(int $limit = 40): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM videos ORDER BY published_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $videos = [];
        while ($row = $stmt->fetch()) {
            $videos[] = $this->hydrate($row);
        }

        return $videos;
    }

    /**
     * Synchronize the link table for collections.
     *
     * @param array<int, string> $collectionNames
     */
    private function syncCollections(int $videoId, array $collectionNames): void
    {
        $pdo = db();

        $stmtDelete = $pdo->prepare('DELETE FROM collection_videos WHERE video_id = :video_id');
        $stmtDelete->execute([':video_id' => $videoId]);

        foreach ($collectionNames as $position => $name) {
            $collection = $this->firstOrCreateCollection($name);
            $stmt = $pdo->prepare(
                'INSERT INTO collection_videos (collection_id, video_id, position)
                 VALUES (:collection_id, :video_id, :position)'
            );
            $stmt->execute([
                ':collection_id' => $collection['id'],
                ':video_id' => $videoId,
                ':position' => $position,
            ]);
        }
    }

    private function firstOrCreateCollection(string $name): array
    {
        $slug = slugify($name);

        $stmt = db()->prepare('SELECT * FROM collections WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        $existing = $stmt->fetch();

        if ($existing) {
            return $existing;
        }

        $stmt = db()->prepare(
            'INSERT INTO collections (name, slug, description, created_at, updated_at)
             VALUES (:name, :slug, :description, :created_at, :updated_at)'
        );

        $now = gmdate('c');
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return $this->firstOrCreateCollection($name);
    }

    private function hydrate(array $record): array
    {
        $record['thumbnails'] = $this->decodeJson($record['thumbnails']);
        $record['tags'] = $this->decodeJson($record['tags']);
        $record['ai_topics'] = $this->decodeJson($record['ai_topics']);
        $record['collections_cache'] = $this->decodeJson($record['collections_cache']);

        return $record;
    }

    private function decodeJson(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}

