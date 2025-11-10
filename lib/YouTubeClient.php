<?php

declare(strict_types=1);

require_once BASE_PATH . '/bootstrap.php';

class YouTubeClient
{
    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? getSetting('youtube_api_key') ?? env('YOUTUBE_API_KEY');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Search for videos by keyword.
     *
     * @return array{items: array<int, array>, pageInfo: array<string, mixed>, nextPageToken?: string}
     */
    public function searchVideos(string $query, int $maxResults = 20, ?string $publishedAfter = null, ?string $pageToken = null): array
    {
        $params = [
            'part' => 'snippet',
            'type' => 'video',
            'q' => $query,
            'maxResults' => min(max($maxResults, 1), 50),
            'key' => $this->requireKey(),
            'order' => 'date',
        ];

        if ($publishedAfter) {
            $params['publishedAfter'] = $publishedAfter;
        }

        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        return $this->get('https://www.googleapis.com/youtube/v3/search', $params);
    }

    /**
     * Fetch videos for a channel.
     */
    public function fetchChannelVideos(string $channelId, int $maxResults = 20, ?string $pageToken = null): array
    {
        $params = [
            'part' => 'snippet',
            'channelId' => $channelId,
            'type' => 'video',
            'order' => 'date',
            'maxResults' => min(max($maxResults, 1), 50),
            'key' => $this->requireKey(),
        ];

        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        return $this->get('https://www.googleapis.com/youtube/v3/search', $params);
    }

    /**
     * Fetch detailed metadata for a set of video IDs.
     *
     * @param array<int, string> $videoIds
     */
    public function fetchVideoDetails(array $videoIds): array
    {
        if (empty($videoIds)) {
            return ['items' => []];
        }

        $chunks = array_chunk($videoIds, 50);
        $results = ['items' => []];

        foreach ($chunks as $chunk) {
            $params = [
                'part' => 'snippet,contentDetails,statistics',
                'id' => implode(',', $chunk),
                'key' => $this->requireKey(),
            ];

            $response = $this->get('https://www.googleapis.com/youtube/v3/videos', $params);
            $results['items'] = array_merge($results['items'], $response['items'] ?? []);
        }

        return $results;
    }

    /**
     * Save an updated API key to the database.
     */
    public function persistApiKey(string $apiKey): void
    {
        $this->apiKey = trim($apiKey);
        setSetting('youtube_api_key', $this->apiKey);
    }

    private function requireKey(): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException(
                'YouTube API key missing. Set YOUTUBE_API_KEY in .env or via the admin settings.'
            );
        }

        return (string) $this->apiKey;
    }

    private function get(string $url, array $params): array
    {
        $query = http_build_query($params);
        $endpoint = $url . '?' . $query;

        $handle = curl_init($endpoint);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($handle);
        if ($body === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException("YouTube API request failed: {$error}");
        }

        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('YouTube API returned invalid JSON.');
        }

        if ($statusCode >= 400) {
            $message = $data['error']['message'] ?? 'Unknown error';
            throw new RuntimeException("YouTube API error ({$statusCode}): {$message}");
        }

        return $data;
    }
}

