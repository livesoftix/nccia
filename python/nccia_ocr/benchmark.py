"""Benchmark OCR engines against manually verified ground truth.

Directory layout: for every ``sample.pdf`` provide ``sample.truth.json`` holding the
verified field values, e.g. {"victim_cnic": "35202-1234567-1", "victim_phone": "03001234567"}.
Only fields present in the truth file are scored.
"""

from __future__ import annotations

import dataclasses
import json
import time
from pathlib import Path

from .config import settings
from .extract import extract
from .ocr import process_pages
from .pdfio import open_pdf


def _same(a, b) -> bool:
    if a is None or b is None:
        return False
    if isinstance(a, float) or isinstance(b, float):
        try:
            return abs(float(a) - float(b)) < 0.01
        except (TypeError, ValueError):
            return False
    return str(a).strip().casefold() == str(b).strip().casefold()


def run(directory: str, engines: list[str], languages: str | None = None) -> dict:
    base = settings()
    docs = sorted(p for p in Path(directory).glob("*.pdf") if p.with_suffix(".truth.json").exists())
    report = {"documents": len(docs), "engines": {}}
    for engine in engines:
        cfg = dataclasses.replace(base, engine=engine, languages=languages or base.languages)
        per_field: dict[str, dict[str, int]] = {}
        needs_review = pages_total = 0
        seconds = 0.0
        errors: list[str] = []
        for pdf in docs:
            truth = json.loads(pdf.with_suffix(".truth.json").read_text(encoding="utf-8"))
            started = time.monotonic()
            try:
                with open_pdf(str(pdf), cfg) as doc:
                    count = doc.page_count
                pages = list(process_pages(str(pdf), 1, count, cfg))
            except Exception as exc:  # noqa: BLE001
                errors.append(f"{pdf.name}: {exc}")
                continue
            seconds += time.monotonic() - started
            pages_total += len(pages)
            result = extract(pages)
            if any((f.required and not f.valid) or any("conflict" in i for i in f.issues) for f in result.fields.values()):
                needs_review += 1
            for name, expected in truth.items():
                stats = per_field.setdefault(name, {"tp": 0, "fp": 0, "fn": 0})
                got = result.fields.get(name)
                value = got.value if got and got.valid else None
                if value is None:
                    stats["fn"] += 1 if expected not in (None, "") else 0
                elif _same(value, expected):
                    stats["tp"] += 1
                else:
                    stats["fp"] += 1
                    stats["fn"] += 1 if expected not in (None, "") else 0
        fields = {}
        for name, s in per_field.items():
            precision = s["tp"] / (s["tp"] + s["fp"]) if s["tp"] + s["fp"] else None
            recall = s["tp"] / (s["tp"] + s["fn"]) if s["tp"] + s["fn"] else None
            fields[name] = {**s, "precision": round(precision, 4) if precision is not None else None,
                            "recall": round(recall, 4) if recall is not None else None}
        report["engines"][engine] = {
            "languages": cfg.languages,
            "fields": fields,
            "cnic_exact_match": fields.get("victim_cnic", {}).get("recall"),
            "phone_exact_match": fields.get("victim_phone", {}).get("recall"),
            "review_rate": round(needs_review / len(docs), 4) if docs else None,
            "seconds_per_page": round(seconds / pages_total, 3) if pages_total else None,
            "errors": errors,
        }
    return report
