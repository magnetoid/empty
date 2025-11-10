# NovaFlix AI — Netflix-Style Video CMS

NovaFlix AI is an independent, self-hosted video CMS inspired by Netflix’s cinematic design. It crawls YouTube channels or keyword searches, stores the results locally, and enriches every title with AI-generated summaries, semantic tags, and adaptive categories. The catalog is rendered in a modern streaming interface, complete with modal playback via embedded iframes.

## Highlights

- **AI-Powered Enrichment** — Plug in an `OPENAI_API_KEY` to generate editorial copy, tags, and collections; otherwise fall back to a heuristic engine.
- **YouTube Synchronization** — Fetch videos from specific channels or keyword searches using the YouTube Data API (v3).
- **Netflix-Style UX** — Dark, cinematic front-end with heroes, horizontal rails, and modals for embedded playback.
- **Admin Dashboard** — Manage sources, trigger syncs, and review the latest content inside a glassmorphism-inspired control room.
- **SQLite Storage** — All metadata, AI output, and source configuration lives in a single local database for easy portability.

## Requirements

- PHP 8.1+ with SQLite and cURL extensions enabled.
- Composer is **not** required; everything runs on the core runtime.
- A Google Cloud project with a YouTube Data API key (`YOUTUBE_API_KEY`).
- (Optional) An OpenAI API key (`OPENAI_API_KEY`) for rich metadata generation.

## Getting Started

1. **Clone / copy** the project into your web root.

2. **Create a `.env` file** (optional but recommended) from the template:
   ```bash
   cp .env.example .env
   ```
   Fill in your credentials:
   ```ini
   YOUTUBE_API_KEY=your_youtube_data_api_key
   OPENAI_API_KEY=optional_openai_api_key
   OPENAI_MODEL=gpt-4o-mini
   ```

3. **Serve the application** (development):
   ```bash
   php -S localhost:8000 -t /workspace
   ```
   Then visit [http://localhost:8000](http://localhost:8000).

## Usage Workflow

1. **Open the Admin dashboard** at `/admin.php`.
2. **Add sources** by channel ID or keyword search. Optionally provide an AI category hint to steer enrichment.
3. **Trigger a sync** from the dashboard or via CLI:
   ```bash
   php scripts/sync.php            # sync all sources (default limit 40)
   php scripts/sync.php --source=1 # sync a specific source
   php scripts/sync.php --limit=75 # override video fetch limit
   ```
4. **Browse the catalog** at `/index.php`. Click any title to watch inside an embedded modal player.

## Automating Updates

Schedule a cron job to keep the catalog fresh:
```cron
0 * * * * php /path/to/project/scripts/sync.php >> /var/log/novaflix-sync.log 2>&1
```

## Project Structure

- `index.php` — Netflix-style front-end experience.
- `admin.php` — Control center for sources and sync jobs.
- `scripts/sync.php` — CLI utility for scheduled crawls.
- `lib/` — Core PHP services (database, YouTube client, AI enrichment, synchronizer).
- `assets/` — Shared CSS and JavaScript.
- `storage/database.sqlite` — Auto-generated SQLite datastore.

## Notes & Customization

- Without an OpenAI key, the system falls back to keyword-based summaries and tags.
- The YouTube client respects basic quota limits; adjust `--limit` or add pagination logic for larger catalogs.
- Tailor the UI by editing `assets/style.css` or extend the layout with additional templates.

Enjoy building your AI-powered streaming universe with NovaFlix AI! 🎬🤖
