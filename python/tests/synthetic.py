"""Synthetic test documents. All names, CNICs and numbers are invented."""

from __future__ import annotations

import json
from pathlib import Path

import cv2
import fitz
import numpy as np

REPORT = """VERIFICATION REPORT
Tracking No: CCW-LHR-{seq:05d}/2026
Inquiry No: E/{seq}/2026
Verification Date: 12-03-2026
COMPLAINANT DETAILS
Name: {name}
Father Name: {father}
Gender: Male
CNIC No: {cnic}
Occupation: Shopkeeper
Mobile Number: {phone}
Current Address: House 12 Street 4 Model Town Lahore
Permanent Address: Village Test Kalan District Kasur
Crime Category: Online Financial Fraud
City of Occurrence: Lahore
Amount Involved: Rs. 45,000
Cyber Crime Circle: {circle}
RECOMMENDATIONS: Permission to Register Enquiry
Reporting Officer: Test Officer ASI
"""

TRUTH_KEYS = ("victim_full_name", "victim_father_name", "victim_cnic", "victim_phone", "inquiry_no", "tracking_no")


def sample(seq: int = 1, circle: str = "Lahore") -> tuple[str, dict]:
    cnic = f"35202-{1000000 + seq:07d}-{seq % 10}"
    phone = f"0300{1000000 + seq:07d}"
    values = {"seq": seq, "name": f"Test Person {chr(65 + seq % 26)}", "father": "Sample Father", "cnic": cnic,
              "phone": phone, "circle": circle}
    truth = {"victim_full_name": values["name"], "victim_father_name": "Sample Father", "victim_cnic": cnic,
             "victim_phone": phone, "inquiry_no": f"E/{seq}/2026", "tracking_no": f"CCW-LHR-{seq:05d}/2026"}
    return REPORT.format(**values), truth


def text_pdf(path: Path, body: str, extra_pages: int = 0) -> Path:
    doc = fitz.open()
    page = doc.new_page()
    page.insert_textbox(fitz.Rect(50, 50, 560, 800), body, fontsize=11, fontname="helv")
    for i in range(extra_pages):
        doc.new_page().insert_text((72, 72), f"Annexure page {i + 2}: supporting statement text for testing.", fontsize=11)
    doc.save(path)
    doc.close()
    return path


def scanned_pdf(path: Path, body: str, rotate: int = 0, skew: float = 0.0, noise: int = 0, dpi: int = 200) -> Path:
    """Render a text page to an image (no text layer), optionally rotated/skewed/noisy."""
    src = fitz.open()
    page = src.new_page()
    page.insert_textbox(fitz.Rect(50, 50, 560, 800), body, fontsize=12, fontname="helv")
    pix = page.get_pixmap(dpi=dpi, colorspace=fitz.csGRAY, alpha=False)
    img = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width).copy()
    src.close()
    if skew:
        h, w = img.shape
        m = cv2.getRotationMatrix2D((w / 2, h / 2), skew, 1.0)
        img = cv2.warpAffine(img, m, (w, h), borderValue=255)
    if noise:
        rng = np.random.default_rng(7)
        mask = rng.random(img.shape) < noise / 1000.0
        img[mask] = 0
    if rotate:
        codes = {90: cv2.ROTATE_90_CLOCKWISE, 180: cv2.ROTATE_180, 270: cv2.ROTATE_90_COUNTERCLOCKWISE}
        img = cv2.rotate(img, codes[rotate])
    ok, png = cv2.imencode(".png", img)
    assert ok
    out = fitz.open()
    h, w = img.shape
    pg = out.new_page(width=w * 72 / dpi, height=h * 72 / dpi)
    pg.insert_image(pg.rect, stream=png.tobytes())
    out.save(path)
    out.close()
    return path


def urdu_text_pdf(path: Path) -> Path:
    """Urdu right-to-left text layer (shaped by PyMuPDF's HTML engine)."""
    doc = fitz.open()
    page = doc.new_page()
    html = ('<div dir="rtl" style="font-size:14px">نام: علی احمد<br/>شناختی کارڈ نمبر: 35202-1234567-1</div>'
            '<div>CNIC No: 35202-1234567-1</div>')
    page.insert_htmlbox(fitz.Rect(50, 50, 560, 300), html)
    doc.save(path)
    doc.close()
    return path


def write_benchmark_set(directory: Path, count: int = 6) -> None:
    directory.mkdir(parents=True, exist_ok=True)
    for seq in range(1, count + 1):
        body, truth = sample(seq)
        if seq % 3 == 0:
            scanned_pdf(directory / f"doc{seq}.pdf", body, skew=2.0, noise=3)
        elif seq % 3 == 1:
            scanned_pdf(directory / f"doc{seq}.pdf", body)
        else:
            text_pdf(directory / f"doc{seq}.pdf", body)
        (directory / f"doc{seq}.truth.json").write_text(json.dumps(truth), encoding="utf-8")
