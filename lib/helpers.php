<?php
declare(strict_types=1);

namespace Lib;

/**
 * Load environment variables from a dotenv-style file.
 */
function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);
        $value = trim($value);

        // Strip matching quotes if present.
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

/**
 * Retrieve an environment variable with an optional default.
 */
function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

/**
 * Resolve a path inside the storage directory.
 */
function storage_path(string $path = ''): string
{
    $base = BASE_PATH . '/storage';

    return rtrim($base . '/' . ltrim($path, '/'), '/');
}

/**
 * Ensure that a directory exists.
 */
function ensure_directory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

/**
 * Determine whether the current execution context is CLI.
 */
function running_in_console(): bool
{
    return PHP_SAPI === 'cli';
}

/**
 * Basic HTML escape helper.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Human-readable formatting for ISO 8601 durations (e.g., PT1H2M10S → 1:02:10).
 */
function format_duration(?string $isoDuration): ?string
{
    if (!$isoDuration) {
        return null;
    }

    try {
        $interval = new \DateInterval($isoDuration);
    } catch (\Exception) {
        return null;
    }

    $totalSeconds = ($interval->h * 3600)
        + ($interval->i * 60)
        + $interval->s
        + ($interval->d * 86400)
        + ($interval->m * 2629746) // approximate months
        + ($interval->y * 31556952); // approximate years

    if ($totalSeconds <= 0) {
        return null;
    }

    $hours = (int) floor($totalSeconds / 3600);
    $minutes = (int) floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
    }

    return sprintf('%d:%02d', $minutes, $seconds);
}

/**
 * Simple slug helper.
 */
function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}
