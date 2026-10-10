"""OCR engines and the per-page processing loop."""

from __future__ import annotations

import re
import time
import unicodedata
from typing import Iterator, Protocol

import cv2
import numpy as np

from .config import Settings
from .pdfio import open_pdf
from .preprocess import enhance, render_page


class OcrEngine(Protocol):
    name: str

    def recognize(self, image: np.ndarray) -> tuple[str, float]:
        """Return (text, mean word confidence in 0..1)."""

    def orientation(self, image: np.ndarray) -> int:
        """Return clockwise rotation (0/90/180/270) needed to make text upright."""


class TesseractEngine:
    name = "tesseract"

    def __init__(self, cfg: Settings):
        import pytesseract

        if not cfg.tesseract_cmd:
            raise RuntimeError("Tesseract binary not found; set OCR_TESSERACT_CMD")
        pytesseract.pytesseract.tesseract_cmd = cfg.tesseract_cmd
        self._tess = pytesseract
        self.cfg = cfg
        available = set(pytesseract.get_languages(config=""))
        requested = [lang for lang in cfg.languages.split("+") if lang]
        missing = [lang for lang in requested if lang not in available]
        if missing:
            raise RuntimeError(f"Tesseract language data missing: {', '.join(missing)} (installed: {', '.join(sorted(available))})")
        self.languages = "+".join(requested)

    def recognize(self, image: np.ndarray) -> tuple[str, float]:
        data = self._tess.image_to_data(
            image, lang=self.languages, config="--oem 1 --psm 3", output_type=self._tess.Output.DICT,
            timeout=self.cfg.page_timeout,
        )
        lines: dict[tuple, list[str]] = {}
        confs: list[float] = []
        for i, word in enumerate(data["text"]):
            word = (word or "").strip()
            conf = float(data["conf"][i])
            if not word or conf < 0:
                continue
            key = (data["block_num"][i], data["par_num"][i], data["line_num"][i])
            lines.setdefault(key, []).append(word)
            confs.append(conf)
        text = "\n".join(" ".join(words) for _, words in sorted(lines.items()))
        mean = (sum(confs) / len(confs) / 100.0) if confs else 0.0
        return text, round(mean, 4)

    def orientation(self, image: np.ndarray) -> int:
        try:
            osd = self._tess.image_to_osd(image, config="--psm 0", timeout=30)
        except Exception:  # noqa: BLE001 - OSD fails on near-empty pages
            return 0
        match = re.search(r"Rotate:\s+(\d+)", osd)
        return int(match.group(1)) if match else 0


class PaddleEngine:
    """Optional engine; used only when paddleocr is installed locally (models stay on disk)."""

    name = "paddle"

    def __init__(self, cfg: Settings):
        from paddleocr import PaddleOCR  # type: ignore[import-not-found]

        lang = "ur" if "urd" in cfg.languages else "en"
        self._ocr = PaddleOCR(use_angle_cls=True, lang=lang, show_log=False)

    def recognize(self, image: np.ndarray) -> tuple[str, float]:
        result = self._ocr.ocr(cv2.cvtColor(image, cv2.COLOR_GRAY2BGR), cls=True) or [[]]
        lines, confs = [], []
        for item in result[0] or []:
            text, conf = item[1]
            lines.append(text)
            confs.append(float(conf))
        return "\n".join(lines), round(sum(confs) / len(confs), 4) if confs else 0.0

    def orientation(self, image: np.ndarray) -> int:
        return 0  # handled by use_angle_cls


def make_engine(cfg: Settings) -> OcrEngine:
    if cfg.engine == "paddle":
        return PaddleEngine(cfg)
    return TesseractEngine(cfg)


LOW_CONFIDENCE = 0.5


def normalize_unicode(text: str) -> str:
    """NFKC folds Arabic-script presentation forms (U+FBxx-U+FExx) back to base letters."""
    return unicodedata.normalize("NFKC", text)


def _rotate_upright(image: np.ndarray, correction: int) -> np.ndarray:
    """Rotate counter-clockwise by ``correction`` degrees (0/90/180/270)."""
    codes = {90: cv2.ROTATE_90_COUNTERCLOCKWISE, 180: cv2.ROTATE_180, 270: cv2.ROTATE_90_CLOCKWISE}
    return cv2.rotate(image, codes[correction]) if correction in codes else image


def _ocr_at(engine: OcrEngine, gray: np.ndarray, correction: int) -> tuple[str, float, float]:
    prepared, skew = enhance(_rotate_upright(gray, correction))
    text, conf = engine.recognize(prepared)
    return text, conf, skew


def process_pages(path: str, start: int, end: int, cfg: Settings, force_ocr: bool = False) -> Iterator[dict]:
    """Yield one result per page in [start, end] (1-based, inclusive).

    A failure on one page is reported for that page and does not stop the range.
    """
    engine: OcrEngine | None = None
    with open_pdf(path, cfg) as doc:
        last = min(end, doc.page_count)
        for page_no in range(max(1, start), last + 1):
            started = time.monotonic()
            result = {"page": page_no, "method": None, "text": "", "confidence": 0.0,
                      "rotation": 0, "skew": 0.0, "engine": None, "error": None}
            try:
                page = doc[page_no - 1]
                native = normalize_unicode(page.get_text("text") or "").strip()
                if len(native) >= cfg.min_text_chars and not force_ocr:
                    result.update(method="text", text=native, confidence=cfg.native_text_confidence)
                elif cfg.max_ocr_pages and page_no > cfg.max_ocr_pages and not force_ocr:
                    result.update(method="skipped", text=native)
                else:
                    engine = engine or make_engine(cfg)
                    gray = render_page(page, cfg)
                    # OSD reports the clockwise angle the text is rotated by; undo it.
                    correction = (360 - engine.orientation(gray)) % 360
                    text, conf, skew = _ocr_at(engine, gray, correction)
                    if conf < LOW_CONFIDENCE:
                        # OSD is unreliable on sparse pages. Probe the other orientations on a
                        # quarter-size image (cheap) and re-run full OCR only if one is clearly better.
                        # Pages that are low-confidence for other reasons (unsupported script,
                        # handwriting) then cost one probe set instead of three full OCR passes.
                        small = cv2.resize(gray, None, fx=0.5, fy=0.5, interpolation=cv2.INTER_AREA)
                        _, base_conf, _ = _ocr_at(engine, small, correction)
                        best, best_conf = correction, base_conf
                        for alternative in (0, 90, 180, 270):
                            if alternative == correction:
                                continue
                            _, alt_conf, _ = _ocr_at(engine, small, alternative)
                            if alt_conf > best_conf + 0.15:
                                best, best_conf = alternative, alt_conf
                        if best != correction:
                            text, conf, skew = _ocr_at(engine, gray, best)
                            correction = best
                    result.update(method="ocr", text=normalize_unicode(text), confidence=conf, rotation=correction,
                                  skew=skew, engine=engine.name)
                    del gray
            except Exception as exc:  # noqa: BLE001 - isolate per-page failures
                result["error"] = f"{type(exc).__name__}: {exc}"[:500]
            result["ms"] = int((time.monotonic() - started) * 1000)
            yield result
