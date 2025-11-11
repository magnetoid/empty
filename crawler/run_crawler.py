"""
Command-line interface for the manual YouTube crawler.
"""

from __future__ import annotations

import argparse
import logging

from crawler import config, youtube_crawler


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run the manual YouTube video crawler.")
    parser.add_argument(
        "-q",
        "--query",
        action="append",
        dest="queries",
        help="Search query to crawl. Can be passed multiple times.",
    )
    parser.add_argument(
        "-l",
        "--limit",
        type=int,
        default=config.MAX_VIDEOS_PER_RUN,
        help="Number of videos per query to fetch.",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    logging.basicConfig(level=logging.INFO)
    affected = youtube_crawler.run(queries=args.queries, limit=args.limit)
    logging.info("Crawl complete. %s videos upserted.", affected)


if __name__ == "__main__":
    main()

