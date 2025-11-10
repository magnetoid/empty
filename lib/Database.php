<?php
declare(strict_types=1);

namespace Lib;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    /**
     * Initialize the connection and ensure schema is available.
     */
    public static function initialize(): void
    {
        self::connection();
    }

    /**
     * Obtain a shared PDO connection instance.
     */
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $databasePath = storage_path('database.sqlite');
        ensure_directory(dirname($databasePath));

        $firstBoot = !file_exists($databasePath);

        try {
            self::$connection = new PDO('sqlite:' . $databasePath);
        } catch (PDOException $exception) {
            throw new \RuntimeException('Unable to open SQLite database: ' . $exception->getMessage(), 0, $exception);
        }

        self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$connection->exec('PRAGMA foreign_keys = ON;');

        if ($firstBoot) {
            self::$connection->exec('PRAGMA journal_mode = wal;');
        }

        self::migrate(self::$connection);

        return self::$connection;
    }

    /**
     * Execute a SQL statement and return the affected row count.
     */
    public static function execute(string $sql, array $parameters = []): int
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->rowCount();
    }

    /**
     * Fetch all rows from a query.
     */
    public static function fetchAll(string $sql, array $parameters = []): array
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * Fetch a single row from a query.
     */
    public static function fetch(string $sql, array $parameters = []): ?array
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($parameters);
        $result = $statement->fetch();

        return $result !== false ? $result : null;
    }

    /**
     * Insert a row and return the last inserted id.
     */
    public static function insert(string $sql, array $parameters = []): int
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) self::connection()->lastInsertId();
    }

    /**
     * Schema definition for the application.
     */
    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS video_sources (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                identifier TEXT NOT NULL,
                label TEXT NOT NULL,
                ai_topic TEXT,
                last_fetched_at TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (type, identifier)
            );
            SQL
        );

        $pdo->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_id INTEGER,
                video_id TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                description TEXT,
                thumbnail_url TEXT,
                channel_title TEXT,
                published_at TEXT,
                duration TEXT,
                ai_summary TEXT,
                ai_tags TEXT,
                ai_category TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (source_id) REFERENCES video_sources(id) ON DELETE SET NULL
            );
            SQL
        );

        $pdo->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                payload TEXT,
                output TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                finished_at TEXT
            );
            SQL
        );
    }
}
