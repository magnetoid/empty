<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once BASE_PATH . '/lib/VideoRepository.php';

$repository = new VideoRepository();
$groups = $repository->getHomepageData();
$recentVideos = $repository->getRecentVideos(18);

$heroVideo = null;
foreach ($groups as $group) {
    if (!empty($group['videos'])) {
        $heroVideo = $group['videos'][0];
        break;
    }
}

if (!$heroVideo && !empty($recentVideos)) {
    $heroVideo = $recentVideos[0];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AtlasStream — AI-powered Video CMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/front.css">
</head>
<body>
    <header class="nav">
        <div class="nav__left">
            <div class="logo">Atlas<span>Stream</span></div>
            <nav>
                <a href="#trending">Trending</a>
                <a href="#fresh">Fresh drops</a>
                <a href="#collections">Collections</a>
            </nav>
        </div>
        <div class="nav__right">
            <div class="search">
                <input type="search" placeholder="Search across the catalog" data-search>
            </div>
            <a class="admin-link" href="admin.php">Admin</a>
        </div>
    </header>

    <main>
        <?php if ($heroVideo): ?>
            <section class="hero" style="background-image: url('<?= htmlspecialchars(heroBackdrop($heroVideo), ENT_QUOTES) ?>');">
                <div class="hero__overlay"></div>
                <div class="hero__content">
                    <span class="badge">Featured</span>
                    <h1><?= htmlspecialchars($heroVideo['title'], ENT_QUOTES) ?></h1>
                    <p><?= htmlspecialchars(heroSummary($heroVideo), ENT_QUOTES) ?></p>
                    <div class="hero__meta">
                        <span><?= htmlspecialchars(formatPublishedAt($heroVideo['published_at']), ENT_QUOTES) ?></span>
                        <?php if (!empty($heroVideo['ai_topics'])): ?>
                            <span><?= htmlspecialchars(implode(' • ', array_slice($heroVideo['ai_topics'], 0, 3)), ENT_QUOTES) ?></span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars(formatDuration($heroVideo['duration']), ENT_QUOTES) ?></span>
                    </div>
                    <div class="hero__actions">
                        <button class="play"
                            data-play="<?= htmlspecialchars($heroVideo['youtube_id'], ENT_QUOTES) ?>"
                            data-title="<?= htmlspecialchars($heroVideo['title'], ENT_QUOTES) ?>"
                            data-summary="<?= htmlspecialchars(heroSummary($heroVideo), ENT_QUOTES) ?>"
                            data-topics="<?= htmlspecialchars(implode(',', $heroVideo['ai_topics'] ?? []), ENT_QUOTES) ?>"
                        >&#9658; Play</button>
                        <button class="info" data-scroll="#collections">Discover more</button>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="empty-state">
                <div class="card">
                    <h1>Build your library</h1>
                    <p>Use the admin console to crawl YouTube content and instantly surface it in this Netflix-inspired experience.</p>
                    <a href="admin.php" class="cta">Go to admin</a>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($groups)): ?>
            <?php foreach ($groups as $index => $group): ?>
                <section class="rail" id="<?= htmlspecialchars($group['slug'], ENT_QUOTES) ?>" data-rail>
                    <div class="rail__header">
                        <h2><?= htmlspecialchars($group['name'], ENT_QUOTES) ?></h2>
                        <div class="rail__meta">
                            <?php if (!empty($group['hero']['ai_topics'])): ?>
                                <?php foreach (array_slice($group['hero']['ai_topics'], 0, 3) as $topic): ?>
                                    <span class="pill"><?= htmlspecialchars($topic, ENT_QUOTES) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="rail__controls">
                            <button class="rail-btn prev" aria-label="Scroll left">&#10094;</button>
                            <button class="rail-btn next" aria-label="Scroll right">&#10095;</button>
                        </div>
                    </div>
                    <div class="rail__scroller" data-carousel>
                        <?php foreach ($group['videos'] as $video): ?>
                            <article class="tile"
                                data-video="<?= htmlspecialchars($video['youtube_id'], ENT_QUOTES) ?>"
                                data-title="<?= htmlspecialchars($video['title'], ENT_QUOTES) ?>"
                                data-summary="<?= htmlspecialchars(tileSummary($video), ENT_QUOTES) ?>"
                                data-topics="<?= htmlspecialchars(implode(',', $video['ai_topics'] ?? []), ENT_QUOTES) ?>">
                                <div class="tile__thumb" style="background-image: url('<?= htmlspecialchars(bestThumbnail($video), ENT_QUOTES) ?>');">
                                    <button class="tile__play" data-play="<?= htmlspecialchars($video['youtube_id'], ENT_QUOTES) ?>">&#9658;</button>
                                </div>
                                <div class="tile__body">
                                    <h3><?= htmlspecialchars($video['title'], ENT_QUOTES) ?></h3>
                                    <p><?= htmlspecialchars(tileSummary($video), ENT_QUOTES) ?></p>
                                    <div class="tile__meta">
                                        <span><?= htmlspecialchars($video['channel_title'] ?? 'Unknown channel', ENT_QUOTES) ?></span>
                                        <span><?= htmlspecialchars(formatPublishedAt($video['published_at']), ENT_QUOTES) ?></span>
                                        <span><?= htmlspecialchars(formatDuration($video['duration']), ENT_QUOTES) ?></span>
                                    </div>
                                    <?php if (!empty($video['ai_topics'])): ?>
                                        <div class="tile__topics">
                                            <?php foreach (array_slice($video['ai_topics'], 0, 3) as $topic): ?>
                                                <span><?= htmlspecialchars($topic, ENT_QUOTES) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($recentVideos)): ?>
            <section class="grid-section" id="fresh">
                <div class="grid-header">
                    <h2>Freshly ingested</h2>
                    <p>Most recent arrivals from your crawlers.</p>
                </div>
                <div class="masonry">
                    <?php foreach ($recentVideos as $video): ?>
                        <article class="masonry__card"
                            data-video="<?= htmlspecialchars($video['youtube_id'], ENT_QUOTES) ?>"
                            data-title="<?= htmlspecialchars($video['title'], ENT_QUOTES) ?>"
                            data-summary="<?= htmlspecialchars(tileSummary($video), ENT_QUOTES) ?>"
                            data-topics="<?= htmlspecialchars(implode(',', $video['ai_topics'] ?? []), ENT_QUOTES) ?>">
                            <div class="masonry__thumb" style="background-image: url('<?= htmlspecialchars(bestThumbnail($video), ENT_QUOTES) ?>');">
                                <span class="duration"><?= htmlspecialchars(formatDuration($video['duration']), ENT_QUOTES) ?></span>
                            </div>
                            <div class="masonry__body">
                                <h3><?= htmlspecialchars($video['title'], ENT_QUOTES) ?></h3>
                                <p><?= htmlspecialchars(tileSummary($video), ENT_QUOTES) ?></p>
                                <div class="chips">
                                    <?php if (!empty($video['ai_topics'])): ?>
                                        <?php foreach (array_slice($video['ai_topics'], 0, 2) as $topic): ?>
                                            <span><?= htmlspecialchars($topic, ENT_QUOTES) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($video['ai_category'] ?? 'Uncategorized', ENT_QUOTES) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <footer class="site-footer" id="collections">
        <div class="footer-grid">
            <div>
                <div class="logo">Atlas<span>Stream</span></div>
                <p>AI-assisted Netflix-style CMS for curated YouTube content.</p>
            </div>
            <div>
                <h4>Workflows</h4>
                <ul>
                    <li>AI-driven tagging</li>
                    <li>Collection curation</li>
                    <li>Rapid ingestion</li>
                </ul>
            </div>
            <div>
                <h4>Admin</h4>
                <ul>
                    <li><a href="admin.php">Crawler dashboard</a></li>
                    <li><a href="README.md">Developer docs</a></li>
                </ul>
            </div>
        </div>
        <p class="fine-print">Built for demonstration purposes. Respect YouTube terms of service when ingesting content.</p>
    </footer>

    <div class="modal" id="playerModal" hidden>
        <div class="modal__backdrop" data-dismiss></div>
        <div class="modal__dialog">
            <button class="modal__close" data-dismiss aria-label="Close">&#10005;</button>
            <div class="modal__player">
                <iframe id="playerFrame" src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
            <div class="modal__details">
                <h3 id="modalTitle"></h3>
                <p id="modalSummary"></p>
                <div class="modal__chips" id="modalTopics"></div>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js" defer></script>
</body>
</html>
<?php

function bestThumbnail(array $video): string
{
    $thumbs = $video['thumbnails'] ?? [];
    $priority = ['maxres', 'standard', 'high', 'medium', 'default'];

    foreach ($priority as $key) {
        if (!empty($thumbs[$key]['url'])) {
            return $thumbs[$key]['url'];
        }
    }

    return 'https://img.youtube.com/vi/' . urlencode($video['youtube_id']) . '/hqdefault.jpg';
}

function heroBackdrop(array $video): string
{
    $thumb = bestThumbnail($video);
    return $thumb;
}

function heroSummary(array $video): string
{
    if (!empty($video['ai_summary'])) {
        return $video['ai_summary'];
    }

    return tileSummary($video);
}

function tileSummary(array $video): string
{
    $text = $video['ai_summary'] ?? $video['description'] ?? '';
    $text = trim($text);

    if ($text === '') {
        return 'No summary available yet.';
    }

    $text = preg_replace('/\s+/', ' ', $text) ?? $text;

    return mb_strlen($text) > 140 ? mb_substr($text, 0, 140) . '…' : $text;
}

function formatDuration(?string $duration): string
{
    if (!$duration) {
        return '—';
    }

    try {
        $interval = new DateInterval($duration);
        $parts = [];
        $hours = $interval->h + ($interval->d * 24);
        if ($hours > 0) {
            $parts[] = sprintf('%dh', $hours);
        }
        if ($interval->i > 0 || $hours > 0) {
            $parts[] = sprintf('%dm', $interval->i);
        }
        if ($interval->s > 0 || empty($parts)) {
            $parts[] = sprintf('%ds', $interval->s);
        }

        return implode(' ', $parts);
    } catch (Exception) {
        return '—';
    }
}

function formatPublishedAt(?string $timestamp): string
{
    if (!$timestamp) {
        return 'Unknown';
    }

    try {
        $dt = new DateTimeImmutable($timestamp);
        return $dt->format('M j, Y');
    } catch (Exception) {
        return $timestamp;
    }
}

