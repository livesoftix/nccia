"""PDF inspection and page iteration with PyMuPDF.

Pages are opened one range at a time so an 800-page PDF is never held in
memory as a whole; each page's pixmap is released before the next is rendered.
"""

from __future__ import annotations

import hashlib
import os
from contextlib import contextmanager
from typing import Iterator

import pymupdf as fitz

from .config import Settings


class PdfRejected(Exception):
    """The file is not a PDF we are willing to process (malformed, encrypted, too large)."""


def sha256_file(path: str, chunk: int = 1024 * 1024) -> str:
    digest = hashlib.sha256()
    with open(path, "rb") as fh:
        for block in iter(lambda: fh.read(chunk), b""):
            digest.update(block)
    return digest.hexdigest()


@contextmanager
def open_pdf(path: str, cfg: Settings) -> Iterator[fitz.Document]:
    size = os.path.getsize(path)
    if size > cfg.max_file_mb * 1024 * 1024:
        raise PdfRejected(f"file exceeds {cfg.max_file_mb} MB limit")
    with open(path, "rb") as fh:
        if fh.read(5) != b"%PDF-":
            raise PdfRejected("not a PDF (missing %PDF- header)")
    try:
        doc = fitz.open(path, filetype="pdf")
    except Exception as exc:  # noqa: BLE001 - PyMuPDF raises several types
        raise PdfRejected(f"cannot open PDF: {exc}") from exc
    try:
        if doc.needs_pass:
            raise PdfRejected("PDF is password protected")
        if doc.page_count < 1:
            raise PdfRejected("PDF has no pages")
        if doc.page_count > cfg.max_pages:
            raise PdfRejected(f"PDF has {doc.page_count} pages; limit is {cfg.max_pages}")
        yield doc
    finally:
        doc.close()


def inspect(path: str, cfg: Settings) -> dict:
    with open_pdf(path, cfg) as doc:
        page_count = doc.page_count
        sample = min(page_count, 5)
        text_pages = sum(1 for i in range(sample) if len((doc[i].get_text("text") or "").strip()) >= cfg.min_text_chars)
    return {
        "page_count": page_count,
        "file_size": os.path.getsize(path),
        "sha256": sha256_file(path),
        "sampled_pages": sample,
        "sampled_text_pages": text_pages,
    }
