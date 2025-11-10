<?php
declare(strict_types=1);

namespace Lib;

use RuntimeException;

final class YouTubeClient
{
    private const API_BASE = 'https://www.googleapis.com/youtube/v3/';

    private string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $apiKey ??= env('YOUTUBE_API_KEY');

        if (!$apiKey) {
            throw new RuntimeException('Missing YOUTUBE_API_KEY environment variable.');
        }

        $this->apiKey = $apiKey;
    }

    /**
     * Fetch videos based on a configured source definition.
     *
     * @param array{type:string,identifier:string,label:string,ai_topic:?string,id?:int} $source
     * @return array<int, array<string, mixed>>
     */
    public function fetchVideosForSource(array $source, int $maxResults = 40): array
    {
        $type = $source['type'] ?? 'query';
        $identifier = $source['identifier'] ?? '';

        if ($type === 'channel') {
            return $this->fetchByChannel($identifier, $maxResults);
        }

        return $this->fetchByQuery($identifier, $maxResults);
    }

    /**
     * Fetch videos from a specific channel ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchByChannel(string $channelId, int $maxResults = 40): array
    {
        $params = [
            'part' => 'snippet',
            'channelId' => $channelId,
            'type' => 'video',
            'order' => 'date',
        ];

        return $this->searchAndHydrate($params, $maxResults);
    }

    /**
     * Free-form search query against YouTube videos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchByQuery(string $query, int $maxResults = 40): array
    {
        $params = [
            'part' => 'snippet',
            'q' => $query,
            'type' => 'video',
            'order' => 'relevance',
        ];

        return $this->searchAndHydrate($params, $maxResults);
    }

    /**
     * Perform search then hydrate videos with detailed metadata.
     *
     * @param array<string, string> $params
     * @return array<int, array<string, mixed>>
     */
    private function searchAndHydrate(array $params, int $maxResults): array
    {
        $videoIds = [];
        $pageToken = null;

        while (count($videoIds) < $maxResults) {
            $batchSize = min(50, $maxResults - count($videoIds));
            $searchParams = array_merge($params, [
                'maxResults' => (string) $batchSize,
            ]);

            if ($pageToken) {
                $searchParams['pageToken'] = $pageToken;
            }

            $response = $this->request('search', $searchParams);
            foreach ($response['items'] ?? [] as $item) {
                $videoId = $item['id']['videoId'] ?? null;
                if ($videoId && !in_array($videoId, $videoIds, true)) {
                    $videoIds[] = $videoId;
                }
            }

            $pageToken = $response['nextPageToken'] ?? null;
            if (!$pageToken || empty($response['items'])) {
                break;
            }
        }

        if (empty($videoIds)) {
            return [];
        }

        return $this->hydrateVideos($videoIds);
    }

    /**
     * Retrieve video metadata from the YouTube Videos endpoint.
     *
     * @param array<int, string> $videoIds
     * @return array<int, array<string, mixed>>
     */
    private function hydrateVideos(array $videoIds): array
    {
        $videos = [];

        foreach (array_chunk($videoIds, 50) as $chunk) {
            $response = $this->request('videos', [
                'part' => 'snippet,contentDetails,statistics',
                'id' => implode(',', $chunk),
            ]);

            foreach ($response['items'] ?? [] as $item) {
                $snippet = $item['snippet'] ?? [];
                $contentDetails = $item['contentDetails'] ?? [];
                $thumbnails = $snippet['thumbnails'] ?? [];

                $videos[] = [
                    'video_id' => $item['id'] ?? null,
                    'title' => $snippet['title'] ?? '',
                    'description' => $snippet['description'] ?? '',
                    'thumbnail_url' => $this->resolveThumbnail($thumbnails),
                    'channel_title' => $snippet['channelTitle'] ?? '',
                    'published_at' => $snippet['publishedAt'] ?? null,
                    'duration' => $contentDetails['duration'] ?? null,
                ];
            }
        }

        usort(
            $videos,
            static fn ($a, $b) => strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? ''))
        );

        return $videos;
    }

    /**
     * Resolve the highest-quality thumbnail available.
     *
     * @param array<string, array<string, mixed>> $thumbnails
     */
    private function resolveThumbnail(array $thumbnails): ?string
    {
        $preferredKeys = ['maxres', 'standard', 'high', 'medium', 'default'];

        foreach ($preferredKeys as $key) {
            if (isset($thumbnails[$key]['url'])) {
                return $thumbnails[$key]['url'];
            }
        }

        return null;
    }

    /**
     * Wrapper around cURL requests to YouTube Data API.
     *
     * @param array<string, string> $parameters
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $parameters): array
    {
        $parameters['key'] = $this->apiKey;
        $url = self::API_BASE . $endpoint . '?' . http_build_query($parameters);

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException('YouTube API request failed: ' . $error);
        }

        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid response from YouTube API: ' . substr($response, 0, 240));
        }

        if ($statusCode >= 400) {
            $message = $decoded['error']['message'] ?? 'HTTP ' . $statusCode;
            throw new RuntimeException('YouTube API error: ' . $message);
        }

        return $decoded;
    }
}
