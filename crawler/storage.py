"""
Storage layer for the video CMS using SQLite.
"""

from __future__ import annotations

import contextlib
import sqlite3
from pathlib import Path
from typing import Iterable, List, Optional

from crawler import config


SCHEMA = """
CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    video_id TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    channel TEXT,
    description TEXT,
    published_at TEXT,
    duration_seconds INTEGER,
    thumbnail_url TEXT,
    watch_url TEXT NOT NULL,
    search_query TEXT,
    crawled_at TEXT NOT NULL,
    ai_summary TEXT,
    ai_tags TEXT
);

CREATE INDEX IF NOT EXISTS idx_videos_channel ON videos(channel);
CREATE INDEX IF NOT EXISTS idx_videos_search_query ON videos(search_query);
"""


def get_connection(db_path: Optional[Path] = None) -> sqlite3.Connection:
    """
    Create a SQLite connection with sensible defaults.
    """
    path = db_path or config.DATABASE_PATH
    conn = sqlite3.connect(path)
    conn.row_factory = sqlite3.Row
    return conn


def initialize(db_path: Optional[Path] = None) -> None:
    """
    Ensure database schema exists.
    """
    with contextlib.closing(get_connection(db_path)) as conn:
        conn.executescript(SCHEMA)
        conn.commit()


def upsert_videos(
    videos: Iterable[dict],
    db_path: Optional[Path] = None,
) -> int:
    """
    Insert or update videos based on the unique video_id.
    Returns number of rows affected.
    """
    rows = list(videos)
    if not rows:
        return 0

    initialize(db_path)

    columns = [
        "video_id",
        "title",
        "channel",
        "description",
        "published_at",
        "duration_seconds",
        "thumbnail_url",
        "watch_url",
        "search_query",
        "crawled_at",
        "ai_summary",
        "ai_tags",
    ]

    placeholders = ", ".join(["?"] * len(columns))
    update_clause = ", ".join(f"{col}=excluded.{col}" for col in columns[1:])

    sql = f"""
        INSERT INTO videos ({", ".join(columns)})
        VALUES ({placeholders})
        ON CONFLICT(video_id) DO UPDATE SET
        {update_clause}
    """

    with contextlib.closing(get_connection(db_path)) as conn:
        result = conn.executemany(
            sql,
            [
                tuple(row.get(col) for col in columns)
                for row in rows
            ],
        )
        conn.commit()
        return result.rowcount


def query_videos(
    *,
    limit: int = 100,
    offset: int = 0,
    search: Optional[str] = None,
    channel: Optional[str] = None,
    tags: Optional[List[str]] = None,
    sort: str = "published_at DESC",
    db_path: Optional[Path] = None,
) -> List[sqlite3.Row]:
    """
    Fetch videos with filtering options.
    """
    initialize(db_path)
    clauses = []
    params: List[str] = []

    if search:
        clauses.append("(title LIKE ? OR description LIKE ?)")
        like_term = f"%{search}%"
        params.extend([like_term, like_term])

    if channel:
        clauses.append("channel = ?")
        params.append(channel)

    if tags:
        for tag in tags:
            clauses.append("ai_tags LIKE ?")
            params.append(f"%{tag}%")

    where = f"WHERE {' AND '.join(clauses)}" if clauses else ""

    sql = f"""
        SELECT * FROM videos
        {where}
        ORDER BY {sort}
        LIMIT ? OFFSET ?
    """
    params.extend([limit, offset])

    with contextlib.closing(get_connection(db_path)) as conn:
        cursor = conn.execute(sql, params)
        return cursor.fetchall()


def distinct_channels(db_path: Optional[Path] = None) -> List[str]:
    initialize(db_path)
    with contextlib.closing(get_connection(db_path)) as conn:
        cursor = conn.execute(
            "SELECT DISTINCT channel FROM videos WHERE channel IS NOT NULL ORDER BY channel"
        )
        return [row["channel"] for row in cursor.fetchall()]


def latest_crawl_timestamp(db_path: Optional[Path] = None) -> Optional[str]:
    initialize(db_path)
    with contextlib.closing(get_connection(db_path)) as conn:
        cursor = conn.execute(
            "SELECT MAX(crawled_at) as latest FROM videos"
        )
        row = cursor.fetchone()
        return row["latest"] if row and row["latest"] else None


def save_ai_enrichment(
    video_id: str, summary: Optional[str], tags: Optional[List[str]], db_path: Optional[Path] = None
) -> None:
    initialize(db_path)
    tags_str = ", ".join(tags) if tags else None
    with contextlib.closing(get_connection(db_path)) as conn:
        conn.execute(
            """
            UPDATE videos
            SET ai_summary = ?, ai_tags = ?
            WHERE video_id = ?
            """,
            (summary, tags_str, video_id),
        )
        conn.commit()


def serialize_row(row: sqlite3.Row) -> dict:
    """
    Convert a sqlite3.Row to a plain dict.
    """
    return {key: row[key] for key in row.keys()}

