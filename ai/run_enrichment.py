"""
Entry point for enriching scraped videos with AI metadata.
"""

from __future__ import annotations

import argparse
import logging

from ai import enrichment


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Enrich video metadata with AI-generated insights.")
    parser.add_argument("--limit", type=int, default=200, help="Number of recent videos to consider.")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    logging.basicConfig(level=logging.INFO)
    updated = enrichment.enrich_all(limit=args.limit)
    logging.info("Updated %s videos with AI metadata.", updated)


if __name__ == "__main__":
    main()

