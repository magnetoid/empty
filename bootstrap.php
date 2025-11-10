<?php
declare(strict_types=1);

/**
 * Global bootstrap for the AI-powered video CMS.
 *
 * Handles base path definition, helper loading, and database initialization.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

require_once BASE_PATH . '/lib/helpers.php';
require_once BASE_PATH . '/lib/Database.php';

\Lib\load_env(BASE_PATH . '/.env');

\Lib\Database::initialize();
