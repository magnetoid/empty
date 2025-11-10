<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Lib\VideoRepository;
use DateTimeImmutable;
use function Lib\e;
use function Lib\format_duration;

$repository = new VideoRepository();

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$collection = isset($_GET['collection']) ? trim((string) $_GET['collection']) : '';

$videos = $repository->fetchVideos($search ?: null, $collection ?: null);
$collections = $repository->availableCollections();
$heroVideo = $videos[0] ?? null;

$grouped = [];
foreach ($videos as $video) {
    $key = $video['ai_category'] ?: ($video['source_label'] ?? 'Featured');
    $grouped[$key][] = $video;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovaFlix AI — Intelligent Video CMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="scene">
    <header class="top-nav">
        <div class="brand">
            <span class="brand-mark">Nova</span><span class="brand-mark accent">Flix</span>
            <span class="brand-sub">AI CMS</span>
        </div>
        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="#collections">Collections</a>
            <a href="#ai">AI Insights</a>
        </nav>
        <div class="nav-actions">
            <form class="search-form" method="get">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search videos, channels, tags…">
                <?php if ($collection): ?>
                    <input type="hidden" name="collection" value="<?= e($collection) ?>">
                <?php endif; ?>
                <button type="submit" aria-label="Search">
                    🔍
                </button>
            </form>
            <a class="btn-admin" href="admin.php">Admin</a>
        </div>
    </header>

    <main>
        <?php if ($heroVideo): ?>
            <section class="hero" style="--hero-image: url('<?= e($heroVideo['thumbnail_url'] ?? '') ?>')">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <span class="tagline"><?= e($heroVideo['ai_category'] ?? $heroVideo['source_label'] ?? 'Featured') ?></span>
                    <h1><?= e($heroVideo['title']) ?></h1>
                    <?php if (!empty($heroVideo['ai_summary'])): ?>
                        <p class="hero-summary"><?= e($heroVideo['ai_summary']) ?></p>
                    <?php endif; ?>
                    <div class="hero-actions">
                        <button class="btn-primary play-trigger" data-video-id="<?= e($heroVideo['video_id']) ?>">
                            ▶ Play Now
                        </button>
                        <button class="btn-secondary" data-video-id="<?= e($heroVideo['video_id']) ?>">
                            ℹ Details
                        </button>
                    </div>
                    <div class="hero-meta">
                        <span><?= e($heroVideo['channel_title'] ?? '') ?></span>
                        <?php if ($heroVideo['published_at']): ?>
                            <span>• <?= e((new DateTimeImmutable($heroVideo['published_at']))->format('M d, Y')) ?></span>
                        <?php endif; ?>
                        <?php if ($heroVideo['duration']): ?>
                            <span>• <?= e(format_duration($heroVideo['duration']) ?? '') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="empty-state">
                <h1>Build your AI video universe</h1>
                <p>Add YouTube sources from the admin console to see your Netflix-style catalog bloom with intelligent collections.</p>
                <a class="btn-primary" href="admin.php">Go to Admin</a>
            </section>
        <?php endif; ?>

        <section id="collections" class="collections">
            <header class="section-header">
                <h2>Curated Collections</h2>
                <div class="filters">
                    <form method="get" class="filter-form">
                        <select name="collection" onchange="this.form.submit()">
                            <option value="">All collections</option>
                            <?php foreach ($collections as $item): ?>
                                <option value="<?= e($item['name']) ?>" <?= $collection === $item['name'] ? 'selected' : '' ?>>
                                    <?= e($item['name']) ?>
                                    (<?= e($item['type'] === 'ai_category' ? 'AI' : 'Source') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($search): ?>
                            <input type="hidden" name="q" value="<?= e($search) ?>">
                        <?php endif; ?>
                    </form>
                </div>
            </header>

            <?php if ($grouped): ?>
                <?php foreach ($grouped as $groupTitle => $items): ?>
                    <div class="collection-block">
                        <div class="collection-header">
                            <h3><?= e($groupTitle) ?></h3>
                            <span class="count"><?= count($items) ?> videos</span>
                        </div>
                        <div class="collection-row">
                            <?php foreach ($items as $video): ?>
                                <article class="video-card" data-video-id="<?= e($video['video_id']) ?>">
                                    <div class="thumbnail-wrapper">
                                        <?php if (!empty($video['thumbnail_url'])): ?>
                                            <img src="<?= e($video['thumbnail_url']) ?>" alt="<?= e($video['title']) ?>">
                                        <?php else: ?>
                                            <div class="placeholder-thumb">No Image</div>
                                        <?php endif; ?>
                                        <div class="card-overlay">
                                            <?php if ($video['duration']): ?>
                                                <span class="chip duration"><?= e(format_duration($video['duration']) ?? '') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="video-info">
                                        <h4><?= e($video['title']) ?></h4>
                                        <p class="meta"><?= e($video['channel_title'] ?? '') ?></p>
                                        <?php if (!empty($video['ai_tags'])): ?>
                                            <div class="tag-group">
                                                <?php foreach (array_slice($video['ai_tags'], 0, 3) as $tag): ?>
                                                    <span class="chip"><?= e($tag) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty-collection">No videos yet. Add sources and run a sync to populate this shelf.</p>
            <?php endif; ?>
        </section>

        <section id="ai" class="ai-intelligence">
            <div class="ai-card">
                <h2>AI Content Brain</h2>
                <p>Every video is enriched using AI-powered summaries, semantic tags, and dynamic categories. Provide an <code>OPENAI_API_KEY</code> to unlock rich editorial metadata, or rely on the built-in heuristic engine.</p>
                <div class="ai-grid">
                    <div>
                        <h4>Smart Summaries</h4>
                        <p>Distill long descriptions into binge-worthy synopsis ready for your audience.</p>
                    </div>
                    <div>
                        <h4>Semantic Tags</h4>
                        <p>Auto-classified keywords make it easy to discover related stories and themes.</p>
                    </div>
                    <div>
                        <h4>Adaptive Shelves</h4>
                        <p>Collections adapt in real-time based on AI categories and your curated sources.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<div id="playerModal" class="modal">
    <div class="modal-dialog">
        <button class="modal-close" type="button">×</button>
        <div class="modal-body">
            <iframe id="playerFrame" src="" loading="lazy" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture;"></iframe>
        </div>
    </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>