# AtlasStream — AI-Powered Netflix-Style Video CMS

AtlasStream is a compact content management platform that curates YouTube videos into a Netflix-inspired streaming UI. It combines automated crawling, AI-assisted metadata enrichment, and a polished browsing experience to help you stand up a smart video catalog in minutes.

## Highlights

- 🎬 **Netflix-style frontdoor** — responsive hero banner, horizontal rails, masonry grids, and cinematic modals.
- 🤖 **AI enrichment pipeline** — optional OpenAI integration (or local heuristics fallback) to auto-tag, summarize, and categorize incoming videos.
- 🔎 **YouTube crawler** — ingest by keyword or channel, bundle results into collections, and embed via iframes.
- 🗂️ **SQLite-backed CMS** — zero external dependencies; everything runs from plain PHP + SQLite.
- 🔐 **Admin access control** — optional password gate plus secure storage of API keys inside the database.

## Getting Started

1. **Clone & install dependencies**
   ```bash
   git clone <repo-url>
   cd workspace
   cp .env.example .env
   ```

2. **Configure environment**
   - `YOUTUBE_API_KEY` — create a key in the [Google Cloud Console](https://console.cloud.google.com/apis/api/youtube.googleapis.com/overview) with the YouTube Data API v3 enabled.
   - `OPENAI_API_KEY` *(optional)* — required for GPT-powered enrichment (fallback heuristics run without it).
   - `ADMIN_PASSWORD` — set to gate the admin dashboard. Leave blank for no password.

3. **Run the server**
   ```bash
   php -S 0.0.0.0:8000
   ```
   The frontdoor is at `http://localhost:8000/index.php`. The admin console lives at `http://localhost:8000/admin.php`.

4. **Ingest content**
   - Visit the admin console.
   - Paste your API keys (leave blank to keep existing).
   - Launch crawlers by keyword or channel ID.
   - Videos are stored in `storage/database.sqlite` and appear instantly on the home page.

## Project Structure

```
.
├── index.php                # Public Netflix-style experience
├── admin.php                # Admin UI with crawler controls
├── bootstrap.php            # Environment bootstrapping & database migrations
├── services/
│   └── IngestionService.php # Orchestrates YouTube crawling + AI enrichment
├── lib/
│   ├── AiService.php        # Optional OpenAI integration & heuristics
│   ├── VideoRepository.php  # All persistence helpers
│   └── YouTubeClient.php    # Lightweight YouTube Data API client
├── assets/
│   ├── css/
│   │   ├── front.css        # Frontdoor styling
│   │   └── admin.css        # Admin dashboard styling
│   └── js/
│       └── app.js           # Frontdoor interactivity (modal, search, rails)
├── storage/
│   └── database.sqlite      # Generated automatically on first run
└── .env.example
```

## AI Enrichment

- **With OpenAI**: set `OPENAI_API_KEY` to leverage GPT-4o-mini for human-quality summaries, categories, and topical tags.
- **Without OpenAI**: deterministic heuristics analyse titles and descriptions to produce reasonable defaults.

You can extend `AiService` with any provider that accepts text prompts — just replace the HTTP call.

## Respecting YouTube’s Policies

AtlasStream uses the official YouTube Data API. Scraping HTML pages is against YouTube’s Terms of Service and is not recommended. Always ensure your usage complies with applicable policies and only embed videos you have the rights to display.

## Development Notes

- Requires PHP 8.1+ with `curl` and `pdo_sqlite` extensions enabled.
- All storage is local; back up `storage/database.sqlite` if you care about your catalog.
- The project is framework-free for portability, but you can easily wrap it in Laravel/Symfony if you prefer a larger stack.

## Roadmap Ideas

- Scheduled ingestion jobs (cron or queue worker)
- Multi-tenant collections with editorial controls
- User accounts & personalised watchlists
- Analytics dashboards for engagement insights

---

Enjoy the cinematic CMS! Contributions and ideas are welcome.  
Use responsibly and comply with YouTube’s terms when ingesting third-party content.
