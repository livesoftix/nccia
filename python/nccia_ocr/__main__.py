"""Command line interface used by Laravel queue jobs.

    python -m nccia_ocr health
    python -m nccia_ocr inspect FILE
    python -m nccia_ocr pages FILE --start 1 --end 25        (JSON lines, one per page)
    python -m nccia_ocr extract --pages-file PAGES.jsonl [--layout NAME]
    python -m nccia_ocr benchmark DIR [--engines tesseract,paddle]

Exit codes: 0 ok, 2 PDF rejected (malformed/encrypted/too large), 1 other error.
All output is JSON on stdout; diagnostics go to stderr.
"""

from __future__ import annotations

import argparse
import json
import sys

from . import __version__
from .config import settings
from .pdfio import PdfRejected


def _emit(obj) -> None:
    sys.stdout.write(json.dumps(obj, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def cmd_health(_args) -> int:
    cfg = settings()
    info = {"version": __version__, "python": sys.version.split()[0], "engine": cfg.engine,
            "tesseract": cfg.tesseract_cmd, "languages_requested": cfg.languages}
    try:
        import pytesseract

        if cfg.tesseract_cmd:
            pytesseract.pytesseract.tesseract_cmd = cfg.tesseract_cmd
            info["tesseract_version"] = str(pytesseract.get_tesseract_version())
            info["languages_installed"] = sorted(pytesseract.get_languages(config=""))
    except Exception as exc:  # noqa: BLE001
        info["tesseract_error"] = str(exc)
    try:
        import paddleocr  # noqa: F401  # type: ignore[import-not-found]

        info["paddleocr"] = True
    except Exception:  # noqa: BLE001
        info["paddleocr"] = False
    missing = [l for l in cfg.languages.split("+") if l and l not in info.get("languages_installed", [])]
    info["ok"] = bool(cfg.tesseract_cmd) and not missing
    if missing:
        info["languages_missing"] = missing
    _emit(info)
    return 0 if info["ok"] else 1


def cmd_inspect(args) -> int:
    from .pdfio import inspect

    _emit(inspect(args.file, settings()))
    return 0


def cmd_pages(args) -> int:
    from .ocr import process_pages

    for result in process_pages(args.file, args.start, args.end, settings(), force_ocr=args.force_ocr):
        _emit(result)
    return 0


def cmd_extract(args) -> int:
    from .extract import extract

    pages = []
    with open(args.pages_file, encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if line:
                pages.append(json.loads(line))
    _emit(extract(pages, args.layout).model_dump())
    return 0


def cmd_benchmark(args) -> int:
    from .benchmark import run

    _emit(run(args.directory, [e for e in args.engines.split(",") if e], args.languages))
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="nccia_ocr")
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("health").set_defaults(func=cmd_health)
    p = sub.add_parser("inspect")
    p.add_argument("file")
    p.set_defaults(func=cmd_inspect)
    p = sub.add_parser("pages")
    p.add_argument("file")
    p.add_argument("--start", type=int, default=1)
    p.add_argument("--end", type=int, default=10)
    p.add_argument("--force-ocr", action="store_true")
    p.set_defaults(func=cmd_pages)
    p = sub.add_parser("extract")
    p.add_argument("--pages-file", required=True)
    p.add_argument("--layout", default=None)
    p.set_defaults(func=cmd_extract)
    p = sub.add_parser("benchmark")
    p.add_argument("directory")
    p.add_argument("--engines", default="tesseract")
    p.add_argument("--languages", default=None)
    p.set_defaults(func=cmd_benchmark)

    args = parser.parse_args(argv)
    try:
        return args.func(args)
    except PdfRejected as exc:
        _emit({"error": str(exc), "rejected": True})
        return 2
    except Exception as exc:  # noqa: BLE001
        _emit({"error": f"{type(exc).__name__}: {exc}"[:1000]})
        return 1


if __name__ == "__main__":
    sys.exit(main())
