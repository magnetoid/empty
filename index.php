<?php
declare(strict_types=1);

$dbPath = __DIR__ . '/data/videos.db';
$databaseReady = file_exists($dbPath);
$videos = [];
$channels = [];
$availableTags = [];
$latestCrawl = null;
$error = null;

if ($databaseReady) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
        $channel = isset($_GET['channel']) ? trim((string) $_GET['channel']) : '';
        $tag = isset($_GET['tag']) ? trim((string) $_GET['tag']) : '';

        $conditions = [];
        $params = [];

        if ($search !== '') {
            $conditions[] = '(title LIKE :search OR description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($channel !== '') {
            $conditions[] = 'channel = :channel';
            $params[':channel'] = $channel;
        }
        if ($tag !== '') {
            $conditions[] = 'ai_tags LIKE :tag';
            $params[':tag'] = '%' . $tag . '%';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = <<<SQL
            SELECT *
            FROM videos
            $where
            ORDER BY COALESCE(published_at, crawled_at) DESC
            LIMIT 200
        SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $channelStmt = $pdo->query(
            "SELECT DISTINCT channel FROM videos WHERE channel IS NOT NULL AND channel != '' ORDER BY channel"
        );
        $channels = $channelStmt ? $channelStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        $tagStmt = $pdo->query(
            "SELECT ai_tags FROM videos WHERE ai_tags IS NOT NULL AND ai_tags != ''"
        );
        if ($tagStmt) {
            $tagSet = [];
            foreach ($tagStmt->fetchAll(PDO::FETCH_COLUMN) as $tagString) {
                $parts = array_map('trim', explode(',', $tagString));
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $tagSet[$part] = true;
                    }
                }
            }
            $availableTags = array_keys($tagSet);
            sort($availableTags);
        }

        $latestStmt = $pdo->query("SELECT MAX(crawled_at) FROM videos");
        $latestCrawl = $latestStmt ? $latestStmt->fetchColumn() : null;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>NovaStream | AI Video CMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <div class="layout">
        <header class="header">
            <div class="brand">
                <div class="brand-logo">N</div>
                <div>
                    <h1>NovaStream CMS</h1>
                    <span>AI-curated catalogue of manually crawled YouTube videos</span>
                </div>
            </div>
            <section class="filters">
                <form method="get">
                    <div class="input-group">
                        <label for="search">Search</label>
                        <input
                            type="search"
                            id="search"
                            name="search"
                            placeholder="Titles, descriptions, keywords..."
                            value="<?= h($_GET['search'] ?? '') ?>"
                        />
                    </div>
                    <div class="input-group">
                        <label for="channel">Channel</label>
                        <select id="channel" name="channel">
                            <option value="">All channels</option>
                            <?php foreach ($channels as $option): ?>
                                <option value="<?= h($option) ?>" <?= ($option === ($_GET['channel'] ?? '')) ? 'selected' : '' ?>>
                                    <?= h($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="tag">AI Tag</label>
                        <select id="tag" name="tag">
                            <option value="">All tags</option>
                            <?php foreach ($availableTags as $option): ?>
                                <option value="<?= h($option) ?>" <?= ($option === ($_GET['tag'] ?? '')) ? 'selected' : '' ?>>
                                    <?= h($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="actions">
                        <button class="button" type="submit">Filter</button>
                    </div>
                </form>
            </section>
            <div class="meta-bar">
                <?php if (!$databaseReady): ?>
                    <span class="meta-chip">Database not initialised. Run the crawler to populate data.</span>
                <?php elseif ($error): ?>
                    <span class="meta-chip">Error: <?= h($error) ?></span>
                <?php else: ?>
                    <span class="meta-chip"><?= count($videos) ?> videos</span>
                    <?php if ($latestCrawl): ?>
                        <span class="timestamp meta-chip">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 6v6l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
                            </svg>
                            Last crawl <?= h($latestCrawl) ?>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!$databaseReady): ?>
            <div class="empty-state">
                <p>The video catalogue is empty. Use <code>python -m crawler.run_crawler</code> to start scraping.</p>
            </div>
        <?php elseif ($error): ?>
            <div class="empty-state">
                <p>We ran into an issue connecting to the database:</p>
                <p><strong><?= h($error) ?></strong></p>
            </div>
        <?php elseif (!$videos): ?>
            <div class="empty-state">
                <p>No videos match your filters yet. Try adjusting your search or running the crawler again.</p>
            </div>
        <?php else: ?>
            <main class="grid">
                <?php foreach ($videos as $video): ?>
                    <article class="card">
                        <?php
                            $watchUrl = $video['watch_url'] ?? '';
                            $thumbnailUrl = $video['thumbnail_url']
                                ?: ('https://i.ytimg.com/vi/' . ($video['video_id'] ?? '') . '/hqdefault.jpg');
                        ?>
                        <a href="<?= h($watchUrl) ?>" target="_blank" rel="noopener">
                            <img
                                src="<?= h($thumbnailUrl) ?>"
                                alt="<?= h($video['title']) ?>"
                                loading="lazy"
                            />
                        </a>
                        <div class="card-body">
                            <h2 class="card-title"><?= h($video['title']) ?></h2>
                            <?php if (!empty($video['channel'])): ?>
                                <div class="card-channel"><?= h($video['channel']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($video['ai_tags'])): ?>
                                <div class="card-tags">
                                    <?php foreach (explode(',', $video['ai_tags']) as $tagName): ?>
                                        <span class="tag"><?= h(trim($tagName)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($video['ai_summary'])): ?>
                                <p class="summary"><?= h($video['ai_summary']) ?></p>
                            <?php elseif (!empty($video['description'])): ?>
                                <p class="summary"><?= h(mb_strimwidth($video['description'], 0, 180, '…')) ?></p>
                            <?php endif; ?>
                            <div class="card-actions">
                                <a class="watch-link" href="<?= h($video['watch_url']) ?>" target="_blank" rel="noopener">
                                    Watch on YouTube
                                </a>
                                <?php if (!empty($video['duration_seconds'])): ?>
                                    <span class="meta-chip">
                                        <?= gmdate('H:i:s', (int) $video['duration_seconds']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </main>
        <?php endif; ?>
    </div>
</body>
</html>

