"""
Utility helpers for the YouTube crawler.
"""

from __future__ import annotations

import json
import logging
import random
import re
import time
from typing import Any, Dict, Iterable, Optional

import requests

from crawler import config


logger = logging.getLogger("youtube_crawler")


def make_session() -> requests.Session:
    session = requests.Session()
    session.headers.update(
        {
            "User-Agent": config.DEFAULT_USER_AGENT,
            "Accept-Language": "en-US,en;q=0.9",
            "Accept": "*/*",
        }
    )
    if config.PROXY:
        session.proxies.update({"http": config.PROXY, "https": config.PROXY})
    return session


YTI_DATA_RE = re.compile(r"ytInitialData\s*=\s*(\{.+?\});", re.DOTALL)
YTI_PLAYER_RE = re.compile(r"ytInitialPlayerResponse\s*=\s*(\{.+?\});", re.DOTALL)


def extract_json(pattern: re.Pattern[str], text: str) -> Optional[Dict[str, Any]]:
    match = pattern.search(text)
    if not match:
        return None
    raw = match.group(1)
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        # Attempt to clean trailing JS assignments
        cleaned = raw.strip().rstrip(";")
        try:
            return json.loads(cleaned)
        except json.JSONDecodeError:
            logger.warning("Failed to decode JSON fragment.")
            return None


def crawl_delay(multiplier: float = 1.0) -> None:
    delay = config.REQUEST_DELAY * multiplier
    jitter = random.uniform(0.5, 1.5)
    time.sleep(delay * jitter)


def safe_get(data: Dict[str, Any], keys: Iterable[Any], default: Any = None) -> Any:
    current: Any = data
    for key in keys:
        if isinstance(current, dict):
            current = current.get(key, default)
        elif isinstance(current, list):
            try:
                current = current[key]  # type: ignore
            except (IndexError, TypeError):
                return default
        else:
            return default
        if current is None:
            return default
    return current

