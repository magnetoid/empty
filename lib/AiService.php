<?php

declare(strict_types=1);

require_once BASE_PATH . '/bootstrap.php';

class AiService
{
    private ?string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, string $model = 'gpt-4o-mini')
    {
        $this->apiKey = $apiKey ?? getSetting('openai_api_key') ?? env('OPENAI_API_KEY');
        $this->model = $model;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function persistApiKey(string $apiKey): void
    {
        $this->apiKey = trim($apiKey);
        setSetting('openai_api_key', $this->apiKey);
    }

    /**
     * Analyze a video and return AI-enriched metadata.
     *
     * @return array{category: string, summary: string|null, topics: array<int, string>}
     */
    public function analyzeVideo(string $title, ?string $description = null): array
    {
        $prompt = $this->buildPrompt($title, $description);

        if ($this->isConfigured()) {
            $response = $this->callOpenAi($prompt);
            if ($response !== null) {
                return $response;
            }
        }

        return $this->fallbackHeuristics($title, $description);
    }

    private function buildPrompt(string $title, ?string $description): string
    {
        $body = trim(sprintf(
            "Title: %s\n\nDescription: %s",
            $title,
            $description ?: 'n/a'
        ));

        $instructions = <<<PROMPT
You are an editorial assistant for a streaming video catalog. Analyze the following YouTube video metadata and respond with JSON that includes:
- "category": a short genre-style label (e.g., "Tech Explained", "Lifestyle", "Music").
- "summary": 1-2 sentences summarizing the video for viewers.
- "topics": an array of 3-5 concise topical tags.

Be concise and ensure the JSON is valid.
PROMPT;

        return $instructions . "\n\n" . $body;
    }

    /**
     * @return array{category: string, summary: string|null, topics: array<int, string>}|null
     */
    private function callOpenAi(string $prompt): ?array
    {
        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        ['type' => 'text', 'text' => 'You are a helpful assistant that only outputs JSON.'],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'video_enrichment',
                    'schema' => [
                        'type' => 'object',
                        'required' => ['category', 'summary', 'topics'],
                        'properties' => [
                            'category' => ['type' => 'string'],
                            'summary' => ['type' => ['string', 'null']],
                            'topics' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'minItems' => 3,
                                'maxItems' => 5,
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];

        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            curl_close($ch);
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $output = $data['output'] ?? $data['response'] ?? null;
        if (is_array($output)) {
            $content = $output[0]['content'][0]['text'] ?? null;
        } else {
            $content = $data['content'][0]['text'] ?? ($data['output_text'] ?? null);
        }

        if (!is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return null;
        }

        $category = trim((string) ($decoded['category'] ?? 'Uncategorized'));
        $summary = isset($decoded['summary']) ? trim((string) $decoded['summary']) : null;
        $topics = array_values(array_filter(
            array_map('trim', (array) ($decoded['topics'] ?? []))
        ));

        if ($category === '') {
            $category = 'Uncategorized';
        }

        if (empty($topics)) {
            $topics = $this->inferTopicsFromText($summary ?? '');
        }

        return [
            'category' => $category,
            'summary' => $summary ?: null,
            'topics' => $topics,
        ];
    }

    /**
     * Fallback to lightweight heuristics when no AI key is configured.
     *
     * @return array{category: string, summary: string|null, topics: array<int, string>}
     */
    private function fallbackHeuristics(string $title, ?string $description): array
    {
        $text = mb_strtolower($title . ' ' . ($description ?? ''));

        $categories = [
            'Tech & Coding' => ['developer', 'coding', 'program', 'software', 'tech', 'technology', 'ai', 'machine learning'],
            'Gaming' => ['game', 'gaming', 'let\'s play', 'walkthrough'],
            'Music' => ['music', 'song', 'album', 'remix', 'cover'],
            'Lifestyle' => ['vlog', 'lifestyle', 'travel', 'food', 'recipe'],
            'Education' => ['tutorial', 'lesson', 'how to', 'educat', 'class', 'course'],
            'News & Politics' => ['news', 'breaking', 'politic', 'election'],
            'Sports & Fitness' => ['sport', 'fitness', 'workout', 'training', 'match'],
        ];

        $selectedCategory = 'Uncategorized';
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $selectedCategory = $category;
                    break 2;
                }
            }
        }

        $topics = array_slice($this->inferTopicsFromText($title . ' ' . ($description ?? '')), 0, 5);

        return [
            'category' => $selectedCategory,
            'summary' => $this->summarizeLocally($title, $description),
            'topics' => $topics,
        ];
    }

    /**
     * Basic topic extraction by keyword frequency.
     *
     * @return array<int, string>
     */
    private function inferTopicsFromText(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text, flags: PREG_SPLIT_NO_EMPTY);

        $stopWords = [
            'the', 'and', 'with', 'from', 'this', 'that', 'you', 'your', 'about',
            'into', 'what', 'when', 'where', 'will', 'have', 'just', 'into', 'for',
            'but', 'are', 'was', 'were', 'how', 'why', 'who', 'they', 'them', 'their',
            'its', 'more', 'like', 'over', 'into', 'also', 'into', 'than', 'then',
            'after', 'before', 'every', 'make', 'made', 'into', 'very', 'much', 'many',
        ];

        $freq = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 4 || in_array($word, $stopWords, true)) {
                continue;
            }
            $freq[$word] = ($freq[$word] ?? 0) + 1;
        }

        arsort($freq);

        return array_keys(array_slice($freq, 0, 5, true));
    }

    private function summarizeLocally(string $title, ?string $description): string
    {
        if (!$description) {
            return $title;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', trim($description));
        if (!$sentences) {
            return $title;
        }

        $summary = array_slice($sentences, 0, 2);

        return implode(' ', $summary);
    }
}

