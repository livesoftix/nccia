"""Runtime configuration, read from environment variables only."""

from __future__ import annotations

import os
import shutil
from dataclasses import dataclass, field


def _int(name: str, default: int) -> int:
    try:
        return int(os.environ.get(name, default))
    except (TypeError, ValueError):
        return default


def _float(name: str, default: float) -> float:
    try:
        return float(os.environ.get(name, default))
    except (TypeError, ValueError):
        return default


def find_tesseract() -> str | None:
    candidates = [
        os.environ.get("OCR_TESSERACT_CMD"),
        shutil.which("tesseract"),
        r"C:\Program Files\Tesseract-OCR\tesseract.exe",
        r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
        "/usr/bin/tesseract",
        "/usr/local/bin/tesseract",
    ]
    for cmd in candidates:
        if cmd and os.path.isfile(cmd):
            return cmd
    return None


@dataclass(frozen=True)
class Settings:
    tesseract_cmd: str | None = field(default_factory=find_tesseract)
    # "+"-separated Tesseract language codes, e.g. "eng" or "eng+urd".
    languages: str = field(default_factory=lambda: os.environ.get("OCR_LANGUAGES", "eng"))
    engine: str = field(default_factory=lambda: os.environ.get("OCR_ENGINE", "tesseract"))
    dpi: int = field(default_factory=lambda: _int("OCR_DPI", 300))
    # A page with at least this many native-text characters skips OCR.
    min_text_chars: int = field(default_factory=lambda: _int("OCR_MIN_TEXT_CHARS", 40))
    # Resource limits: reject anything larger instead of exhausting memory.
    max_pages: int = field(default_factory=lambda: _int("OCR_MAX_PAGES", 1000))
    max_file_mb: int = field(default_factory=lambda: _int("OCR_MAX_FILE_MB", 512))
    max_page_pixels: int = field(default_factory=lambda: _int("OCR_MAX_PAGE_PIXELS", 40_000_000))
    page_timeout: int = field(default_factory=lambda: _int("OCR_PAGE_TIMEOUT", 120))
    # Scanned pages beyond this are not OCR'd (fields live in the leading report pages;
    # annexes stay viewable in the original PDF). 0 = OCR every page.
    max_ocr_pages: int = field(default_factory=lambda: _int("OCR_MAX_OCR_PAGES", 5))
    native_text_confidence: float = field(default_factory=lambda: _float("OCR_NATIVE_TEXT_CONFIDENCE", 0.99))


def settings() -> Settings:
    return Settings()
