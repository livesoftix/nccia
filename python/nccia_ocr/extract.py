"""Layout-driven field extraction over per-page text."""

from __future__ import annotations

import json
import re
from functools import lru_cache
from pathlib import Path
from typing import Iterable

from .fields import NORMALIZERS, Candidate, ExtractionResult, FieldResult, normalize_text

LAYOUT_DIR = Path(__file__).resolve().parent / "layouts"
FLAGS = re.IGNORECASE | re.MULTILINE
RELATION_RE = re.compile(r"^(.+?)\s+(?:S/O|D/O|W/O|son of|daughter of|wife of)\s+(.+)$", re.IGNORECASE)


@lru_cache(maxsize=None)
def load_layout(name: str) -> dict:
    if not re.fullmatch(r"[a-z0-9_]+", name):
        raise ValueError("invalid layout name")
    path = LAYOUT_DIR / f"{name}.json"
    layout = json.loads(path.read_text(encoding="utf-8"))
    for spec in layout["fields"]:
        spec["_compiled"] = [re.compile(p, FLAGS) for p in spec["patterns"]]
        spec["_format"] = re.compile(spec["format"]) if spec.get("format") else None
    layout["_circle"] = [re.compile(p, FLAGS) for p in layout.get("circle_patterns", [])]
    return layout


def available_layouts() -> list[str]:
    return sorted(p.stem for p in LAYOUT_DIR.glob("*.json"))


def detect_layout(pages: list[dict]) -> str:
    sample = "\n".join(p.get("text", "") for p in pages[:3])
    best, best_score = "verification_report", -1
    for name in available_layouts():
        score = sum(1 for pattern in load_layout(name).get("detect", []) if re.search(pattern, sample, FLAGS))
        if score > best_score:
            best, best_score = name, score
    return best


def _candidates(spec: dict, pages: Iterable[dict]) -> list[Candidate]:
    normalizer = NORMALIZERS[spec["type"]]
    found: list[Candidate] = []
    for page in pages:
        text = page.get("text") or ""
        if not text:
            continue
        from_ocr = page.get("method") == "ocr"
        page_conf = float(page.get("confidence") or 0.0)
        for rank, pattern in enumerate(spec["_compiled"]):
            for match in pattern.finditer(text):
                raw = match.group(1).strip()
                if spec["type"] == "text":
                    value, issues = normalize_text(raw, from_ocr, max_len=int(spec.get("max_len", 255)))
                else:
                    value, issues = normalizer(raw, from_ocr)
                if value is not None and spec["_format"] is not None and not spec["_format"].match(str(value)):
                    issues = issues + [f"unexpected format for {spec['name']}"]
                    value = None
                confidence = page_conf * (1.0 if rank == 0 else 0.9)
                if any("corrected" in issue for issue in issues):
                    confidence *= 0.9
                found.append(Candidate(raw=raw, value=value, page=page.get("page"),
                                       confidence=round(confidence if value is not None else 0.0, 4),
                                       valid=value is not None, issues=issues))
            if found:
                break  # higher-priority pattern matched; lower patterns are fallbacks only
    return found


def _resolve(spec: dict, candidates: list[Candidate]) -> FieldResult:
    result = FieldResult(name=spec["name"], required=bool(spec.get("required")), candidates=candidates[:10])
    valid = [c for c in candidates if c.valid]
    if not candidates:
        if result.required:
            result.issues.append("required field not found")
        return result
    if not valid:
        best = max(candidates, key=lambda c: len(c.raw))
        result.raw, result.page = best.raw, best.page
        result.issues.extend(best.issues or ["value failed validation"])
        return result

    def key(c: Candidate) -> str:
        return str(c.value).casefold() if isinstance(c.value, str) else str(c.value)

    distinct = {key(c) for c in valid}
    best = max(valid, key=lambda c: c.confidence)
    result.value, result.raw, result.page, result.confidence = best.value, best.raw, best.page, best.confidence
    result.issues.extend(best.issues)
    if len(distinct) > 1:
        result.issues.append(f"conflicting values found ({len(distinct)} different)")
        result.valid = False
    else:
        result.valid = True
    return result


def _split_relation(fields: dict[str, FieldResult]) -> None:
    """Split "Name S/O Father" when no separate father/guardian field was found."""
    name = fields.get("victim_full_name")
    father = fields.get("victim_father_name")
    if not name or not isinstance(name.value, str):
        return
    match = RELATION_RE.match(name.value)
    if not match:
        return
    name.value = match.group(1).strip()
    if father is not None and father.value is None:
        father.value = match.group(2).strip()
        father.raw, father.page, father.confidence = name.raw, name.page, name.confidence
        father.valid = True
        father.issues = ["split from complainant name"]


def extract(pages: list[dict], layout_name: str | None = None) -> ExtractionResult:
    pages = sorted((p for p in pages if not p.get("error") and p.get("method") != "skipped"), key=lambda p: p.get("page") or 0)
    name = layout_name or detect_layout(pages)
    layout = load_layout(name)

    # The report itself occupies the first page(s); annexes (statements, bank slips,
    # screenshots) name other people and addresses. Fields come from the report pages;
    # an annex-only value is surfaced for the reviewer but never accepted automatically.
    primary_count = int(layout.get("primary_pages") or 0)
    if primary_count:
        # The report starts at the first page carrying the layout's header (a covering
        # letter may precede it); it spans primary_pages from there.
        start = next((i for i, p in enumerate(pages)
                      if sum(1 for d in layout.get("detect", []) if re.search(d, p.get("text") or "", FLAGS)) >= 2), 0)
        primary = pages[start:start + primary_count]
        annex = pages[:start] + pages[start + primary_count:]
    else:
        primary, annex = pages, []
    fields = {}
    for spec in layout["fields"]:
        result = _resolve(spec, _candidates(spec, primary))
        annex_candidates = _candidates(spec, annex) if annex else []
        if result.value is None and not result.candidates and annex_candidates:
            result = _resolve(spec, annex_candidates)
            result.valid = False
            result.issues = [i for i in result.issues if i != "required field not found"]
            result.issues.append(f"found only outside the report pages (page {result.page})")
        elif annex_candidates:
            result.candidates = (result.candidates + annex_candidates)[:10]
        fields[spec["name"]] = result
    _split_relation(fields)

    circle_hint = None
    for page in primary:
        for pattern in layout["_circle"]:
            match = pattern.search(page.get("text") or "")
            if match:
                circle_hint = re.sub(r"\s+", " ", match.group(1)).strip()
                break
        if circle_hint:
            break

    ocr_pages = [p for p in pages if p.get("method") == "ocr"]
    # Confidence of the pages the fields come from; annex quality must not block a clean report.
    confs = [float(p.get("confidence") or 0.0) for p in primary]
    warnings = []
    if not pages:
        warnings.append("no readable pages")
    if ocr_pages and sum(len(p.get("text") or "") for p in ocr_pages) < 20 * len(ocr_pages):
        warnings.append("OCR produced very little text; page may be blank, handwritten or illegible")

    return ExtractionResult(
        layout=name,
        fields=fields,
        circle_hint=circle_hint,
        page_count=len(pages),
        ocr_pages=len(ocr_pages),
        mean_confidence=round(sum(confs) / len(confs), 4) if confs else 0.0,
        warnings=warnings,
    )
