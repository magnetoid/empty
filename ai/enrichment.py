"""
Lightweight AI-inspired enrichment utilities for video metadata.

The module performs extractive summarisation and keyword extraction without external services.
"""

from __future__ import annotations

import math
import re
from collections import Counter
from typing import Iterable, List, Optional, Sequence, Tuple

from crawler import storage


TOKEN_RE = re.compile(r"[A-Za-z0-9']+")
STOP_WORDS = {
    "the",
    "a",
    "an",
    "and",
    "or",
    "in",
    "on",
    "for",
    "of",
    "to",
    "with",
    "from",
    "by",
    "is",
    "are",
    "be",
    "this",
    "that",
    "it",
    "as",
    "at",
    "we",
    "you",
    "your",
    "our",
    "their",
    "they",
    "he",
    "she",
    "his",
    "her",
    "its",
    "was",
    "were",
    "will",
    "about",
    "into",
    "over",
    "under",
    "after",
    "before",
}


def tokenize(text: str) -> List[str]:
    return [token.lower() for token in TOKEN_RE.findall(text)]


def split_sentences(text: str) -> List[str]:
    sentences = re.split(r"(?<=[.!?])\s+", text.strip())
    return [sentence for sentence in sentences if sentence]


def sentence_scores(sentences: Sequence[str]) -> List[Tuple[str, float]]:
    if not sentences:
        return []
    word_freq = Counter(tokenize(" ".join(sentences)))
    scores: List[Tuple[str, float]] = []
    for sentence in sentences:
        tokens = tokenize(sentence)
        if not tokens:
            continue
        score = sum(word_freq[token] for token in tokens if token not in STOP_WORDS)
        scores.append((sentence, score / len(tokens)))
    return scores


def generate_summary(description: Optional[str], max_sentences: int = 2) -> Optional[str]:
    if not description:
        return None

    sentences = split_sentences(description)
    if not sentences:
        return None

    scored = sentence_scores(sentences)
    if not scored:
        return None

    top_sentences = sorted(scored, key=lambda item: item[1], reverse=True)[:max_sentences]
    ordered = sorted(top_sentences, key=lambda item: sentences.index(item[0]))
    return " ".join(sentence for sentence, _ in ordered)


def extract_candidate_phrases(text: str) -> List[str]:
    words = TOKEN_RE.findall(text.lower())
    phrases: List[str] = []
    current: List[str] = []
    for word in words:
        if word in STOP_WORDS:
            if current:
                phrases.append(" ".join(current))
                current = []
        else:
            current.append(word)
    if current:
        phrases.append(" ".join(current))
    return [phrase for phrase in phrases if len(phrase) > 2]


def generate_tags(title: Optional[str], description: Optional[str], max_tags: int = 6) -> List[str]:
    combined = " ".join(filter(None, [title or "", description or ""]))
    if not combined:
        return []

    phrase_counts = Counter(extract_candidate_phrases(combined))
    if not phrase_counts:
        return []

    # Weight phrases by length to encourage multi-word tags.
    scored = [
        (phrase, count * (1 + math.log(len(phrase.split()), 2)))
        for phrase, count in phrase_counts.items()
    ]
    top = sorted(scored, key=lambda item: item[1], reverse=True)[:max_tags]
    return [phrase.title() for phrase, _ in top]


def enrich_all(limit: int = 100) -> int:
    """
    Enrich videos lacking AI metadata.
    Returns the number of videos updated.
    """
    rows = storage.query_videos(limit=limit, sort="crawled_at DESC")
    updated = 0
    for row in rows:
        if row["ai_summary"] and row["ai_tags"]:
            continue
        summary = generate_summary(row["description"])
        tags = generate_tags(row["title"], row["description"])
        if summary or tags:
            storage.save_ai_enrichment(row["video_id"], summary, tags)
            updated += 1
    return updated

