"""Field result models and value normalisers/validators."""

from __future__ import annotations

import re
from datetime import date, datetime
from typing import Any

from pydantic import BaseModel, Field

DATE_FORMATS = ("%d-%m-%Y", "%d/%m/%Y", "%d.%m.%Y", "%Y-%m-%d", "%d-%m-%y", "%d/%m/%y", "%d %B %Y", "%d %b %Y", "%B %d, %Y")
EMAIL_RE = re.compile(r"^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$", re.I)
# OCR commonly confuses these glyphs inside digit-only fields.
DIGIT_FIXES = str.maketrans({"O": "0", "o": "0", "D": "0", "I": "1", "l": "1", "|": "1", "S": "5", "s": "5", "B": "8", "Z": "2"})


class Candidate(BaseModel):
    raw: str
    value: Any = None
    page: int | None = None
    confidence: float = 0.0
    valid: bool = False
    issues: list[str] = Field(default_factory=list)


class FieldResult(BaseModel):
    name: str
    value: Any = None
    raw: str | None = None
    page: int | None = None
    confidence: float = 0.0
    valid: bool = False
    required: bool = False
    issues: list[str] = Field(default_factory=list)
    candidates: list[Candidate] = Field(default_factory=list)


class ExtractionResult(BaseModel):
    layout: str
    fields: dict[str, FieldResult]
    accused: list[dict[str, Any]] = Field(default_factory=list)
    circle_hint: str | None = None
    page_count: int = 0
    ocr_pages: int = 0
    mean_confidence: float = 0.0
    warnings: list[str] = Field(default_factory=list)


def _digits(raw: str, fix_glyphs: bool) -> str:
    text = raw.translate(DIGIT_FIXES) if fix_glyphs else raw
    return re.sub(r"\D", "", text)


def normalize_cnic(raw: str, from_ocr: bool) -> tuple[str | None, list[str]]:
    digits = _digits(raw, from_ocr)
    if len(digits) != 13:
        return None, [f"CNIC must have 13 digits (found {len(digits)})"]
    if digits[0] not in "1234567":
        return None, ["CNIC first digit must be 1-7 (province code)"]
    issues = ["glyphs corrected to digits"] if from_ocr and _digits(raw, False) != digits else []
    return f"{digits[:5]}-{digits[5:12]}-{digits[12]}", issues


def normalize_phone(raw: str, from_ocr: bool) -> tuple[str | None, list[str]]:
    digits = _digits(raw, from_ocr)
    if digits.startswith("0092"):
        digits = digits[4:]
    elif digits.startswith("92") and len(digits) == 12:
        digits = digits[2:]
    if len(digits) == 10 and digits.startswith("3"):
        digits = "0" + digits
    if re.fullmatch(r"03\d{9}", digits):
        return digits, []
    if re.fullmatch(r"0[1-9]\d{8,9}", digits):  # landline with area code
        return digits, ["landline number"]
    return None, [f"not a valid Pakistani phone number ({len(digits)} digits)"]


def normalize_date(raw: str, from_ocr: bool) -> tuple[str | None, list[str]]:
    text = re.sub(r"\s+", " ", raw.strip().rstrip("."))
    for fmt in DATE_FORMATS:
        try:
            parsed = datetime.strptime(text, fmt).date()
        except ValueError:
            continue
        if parsed.year < 1950:
            return None, ["date before 1950"]
        if parsed > date.today():
            return None, ["date is in the future"]
        return parsed.isoformat(), []
    return None, ["unrecognised date format"]


def normalize_email(raw: str, from_ocr: bool) -> tuple[str | None, list[str]]:
    value = raw.strip().strip(".,;").lower()
    return (value, []) if EMAIL_RE.match(value) else (None, ["invalid email address"])


def normalize_amount(raw: str, from_ocr: bool) -> tuple[float | None, list[str]]:
    cleaned = re.sub(r"[^\d.]", "", raw.replace(",", ""))
    try:
        value = float(cleaned)
    except ValueError:
        return None, ["amount is not a number"]
    return (value, []) if value >= 0 else (None, ["negative amount"])


def normalize_text(raw: str, from_ocr: bool, max_len: int = 255, min_len: int = 2) -> tuple[str | None, list[str]]:
    value = re.sub(r"\s+", " ", raw).strip().strip(":-,.\"'").strip()
    if len(value) < min_len:
        return None, ["value too short"]
    if len(value) > max_len:
        return value[:max_len], [f"truncated to {max_len} characters"]
    return value, []


def normalize_ref(raw: str, from_ocr: bool) -> tuple[str | None, list[str]]:
    value = re.sub(r"\s+", "", raw).strip(".,;:").upper()
    if not re.fullmatch(r"[A-Z0-9][A-Z0-9/\-]{2,60}", value):
        return None, ["reference contains unexpected characters"]
    return value, []


NORMALIZERS = {
    "cnic": normalize_cnic,
    "phone": normalize_phone,
    "date": normalize_date,
    "email": normalize_email,
    "amount": normalize_amount,
    "ref": normalize_ref,
    "text": normalize_text,
}
