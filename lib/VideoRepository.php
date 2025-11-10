<?php
declare(strict_types=1);

namespace Lib;

use DateTimeImmutable;
use RuntimeException;

final class VideoRepository
{
    /**
     * Return all registered video sources ordered by creation date.
     */
    public function allSources(): array
    {
        return Database::fetchAll(
            'SELECT * FROM video_sources ORDER BY created_at DESC'
        );
    }

    /**
     * Retrieve a single source by ID.
     */
    public function findSource(int $id): ?array
    {
        return Database::fetch(
            'SELECT * FROM video_sources WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Persist a new source; throws when duplicate.
     */
    public function createSource(string $type, string $identifier, string $label, ?string $aiTopic = null): int
    {
        $type = strtolower($type);
        if (!in_array($type, ['channel', 'query'], true)) {
            throw new RuntimeException('Unsupported source type: ' . $type);
        }

        return Database::insert(
            'INSERT INTO video_sources (type, identifier, label, ai_topic) VALUES (:type, :identifier, :label, :ai_topic)',
            [
                ':type' => $type,
                ':identifier' => trim($identifier),
                ':label' => trim($label),
                ':ai_topic' => $aiTopic !== null ? trim($aiTopic) : null,
            ]
        );
    }

    /**
     * Remove a source; also detaches linked videos.
     */
    public function deleteSource(int $id): void
    {
        Database::execute('UPDATE videos SET source_id = NULL WHERE source_id = :id', [':id' => $id]);
        Database::execute('DELETE FROM video_sources WHERE id = :id', [':id' => $id]);
    }

    /**
     * Update fetch timestamp.
     */
    public function markSourceFetched(int $id): void
    {
        Database::execute(
            'UPDATE video_sources SET last_fetched_at = :now WHERE id = :id',
            [
                ':now' => (new DateTimeImmutable())->format(DATE_ATOM),
                ':id' => $id,
            ]
        );
    }

    /**
     * Upsert video entry using YouTube videoId as unique key.
     */
    public function upsertVideo(array $payload): void
    {
        Database::execute(
            <<<SQL
            INSERT INTO videos (
                source_id,
                video_id,
                title,
                description,
                thumbnail_url,
                channel_title,
                published_at,
                duration,
                ai_summary,
                ai_tags,
                ai_category
            ) VALUES (
                :source_id,
                :video_id,
                :title,
                :description,
                :thumbnail_url,
                :channel_title,
                :published_at,
                :duration,
                :ai_summary,
                :ai_tags,
                :ai_category
            )
            ON CONFLICT(video_id) DO UPDATE SET
                source_id = excluded.source_id,
                title = excluded.title,
                description = excluded.description,
                thumbnail_url = excluded.thumbnail_url,
                channel_title = excluded.channel_title,
                published_at = excluded.published_at,
                duration = excluded.duration,
                ai_summary = excluded.ai_summary,
                ai_tags = excluded.ai_tags,
                ai_category = excluded.ai_category
            ;
            SQL,
            [
                ':source_id' => $payload['source_id'] ?? null,
                ':video_id' => $payload['video_id'],
                ':title' => $payload['title'],
                ':description' => $payload['description'] ?? null,
                ':thumbnail_url' => $payload['thumbnail_url'] ?? null,
                ':channel_title' => $payload['channel_title'] ?? null,
                ':published_at' => $payload['published_at'] ?? null,
                ':duration' => $payload['duration'] ?? null,
                ':ai_summary' => $payload['ai_summary'] ?? null,
                ':ai_tags' => isset($payload['ai_tags']) ? json_encode($payload['ai_tags'], JSON_THROW_ON_ERROR) : null,
                ':ai_category' => $payload['ai_category'] ?? null,
            ]
        );
    }

    /**
     * Fetch videos with optional filters.
     */
    public function fetchVideos(?string $search = null, ?string $category = null, ?int $limit = null): array
    {
        $conditions = [];
        $parameters = [];

        if ($search) {
            $conditions[] = '(v.title LIKE :search OR v.description LIKE :search OR v.channel_title LIKE :search)';
            $parameters[':search'] = '%' . $search . '%';
        }

        if ($category) {
            $conditions[] = '(v.ai_category = :category OR s.label = :category)';
            $parameters[':category'] = $category;
        }

        $sql = <<<SQL
            SELECT v.*, s.label AS source_label
            FROM videos v
            LEFT JOIN video_sources s ON s.id = v.source_id
        SQL;

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY datetime(v.published_at) DESC NULLS LAST, v.created_at DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $videos = Database::fetchAll($sql, $parameters);

        foreach ($videos as &$video) {
            if (isset($video['ai_tags'])) {
                $decoded = json_decode((string) $video['ai_tags'], true);
                $video['ai_tags'] = is_array($decoded) ? $decoded : [];
            } else {
                $video['ai_tags'] = [];
            }
        }

        return $videos;
    }

    /**
     * Return the most recent N videos.
     */
    public function latest(int $limit = 12): array
    {
        return $this->fetchVideos(null, null, $limit);
    }

    /**
     * Retrieve distinct AI categories and source labels for filtering.
     */
    public function availableCollections(): array
    {
        $categories = Database::fetchAll(
            'SELECT DISTINCT ai_category AS name FROM videos WHERE ai_category IS NOT NULL AND ai_category != "" ORDER BY ai_category'
        );
        $sources = Database::fetchAll(
            'SELECT DISTINCT label AS name FROM video_sources ORDER BY label'
        );

        $collection = [];
        foreach ($categories as $row) {
            if ($row['name']) {
                $collection[] = [
                    'type' => 'ai_category',
                    'name' => $row['name'],
                ];
            }
        }

        foreach ($sources as $row) {
            if ($row['name']) {
                $collection[] = [
                    'type' => 'source',
                    'name' => $row['name'],
                ];
            }
        }

        return $collection;
    }
}
