# NisamVideo – AI-Powered YouTube Video CMS

NovaStream is a Netflix-inspired content management system that automatically discovers, enriches, and showcases YouTube videos without relying on the official YouTube Data API. A lightweight crawler scrapes the public website, persists metadata in SQLite, and an AI enrichment layer generates summaries and smart tags before presenting the catalogue in a cinematic UI.

## Feature Highlights

- Manual HTML crawlers for YouTube search and watch pages (no API keys required)
- SQLite-backed catalogue with automatic schema provisioning
- AI-style enrichment that summarises descriptions and extracts topical tags
- Netflix-like browsing experience with search, channel, and tag filters
- Modular Python tooling for crawling and enrichment, PHP frontend for display

## Architecture Overview

| Layer | Technologies | Responsibilities |
| --- | --- | --- |
| Crawling | Python, `requests` | Scrape search results & watch pages, normalise metadata |
| Storage | SQLite | Persist videos, timestamps, AI fields |
| AI Enrichment | Python | Generate extractive summaries & tags from video descriptions |
| CMS UI | PHP, HTML/CSS | Serve catalogue, provide filters & Netflix-style grid |

The crawler populates `data/videos.db` with a `videos` table. Each row contains raw metadata (title, channel, description, etc.) plus enrichment fields (`ai_summary`, `ai_tags`). The PHP frontend queries this database directly to render the catalogue.

## Getting Started

### Prerequisites

- Python 3.9+
- PHP 8.1+
- `pip` to install Python dependencies

### Install Python Dependencies

```bash
pip install -r requirements.txt
```

### Run the Manual YouTube Crawler

```bash
python -m crawler.run_crawler \
  --query "technology documentaries" \
  --query "space exploration 4k" \
  --limit 25
```

This command scrapes up to 25 videos per query and upserts them into `data/videos.db`. Default queries and throttling behaviour are defined in `crawler/config.py`.

### Enrich Videos with AI Metadata

```bash
python -m ai.run_enrichment --limit 200
```

The enrichment step generates short summaries and keyword tags for recent videos when the description is available.

### Launch the CMS Frontend

Use PHP’s built-in web server from the repository root:

```bash
php -S 0.0.0.0:8080
```

Visit `http://localhost:8080` to browse the catalogue. Use the search bar, channel dropdown, or AI tag filter to drill into the collection.

## Project Structure

```
.
├── ai/
│   ├── enrichment.py        # Text summarisation & tag extraction helpers
│   └── run_enrichment.py    # CLI entry point for enrichment
├── assets/
│   └── style.css            # Netflix-inspired styling
├── crawler/
│   ├── config.py            # Crawler settings (queries, delay, user-agent)
│   ├── run_crawler.py       # CLI wrapper for scheduled/manual runs
│   ├── storage.py           # SQLite schema + persistence utilities
│   ├── utils.py             # HTTP/session helpers & HTML parsers
│   └── youtube_crawler.py   # Core scraping logic
├── data/                    # Created on first run; holds SQLite database
├── index.php                # CMS frontend
├── requirements.txt         # Python dependencies
└── README.md
```

## Operational Notes

- **Respect YouTube’s Terms of Service.** This crawler mimics a browser via user-agent headers and polite delays (`REQUEST_DELAY`). Tune queries and throttle settings to avoid rate limiting.
- **Scheduling:** Use cron or any job scheduler to run the crawler and enricher periodically (e.g., daily crawl + enrichment).
- **Extensibility:** The storage layer exposes helpers to build APIs or additional CMS views. Consider adding thumbnail caching, pagination, or authentication as next steps.
- **Backups:** SQLite is file-based. Copy `data/videos.db` regularly if you deploy NovaStream in production.

## Roadmap Ideas

- Replace heuristic AI enrichment with a lightweight local language model
- Add playlist/genre curation dashboards and editorial workflows
- Serve a JSON API for headless integrations
- Integrate background job processing for large crawling schedules

Enjoy building cinematic experiences on top of open web data!

