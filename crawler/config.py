"""
Configuration values for the manual YouTube crawler.
"""

from pathlib import Path


DATA_DIR = Path(__file__).resolve().parent.parent / "data"
DATA_DIR.mkdir(parents=True, exist_ok=True)

DATABASE_PATH = DATA_DIR / "videos.db"

# User-Agent to mimic a browser; adjust if YouTube blocks requests.
DEFAULT_USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/119.0.0.0 Safari/537.36"
)

# Default search queries to crawl.
SEARCH_QUERIES = [
    "technology documentaries",
    "nature documentary full",
    "space exploration 4k",
    "indie short film award winning",
]

# Maximum videos to persist for each query run.
MAX_VIDEOS_PER_RUN = 50

# Seconds to wait between HTTP requests to avoid rate limiting.
REQUEST_DELAY = 1.5

# Optional proxy configuration (None to disable)
PROXY = None

