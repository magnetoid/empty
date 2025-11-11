"""
Manual YouTube crawler that scrapes video metadata without using the official API.
"""

from __future__ import annotations

import logging
from datetime import datetime
from typing import Dict, List, Optional

from crawler import config, storage
from crawler.utils import (
    YTI_DATA_RE,
    YTI_PLAYER_RE,
    crawl_delay,
    extract_json,
    make_session,
    safe_get,
)


logger = logging.getLogger("youtube_crawler")


def parse_video_renderer(renderer: Dict) -> Optional[Dict]:
    video_id = renderer.get("videoId")
    if not video_id:
        return None

    title_runs = safe_get(renderer, ["title", "runs"], [])
    title = title_runs[0]["text"] if title_runs else renderer.get("title", {}).get("simpleText")
    owner_text = safe_get(renderer, ["ownerText", "runs", 0, "text"])
    published_time = safe_get(renderer, ["publishedTimeText", "simpleText"])
    length_text = safe_get(renderer, ["lengthText", "simpleText"])

    thumbnails = safe_get(renderer, ["thumbnail", "thumbnails"], [])
    thumbnail_url = thumbnails[-1]["url"] if thumbnails else None

    return {
        "video_id": video_id,
        "title": title,
        "channel": owner_text,
        "published_time_text": published_time,
        "duration_text": length_text,
        "thumbnail_url": thumbnail_url,
    }


def parse_search_response(html: str) -> List[Dict]:
    initial_data = extract_json(YTI_DATA_RE, html)
    if not initial_data:
        logger.warning("Could not find ytInitialData in search response.")
        return []

    contents = safe_get(
        initial_data,
        [
            "contents",
            "twoColumnSearchResultsRenderer",
            "primaryContents",
            "sectionListRenderer",
            "contents",
        ],
        [],
    )

    videos: List[Dict] = []
    for section in contents:
        items = safe_get(section, ["itemSectionRenderer", "contents"], [])
        for item in items:
            renderer = item.get("videoRenderer")
            if renderer:
                parsed = parse_video_renderer(renderer)
                if parsed:
                    videos.append(parsed)
    return videos


def parse_watch_response(html: str) -> Dict:
    player_json = extract_json(YTI_PLAYER_RE, html) or {}
    video_details = player_json.get("videoDetails", {})
    microformat = safe_get(player_json, ["microformat", "playerMicroformatRenderer"], {})

    publish_date = microformat.get("publishDate") or microformat.get("uploadDate")
    length_seconds = int(video_details.get("lengthSeconds", 0)) if video_details.get("lengthSeconds") else None

    return {
        "description": video_details.get("shortDescription"),
        "published_at": publish_date,
        "duration_seconds": length_seconds,
    }


def crawl_query(query: str, limit: int) -> List[Dict]:
    session = make_session()
    params = {"search_query": query, "sp": "EgIQAQ%253D%253D"}  # Filters for videos only
    logger.info("Fetching search results for %s", query)
    response = session.get("https://www.youtube.com/results", params=params, timeout=15)
    response.raise_for_status()
    videos = parse_search_response(response.text)
    crawl_delay()

    collected: List[Dict] = []
    for video in videos[:limit]:
        video_id = video["video_id"]
        watch_url = f"https://www.youtube.com/watch?v={video_id}"
        try:
            logger.info("Fetching watch metadata for %s", video_id)
            watch_resp = session.get(watch_url, timeout=15)
            watch_resp.raise_for_status()
            watch_data = parse_watch_response(watch_resp.text)
            crawl_delay()
        except Exception as exc:  # pylint: disable=broad-exception-caught
            logger.warning("Failed to fetch watch page for %s: %s", video_id, exc)
            watch_data = {}

        record = {
            "video_id": video_id,
            "title": video.get("title"),
            "channel": video.get("channel"),
            "description": watch_data.get("description"),
            "published_at": watch_data.get("published_at") or video.get("published_time_text"),
            "duration_seconds": watch_data.get("duration_seconds"),
            "thumbnail_url": video.get("thumbnail_url"),
            "watch_url": watch_url,
            "search_query": query,
            "crawled_at": datetime.utcnow().isoformat(),
            "ai_summary": None,
            "ai_tags": None,
        }
        collected.append(record)

    return collected


def run(
    queries: Optional[List[str]] = None,
    limit: Optional[int] = None,
) -> int:
    """
    Crawl the provided queries and persist video metadata.
    Returns the number of videos upserted.
    """
    queries = queries or config.SEARCH_QUERIES
    limit = limit or config.MAX_VIDEOS_PER_RUN

    all_videos: List[Dict] = []
    for query in queries:
        try:
            all_videos.extend(crawl_query(query, limit))
        except Exception as exc:  # pylint: disable=broad-exception-caught
            logger.error("Failed to crawl query %s: %s", query, exc)

    if not all_videos:
        logger.warning("No videos collected.")
        return 0

    affected = storage.upsert_videos(all_videos)
    logger.info("Persisted %s video records", affected)
    return affected


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    run()

