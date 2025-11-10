<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Lib\Synchronizer;
use Lib\VideoRepository;
use DateTimeImmutable;
use function Lib\e;
use function Lib\format_duration;

$repository = new VideoRepository();
$messages = [];
$errors = [];
$syncResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_source') {
        $type = $_POST['type'] ?? 'query';
        $identifier = trim($_POST['identifier'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $aiTopic = trim($_POST['ai_topic'] ?? '');

        if (!$identifier || !$label) {
            $errors[] = 'Identifier and label are required.';
        } else {
            try {
                $repository->createSource($type, $identifier, $label, $aiTopic ?: null);
                $messages[] = 'Source added successfully!';
            } catch (Throwable $exception) {
                $errors[] = 'Failed to add source: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'delete_source') {
        $sourceId = (int) ($_POST['source_id'] ?? 0);
        if ($sourceId > 0) {
            try {
                $repository->deleteSource($sourceId);
                $messages[] = 'Source deleted.';
            } catch (Throwable $exception) {
                $errors[] = 'Failed to delete source: ' . $exception->getMessage();
            }
        }
    } elseif ($action === 'sync_sources') {
        $sourceId = isset($_POST['source_id']) && $_POST['source_id'] !== '' ? (int) $_POST['source_id'] : null;
        $limit = isset($_POST['limit']) ? max(1, (int) $_POST['limit']) : 40;
        $synchronizer = new Synchronizer($repository);

        try {
            $syncResults = $synchronizer->synchronize($sourceId, $limit);
            if ($syncResults) {
                $messages[] = 'Sync completed.';
            } else {
                $messages[] = 'No sources to sync yet.';
            }
        } catch (Throwable $exception) {
            $errors[] = 'Sync failed: ' . $exception->getMessage();
        }
    }
}

$sources = $repository->allSources();
$latestVideos = $repository->latest(24);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Video CMS Admin</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .admin-wrapper {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .admin-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(12px);
        }

        .admin-card h2 {
            margin-top: 0;
            font-size: 1.4rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .form-grid label {
            display: block;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.7);
            margin-bottom: 0.35rem;
        }

        .form-grid input,
        .form-grid select,
        .form-grid textarea {
            width: 100%;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            padding: 0.65rem 0.75rem;
            color: #fff;
            font-size: 0.95rem;
        }

        .form-grid textarea {
            resize: vertical;
            min-height: 85px;
        }

        .btn {
            background: linear-gradient(135deg, #e50914, #f40612);
            border: none;
            border-radius: 999px;
            color: #fff;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 12px 30px rgba(229, 9, 20, 0.35);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 40px rgba(229, 9, 20, 0.45);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.08);
            box-shadow: none;
        }

        .status-list {
            list-style: none;
            margin: 0.5rem 0 0;
            padding: 0;
        }

        .status-list li {
            margin-bottom: 0.4rem;
            color: rgba(255,255,255,0.75);
            font-size: 0.95rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.85rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .table th {
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
            font-size: 0.78rem;
        }

        .table td {
            font-size: 0.93rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            background: rgba(255,255,255,0.12);
            border-radius: 999px;
            padding: 0.2rem 0.8rem;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .messages {
            margin-bottom: 1rem;
        }

        .messages .success,
        .messages .error {
            border-radius: 12px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .messages .success {
            background: rgba(46, 204, 113, 0.12);
            border: 1px solid rgba(46, 204, 113, 0.25);
            color: #5ef88c;
        }

        .messages .error {
            background: rgba(231, 76, 60, 0.12);
            border: 1px solid rgba(231, 76, 60, 0.25);
            color: #ffa39e;
        }

        .sync-summary {
            margin-top: 1rem;
            background: rgba(255,255,255,0.04);
            border-radius: 12px;
            padding: 1rem;
        }
    </style>
</head>
<body class="admin-body">
<div class="admin-wrapper">
    <div class="admin-header">
        <div>
            <h1>AI Video CMS • Admin</h1>
            <p class="subtitle">Curate, sync, and enrich your Netflix-style video library.</p>
        </div>
        <div>
            <a class="btn btn-secondary" href="index.php">← Back to catalog</a>
        </div>
    </div>

    <div class="messages">
        <?php foreach ($messages as $message): ?>
            <div class="success"><?= e($message) ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endforeach; ?>
    </div>

    <?php if ($syncResults): ?>
        <div class="admin-card sync-summary">
            <h2>Sync Summary</h2>
            <ul class="status-list">
                <?php foreach ($syncResults as $result): ?>
                    <?php $sourceLabel = $result['source']['label'] ?? $result['source']['identifier'] ?? 'Unknown'; ?>
                    <li>
                        <strong><?= e($sourceLabel) ?>:</strong>
                        Synced <?= (int) $result['synced'] ?> videos
                        <?php if (!empty($result['errors'])): ?>
                            — <span class="badge"><?= count($result['errors']) ?> warnings</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <h2>Add Content Source</h2>
        <form method="post">
            <input type="hidden" name="action" value="add_source">
            <div class="form-grid">
                <div>
                    <label for="type">Source Type</label>
                    <select id="type" name="type">
                        <option value="channel">YouTube Channel ID</option>
                        <option value="query">YouTube Search Query</option>
                    </select>
                </div>
                <div>
                    <label for="identifier">Channel ID or Query</label>
                    <input id="identifier" name="identifier" placeholder="UC_x5XG1OV2P6uZZ5FSM9Ttw / AI documentary" required>
                </div>
                <div>
                    <label for="label">Display Label</label>
                    <input id="label" name="label" placeholder="Google Developers / AI Documentaries" required>
                </div>
                <div>
                    <label for="ai_topic">AI Category Hint (optional)</label>
                    <input id="ai_topic" name="ai_topic" placeholder="AI Education, Tech Trends, ...">
                </div>
            </div>
            <div style="margin-top: 1.2rem;">
                <button class="btn" type="submit">Add Source</button>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <h2>Sync Sources</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="sync_sources">
            <div>
                <label for="source_id">Source</label>
                <select id="source_id" name="source_id">
                    <option value="">All sources</option>
                    <?php foreach ($sources as $source): ?>
                        <option value="<?= (int) $source['id'] ?>">
                            <?= e($source['label']) ?> (<?= e($source['type']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="limit">Max Videos</label>
                <input id="limit" name="limit" type="number" min="1" max="100" value="40">
            </div>
            <div style="display:flex; align-items:flex-end;">
                <button class="btn" type="submit">Run Sync</button>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <h2>Configured Sources</h2>
        <?php if (!$sources): ?>
            <p>No sources yet. Add a YouTube channel or keyword search to start populating the catalog.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Label</th>
                    <th>Type</th>
                    <th>Identifier</th>
                    <th>AI Topic</th>
                    <th>Last Synced</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sources as $source): ?>
                    <tr>
                        <td><?= e($source['label']) ?></td>
                        <td><span class="badge"><?= e(strtoupper($source['type'])) ?></span></td>
                        <td style="max-width:240px; overflow:hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= e($source['identifier']) ?>
                        </td>
                        <td><?= e($source['ai_topic'] ?? '—') ?></td>
                        <td>
                            <?= $source['last_fetched_at'] ? e((new DateTimeImmutable($source['last_fetched_at']))->format('M d, Y H:i')) : 'Never' ?>
                        </td>
                        <td>
                            <form method="post" onsubmit="return confirm('Remove this source?');">
                                <input type="hidden" name="action" value="delete_source">
                                <input type="hidden" name="source_id" value="<?= (int) $source['id'] ?>">
                                <button class="btn btn-secondary" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <h2>Latest Videos</h2>
        <?php if (!$latestVideos): ?>
            <p>No videos fetched yet. Run a sync to populate your catalog.</p>
        <?php else: ?>
            <div class="collection-row">
                <?php foreach ($latestVideos as $video): ?>
                    <div class="video-card admin-card-compact">
                        <div class="thumbnail-wrapper">
                            <?php if (!empty($video['thumbnail_url'])): ?>
                                <img src="<?= e($video['thumbnail_url']) ?>" alt="<?= e($video['title']) ?>">
                            <?php else: ?>
                                <div class="placeholder-thumb">No Image</div>
                            <?php endif; ?>
                            <div class="card-overlay">
                                <span class="chip duration">
                                    <?= e(format_duration($video['duration']) ?? '') ?>
                                </span>
                            </div>
                        </div>
                        <div class="video-info">
                            <h3><?= e($video['title']) ?></h3>
                            <p><?= e($video['channel_title'] ?? '') ?></p>
                            <?php if (!empty($video['ai_category'])): ?>
                                <span class="chip"><?= e($video['ai_category']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
