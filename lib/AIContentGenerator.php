<?php
declare(strict_types=1);

namespace Lib;

use RuntimeException;

final class AIContentGenerator
{
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    private ?string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? env('OPENAI_API_KEY');
        $this->model = $model ?? (string) env('OPENAI_MODEL', self::DEFAULT_MODEL);
    }

    /**
     * Generate AI-driven metadata for a video.
     *
     * @param array<string, mixed> $video
     * @return array{summary:string,tags:array<int,string>,category:string}
     */
    public function generate(array $video, ?string $preferredTopic = null): array
    {
        if (!$this->apiKey) {
            return $this->fallback($video, $preferredTopic);
        }

        try {
            return $this->callOpenAI($video, $preferredTopic);
        } catch (\Throwable) {
            return $this->fallback($video, $preferredTopic);
        }
    }

    /**
     * Fallback metadata creation based on heuristics.
     *
     * @param array<string, mixed> $video
     * @return array{summary:string,tags:array<int,string>,category:string}
     */
    private function fallback(array $video, ?string $preferredTopic): array
    {
        $title = (string) ($video['title'] ?? '');
        $description = (string) ($video['description'] ?? '');

        $summary = $description ?: 'Auto-generated summary unavailable.';
        if (strlen($summary) > 240) {
            $summary = substr($summary, 0, 237) . '...';
        }

        $keywords = $this->extractKeywords($title . ' ' . $description);
        $category = $preferredTopic ?: ($keywords[0] ?? 'General');

        return [
            'summary' => $summary,
            'tags' => array_slice($keywords, 0, 6),
            'category' => $category,
        ];
    }

    /**
     * Invoke OpenAI Chat Completions endpoint to enrich video metadata.
     *
     * @param array<string, mixed> $video
     * @return array{summary:string,tags:array<int,string>,category:string}
     */
    private function callOpenAI(array $video, ?string $preferredTopic): array
    {
        $payload = [
            'model' => $this->model,
            'temperature' => 0.6,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an editorial AI that summarizes and categorizes streaming videos for a Netflix-style catalog. Respond ONLY with valid JSON matching the schema: {"summary": string, "tags": string[], "category": string}. Tags should be short keywords.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode(
                        [
                            'preferredTopic' => $preferredTopic,
                            'title' => $video['title'] ?? '',
                            'description' => $video['description'] ?? '',
                            'channel' => $video['channel_title'] ?? '',
                            'duration' => $video['duration'] ?? '',
                        ],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                    ),
                ],
            ],
        ];

        $response = $this->postJson('https://api.openai.com/v1/chat/completions', $payload);

        $content = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            throw new RuntimeException('Unexpected response from OpenAI.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OpenAI response was not valid JSON: ' . $content);
        }

        $summary = (string) ($decoded['summary'] ?? '');
        $tags = $decoded['tags'] ?? [];
        $category = (string) ($decoded['category'] ?? '');

        if (!$summary) {
            $summary = $this->fallback($video, $preferredTopic)['summary'];
        }

        if (!is_array($tags)) {
            $tags = [];
        }

        $tags = array_values(array_filter(array_map(static fn ($tag) => trim((string) $tag), $tags)));
        if (!$tags) {
            $tags = $this->extractKeywords(($video['title'] ?? '') . ' ' . ($video['description'] ?? ''));
        }

        if (!$category) {
            $category = $preferredTopic ?: ($tags[0] ?? 'General');
        }

        return [
            'summary' => $summary,
            'tags' => array_slice($tags, 0, 8),
            'category' => $category,
        ];
    }

    /**
     * Minimal HTTP JSON POST helper.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $url, array $payload): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException('OpenAI request failed: ' . $error);
        }

        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response from OpenAI: ' . substr($response, 0, 240));
        }

        if ($statusCode >= 400) {
            $message = $decoded['error']['message'] ?? ('HTTP ' . $statusCode);
            throw new RuntimeException('OpenAI API error: ' . $message);
        }

        return $decoded;
    }

    /**
     * Basic keyword extraction from free text.
     *
     * @return array<int, string>
     */
    private function extractKeywords(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? '';
        $words = preg_split('/\s+/', $text, flags: PREG_SPLIT_NO_EMPTY);

        $stopWords = [
            'the', 'a', 'an', 'and', 'or', 'of', 'for', 'with', 'to', 'in', 'on', 'at', 'by', 'from', 'is', 'are',
            'this', 'that', 'it', 'be', 'as', 'we', 'you', 'your', 'our', 'their', 'they', 'he', 'she', 'his', 'her',
            'them', 'was', 'were', 'will', 'would', 'should', 'can', 'could', 'about', 'into', 'across', 'over', 'into',
        ];

        $keywords = [];
        foreach ($words as $word) {
            if (strlen($word) < 3) {
                continue;
            }
            if (in_array($word, $stopWords, true)) {
                continue;
            }
            $keywords[$word] = ($keywords[$word] ?? 0) + 1;
        }

        arsort($keywords);

        return array_keys(array_slice($keywords, 0, 12, true));
    }
}
