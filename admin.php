<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/bootstrap.php';
require_once BASE_PATH . '/lib/YouTubeClient.php';
require_once BASE_PATH . '/lib/AiService.php';
require_once BASE_PATH . '/lib/VideoRepository.php';
require_once BASE_PATH . '/services/IngestionService.php';

$youtubeClient = new YouTubeClient();
$aiService = new AiService();
$repository = new VideoRepository();
$ingestionService = new IngestionService($youtubeClient, $aiService, $repository);

$adminPassword = env('ADMIN_PASSWORD');

if ($adminPassword) {
    if (isset($_GET['logout'])) {
        unset($_SESSION['admin_authenticated']);
        header('Location: admin.php');
        exit;
    }

    if (!($_SESSION['admin_authenticated'] ?? false)) {
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
            $password = $_POST['password'] ?? '';
            if (hash_equals($adminPassword, $password)) {
                $_SESSION['admin_authenticated'] = true;
                header('Location: admin.php');
                exit;
            }

            $error = 'Invalid password.';
        }

        echo renderLoginScreen($error);
        exit;
    }
}

$messages = [];
$errors = [];
$ingestionOutput = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save_keys':
                $youtubeKey = trim($_POST['youtube_api_key'] ?? '');
                $openAiKey = trim($_POST['openai_api_key'] ?? '');

                if ($youtubeKey !== '') {
                    $youtubeClient->persistApiKey($youtubeKey);
                    $messages[] = 'YouTube Data API key saved.';
                }

                if ($openAiKey !== '') {
                    $aiService->persistApiKey($openAiKey);
                    $messages[] = 'OpenAI API key saved.';
                }

                if ($youtubeKey === '' && $openAiKey === '') {
                    $messages[] = 'No changes were made to API keys.';
                }
                break;

            case 'ingest_query':
                $query = trim($_POST['query'] ?? '');
                $collection = trim($_POST['collection'] ?? '');
                $limit = sanitizeLimit($_POST['limit'] ?? 10);

                if ($query === '') {
                    throw new InvalidArgumentException('Search query is required.');
                }

                $ingestionOutput = $ingestionService->ingestSearch(
                    $query,
                    $limit,
                    $collection ?: null
                );

                $messages[] = sprintf(
                    'Ingested %d videos (%d skipped).',
                    $ingestionOutput['ingested'],
                    $ingestionOutput['skipped']
                );
                break;

            case 'ingest_channel':
                $channelId = trim($_POST['channel_id'] ?? '');
                $collection = trim($_POST['collection'] ?? '');
                $limit = sanitizeLimit($_POST['limit'] ?? 10);

                if ($channelId === '') {
                    throw new InvalidArgumentException('Channel ID is required.');
                }

                $ingestionOutput = $ingestionService->ingestChannel(
                    $channelId,
                    $limit,
                    $collection ?: null
                );

                $messages[] = sprintf(
                    'Ingested %d videos (%d skipped).',
                    $ingestionOutput['ingested'],
                    $ingestionOutput['skipped']
                );
                break;

            default:
                if ($action !== 'login') {
                    $errors[] = 'Unknown action.';
                }
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$totalVideos = (int) db()->query('SELECT COUNT(*) FROM videos')->fetchColumn();
$latestVideo = db()->query('SELECT title, published_at FROM videos ORDER BY published_at DESC LIMIT 1')->fetch();
$categoriesStmt = db()->query(
    'SELECT ai_category AS name, COUNT(*) AS total
     FROM videos WHERE ai_category IS NOT NULL
     GROUP BY ai_category
     ORDER BY total DESC'
);
$categories = $categoriesStmt ? $categoriesStmt->fetchAll() : [];
$recentVideos = $repository->getRecentVideos(12);

echo renderAdminPage([
    'messages' => $messages,
    'errors' => array_merge($errors, $ingestionOutput['errors'] ?? []),
    'logs' => $ingestionOutput['logs'] ?? [],
    'stats' => [
        'totalVideos' => $totalVideos,
        'latestTitle' => $latestVideo['title'] ?? null,
        'latestPublishedAt' => $latestVideo['published_at'] ?? null,
        'youtubeConfigured' => $youtubeClient->isConfigured(),
        'aiConfigured' => $aiService->isConfigured(),
        'categories' => $categories,
    ],
    'recentVideos' => $recentVideos,
]);

function sanitizeLimit(mixed $value): int
{
    $limit = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => [
            'default' => 10,
            'min_range' => 1,
            'max_range' => 50,
        ],
    ]);

    return $limit ?: 10;
}

function renderLoginScreen(?string $error): string
{
    ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin auth">
    <div class="login-card">
        <h1>CMS Admin</h1>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <label>
                <span>Password</span>
                <input type="password" name="password" required>
            </label>
            <button type="submit">Sign in</button>
        </form>
    </div>
</body>
</html>
<?php
    return (string) ob_get_clean();
}

function renderAdminPage(array $data): string
{
    $messages = $data['messages'];
    $errors = $data['errors'];
    $logs = $data['logs'];
    $stats = $data['stats'];
    $recentVideos = $data['recentVideos'];

    ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Video CMS Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin">
    <header class="admin-header">
        <h1>AI Video CMS Dashboard</h1>
        <nav>
            <a href="index.php" target="_blank">View frontdoor</a>
            <?php if (env('ADMIN_PASSWORD')): ?>
                <a href="?logout=1">Sign out</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="admin-content">
        <?php foreach ($messages as $message): ?>
            <div class="alert success"><?= htmlspecialchars($message, ENT_QUOTES) ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endforeach; ?>

        <section class="grid stats">
            <article>
                <h2>Total videos</h2>
                <p class="metric"><?= number_format($stats['totalVideos']) ?></p>
                <?php if ($stats['latestTitle']): ?>
                    <p class="muted">Latest: <?= htmlspecialchars($stats['latestTitle'], ENT_QUOTES) ?></p>
                    <p class="muted">Published: <?= htmlspecialchars($stats['latestPublishedAt'] ?? '-', ENT_QUOTES) ?></p>
                <?php endif; ?>
            </article>
            <article>
                <h2>Integrations</h2>
                <ul class="status-list">
                    <li class="<?= $stats['youtubeConfigured'] ? 'ok' : 'warn' ?>">
                        YouTube API <?= $stats['youtubeConfigured'] ? 'connected' : 'missing' ?>
                    </li>
                    <li class="<?= $stats['aiConfigured'] ? 'ok' : 'warn' ?>">
                        AI enrichment <?= $stats['aiConfigured'] ? 'ready' : 'using heuristics' ?>
                    </li>
                </ul>
            </article>
            <article>
                <h2>AI categories</h2>
                <?php if (empty($stats['categories'])): ?>
                    <p class="muted">Ingest content to see categories.</p>
                <?php else: ?>
                    <ul class="category-list">
                        <?php foreach ($stats['categories'] as $row): ?>
                            <li>
                                <span><?= htmlspecialchars($row['name'] ?? 'Uncategorized', ENT_QUOTES) ?></span>
                                <span><?= number_format($row['total']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </section>

        <section class="card">
            <h2>API keys</h2>
            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="save_keys">
                <label>
                    <span>YouTube Data API key</span>
                    <input type="text" name="youtube_api_key" placeholder="AIza..." value="">
                </label>
                <label>
                    <span>OpenAI API key (optional)</span>
                    <input type="text" name="openai_api_key" placeholder="sk-..." value="">
                </label>
                <p class="muted">Keys are stored in the local SQLite database. Leave blank to keep existing values.</p>
                <button type="submit">Save keys</button>
            </form>
        </section>

        <section class="grid ingest">
            <article class="card">
                <h2>Crawl by keyword</h2>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="ingest_query">
                    <label>
                        <span>Search query</span>
                        <input type="text" name="query" placeholder="e.g. AI documentary" required>
                    </label>
                    <label>
                        <span>Collection (optional)</span>
                        <input type="text" name="collection" placeholder="Custom collection name">
                    </label>
                    <label>
                        <span>Max results</span>
                        <input type="number" name="limit" min="1" max="50" value="10">
                    </label>
                    <button type="submit">Run search crawler</button>
                </form>
            </article>

            <article class="card">
                <h2>Crawl by channel</h2>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="ingest_channel">
                    <label>
                        <span>Channel ID</span>
                        <input type="text" name="channel_id" placeholder="UC_x5XG1OV2P6uZZ5FSM9Ttw" required>
                    </label>
                    <label>
                        <span>Collection (optional)</span>
                        <input type="text" name="collection" placeholder="Custom collection name">
                    </label>
                    <label>
                        <span>Max results</span>
                        <input type="number" name="limit" min="1" max="50" value="10">
                    </label>
                    <button type="submit">Run channel crawler</button>
                </form>
            </article>
        </section>

        <?php if (!empty($logs)): ?>
            <section class="card logs">
                <h2>Ingestion log</h2>
                <ul>
                    <?php foreach ($logs as $log): ?>
                        <li><?= htmlspecialchars($log, ENT_QUOTES) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2>Recently ingested</h2>
            <div class="recent-grid">
                <?php if (empty($recentVideos)): ?>
                    <p class="muted">No videos yet. Run a crawl to populate the catalog.</p>
                <?php else: ?>
                    <?php foreach ($recentVideos as $video): ?>
                        <article class="recent-card">
                            <div class="thumb" style="background-image: url('<?= htmlspecialchars(bestThumbnail($video), ENT_QUOTES) ?>');"></div>
                            <div class="meta">
                                <h3><?= htmlspecialchars($video['title'], ENT_QUOTES) ?></h3>
                                <p class="muted"><?= htmlspecialchars($video['ai_category'] ?? 'Uncategorized', ENT_QUOTES) ?></p>
                                <p class="muted"><?= htmlspecialchars($video['published_at'] ?? '-', ENT_QUOTES) ?></p>
                            </div>
                            <a class="open" href="https://www.youtube.com/watch?v=<?= htmlspecialchars($video['youtube_id'], ENT_QUOTES) ?>" target="_blank" rel="noopener">Open YouTube</a>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
<?php
    return (string) ob_get_clean();
}

function bestThumbnail(array $video): string
{
    $thumbs = $video['thumbnails'] ?? [];
    $order = ['maxres', 'standard', 'high', 'medium', 'default'];

    foreach ($order as $key) {
        if (!empty($thumbs[$key]['url'])) {
            return $thumbs[$key]['url'];
        }
    }

    return 'https://img.youtube.com/vi/' . urlencode($video['youtube_id']) . '/hqdefault.jpg';
}

