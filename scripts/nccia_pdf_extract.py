#!/usr/bin/env python3
"""Extract NCCIA/FIA verification report fields from complaint PDFs.

Compatible with Python 3.6+ (shared hosting).
"""

import io
import json
import os
import re
import sys
from pathlib import Path


def normalize_cnic(raw):
    if not raw:
        return None
    digits = re.sub(r"\D", "", raw)
    if len(digits) != 13:
        return raw.strip() or None
    return f"{digits[:5]}-{digits[5:12]}-{digits[12]}"


def parse_date(raw):
    if not raw:
        return None
    raw = raw.strip()
    for fmt in ("%d-%m-%Y", "%d/%m/%Y", "%Y-%m-%d", "%d-%m-%y", "%d/%m/%y"):
        try:
            from datetime import datetime

            return datetime.strptime(raw, fmt).strftime("%Y-%m-%d")
        except ValueError:
            continue
    return None


def inquiry_ref_from_filename(filename):
    stem = Path(filename).stem
    m = re.match(r"^(\d+)-(\d+)$", stem)
    if m:
        return f"E/{m.group(1)}/{m.group(2)}"
    m = re.match(r"^E[/\-]?(\d+)[/\-](\d+)$", stem, re.I)
    if m:
        return f"E/{m.group(1)}/{m.group(2)}"
    return None


def clean_value(val):
    if not val:
        return None
    val = val.strip().strip('"').strip("'")
    val = re.sub(r"\s+", " ", val)
    return val or None


def first_match(text, patterns, flags=re.I | re.S):
    for pattern in patterns:
        m = re.search(pattern, text, flags)
        if m:
            val = m.group(1).strip()
            val = re.sub(r"\s+", " ", val)
            if val:
                return val
    return None


def configure_tesseract():
    try:
        import pytesseract
    except Exception:
        return
    candidates = [
        os.environ.get("TESSERACT_CMD"),
        os.path.join(os.path.dirname(sys.executable), "tesseract"),
        os.path.expanduser("~/miniconda3/bin/tesseract"),
        os.path.expanduser("~/miniforge3/bin/tesseract"),
        os.path.expanduser("~/.local/bin/tesseract"),
        "/usr/bin/tesseract",
        "/usr/local/bin/tesseract",
    ]
    for cmd in candidates:
        if cmd and os.path.isfile(cmd) and os.access(cmd, os.X_OK):
            pytesseract.pytesseract.tesseract_cmd = cmd
            tessdata = os.path.normpath(os.path.join(os.path.dirname(cmd), "..", "share", "tessdata"))
            if os.path.isdir(tessdata) and not os.environ.get("TESSDATA_PREFIX"):
                os.environ["TESSDATA_PREFIX"] = tessdata
            return


def ocr_page(page):
    import fitz
    import subprocess
    import tempfile

    pix = page.get_pixmap(matrix=fitz.Matrix(2, 2))
    png_bytes = pix.tobytes("png")

    try:
        import pytesseract
        from PIL import Image

        configure_tesseract()
        img = Image.open(io.BytesIO(png_bytes))
        text = pytesseract.image_to_string(img)
        if len(text.strip()) > 40:
            return text
    except Exception:
        pass

    # Node tesseract.js fallback
    try:
        script_dir = Path(__file__).resolve().parent
        node_script = script_dir / "ocr-png.cjs"
        if node_script.is_file():
            with tempfile.NamedTemporaryFile(suffix=".png", delete=False) as tmp:
                tmp.write(png_bytes)
                tmp_path = tmp.name

            try:
                proc = subprocess.run(
                    ["node", str(node_script), tmp_path],
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    timeout=90,
                    encoding="utf-8",
                    errors="replace",
                )
                if proc.returncode == 0 and len(proc.stdout.strip()) > 20:
                    return proc.stdout.strip()
            finally:
                try:
                    os.unlink(tmp_path)
                except Exception:
                    pass
    except Exception:
        pass

    return ""


def extract_text_from_pdf(pdf_path, max_pages=3):
    fitz_mod = None
    try:
        import fitz as fitz_mod
    except Exception:
        try:
            import pymupdf as fitz_mod
        except Exception:
            pass

    if fitz_mod is not None:
        try:
            doc = fitz_mod.open(pdf_path)
            page_count = doc.page_count
            chunks = []
            used_ocr = False

            for i in range(min(max_pages, page_count)):
                page = doc[i]
                text = page.get_text("text") or ""
                if len(text.strip()) < 40:
                    ocr_text = ocr_page(page)
                    if ocr_text.strip():
                        text = ocr_text
                        used_ocr = True
                chunks.append(text)
                combined = "\n".join(chunks)
                if re.search(r"\d{5}[-\s]?\d{7}[-\s]?\d", combined) and re.search(
                    r"verification|complainant|tracking", combined, re.I
                ):
                    break

            doc.close()
            return "\n".join(chunks), page_count, used_ocr
        except Exception:
            pass

    # Fallback 1: pypdf / PyPDF2
    for pypdf_name in ("pypdf", "PyPDF2"):
        try:
            pdf_lib = __import__(pypdf_name)
            reader = pdf_lib.PdfReader(pdf_path)
            chunks = [p.extract_text() or "" for p in reader.pages[:max_pages]]
            return "\n".join(chunks), len(reader.pages), False
        except Exception:
            pass

    # Fallback 2: pdftotext CLI
    try:
        import subprocess

        proc = subprocess.run(
            ["pdftotext", "-l", str(max_pages), pdf_path, "-"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            encoding="utf-8",
            errors="replace",
        )
        if proc.returncode == 0 and len(proc.stdout.strip()) > 10:
            return proc.stdout.strip(), max_pages, False
    except Exception:
        pass

    # Fallback 3: Raw text streams from PDF binary
    try:
        with open(pdf_path, "rb") as f:
            raw_bytes = f.read(2 * 1024 * 1024)
        matches = re.findall(rb"\(([^()\\\\]*(?:\\\\.[^()\\\\]*)*)\)", raw_bytes)
        text = " ".join(m.decode("latin1", errors="ignore") for m in matches if len(m) > 2)
        if len(text.strip()) > 30:
            return text, 1, False
    except Exception:
        pass

    return "", 0, False


def split_name_father(full_name):
    if not full_name:
        return None, None
    m = re.match(r"^(.+?)\s+S/O\s+(.+)$", full_name, re.I)
    if m:
        return m.group(1).strip(), m.group(2).strip()
    m = re.match(r"^(.+?)\s+D/O\s+(.+)$", full_name, re.I)
    if m:
        return m.group(1).strip(), m.group(2).strip()
    return full_name.strip(), None


def map_recommendation(text):
    if not text:
        return None
    lower = text.lower()
    if "register enq" in lower or "registration of enquiry" in lower:
        return "enquiry_registration"
    if "closure" in lower or "close" in lower:
        return "closure"
    if "merge" in lower:
        return "merge"
    if "transfer" in lower:
        return "transfer"
    return None


def extract_accused_list_from_text(text):
    accused = []
    seen = set()

    for m in re.finditer(r"To\s+([A-Z\s]{3,35})\s+(PK\d{2}[A-Z0-9]{16,24}|\d{10,24})\s+(?:Bank\s+)?([A-Za-z\s]{3,25})?(?:[\s\S]*?(?:Paid|Debited|Amount)[\s\S]*?(?:Rs\.?|PKR)\s*([\d,]+))?", text, re.I):
        name = clean_value(m.group(1))
        acc = m.group(2).strip()
        bank = clean_value(m.group(3)) or ""
        amount = m.group(4).strip() if m.group(4) else ""
        if not name or re.match(r"^(abid\s+hussain|customer|service|status|bank|from|amount)", name, re.I):
            continue
        key = (name.lower(), acc.lower())
        if key not in seen:
            seen.add(key)
            accused.append({
                "name": name,
                "father_name": "",
                "cnic": "",
                "mobile_no": "",
                "phone": "",
                "bank_account": acc,
                "bank_name": bank,
                "description": f"Bank: {bank} | Acc/IBAN: {acc}" + (f" | Amount: Rs. {amount}" if amount else ""),
            })

    for m in re.finditer(r"Name[\s\=]+([A-Z\s]{3,35})\s+Account[\s\=]+(\d+)(?:\s+IBAN[\s\=]+(PK\d{2}[A-Z0-9]+))?(?:\s+([A-Za-z\s]+)bank)?", text, re.I):
        name = clean_value(m.group(1))
        acc = m.group(2).strip()
        iban = m.group(3).strip() if m.group(3) else ""
        bank = clean_value(m.group(4)) or ""
        if not name:
            continue
        target_acc = iban or acc
        key = (name.lower(), target_acc.lower())
        if key not in seen:
            seen.add(key)
            accused.append({
                "name": name,
                "father_name": "",
                "cnic": "",
                "mobile_no": "",
                "phone": "",
                "bank_account": target_acc,
                "bank_name": bank,
                "description": f"Bank: {bank} | Acc: {acc}" + (f" | IBAN: {iban}" if iban else ""),
            })

    m_hira = re.search(r"(?:Sailkot\s+Hira|Hira\s+Rehman)[^\d]{0,20}(\+?92[\s\-]?\d{10}|03\d{9})", text, re.I)
    if m_hira:
        phone = re.sub(r"\D", "", m_hira.group(1))
        if phone.startswith("92") and len(phone) > 10:
            phone = phone[2:]
        if len(phone) == 10:
            phone = "0" + phone
        key = ("hira", phone)
        if key not in seen:
            seen.add(key)
            accused.append({
                "name": "Hira Rehman / Sailkot Hira",
                "father_name": "",
                "cnic": "",
                "mobile_no": phone,
                "phone": phone,
                "description": "WhatsApp / Fraud App Operator (mallbyvip.com / BestBuy)",
            })

    return accused


def parse_verification_report(text, filename):
    tracking_no = first_match(
        text,
        [
            r"Tracking\s*No[\s\.\:\-]+([A-Z0-9][A-Z0-9\-\/]+)",
            r"Tracking\s*Number[\s\.\:\-]+([A-Z0-9][A-Z0-9\-\/]+)",
            r"VERIFICATION\s*REPORT[\s\S]{0,100}?No[\s\.\:\-]+([A-Z0-9][A-Z0-9\-\/]+)",
            r"\b(CCW-[A-Z0-9\-\/]+)\b",
            r"No[\s\.\:\-]+(CCW-[A-Z0-9\-\/]+)",
        ],
    )

    verification_date = parse_date(
        first_match(text, [r"Verification\s*Date[\s\.\:\-]+([\d\-\/\.]{8,12})"])
    )
    assignment_date = parse_date(
        first_match(text, [r"Assignment\s*Date[\s\.\:\-]+([\d\-\/\.]{8,12})"])
    )

    full_name = first_match(
        text,
        [
            r"COMPLAINANT\s*DETAILS[\s\S]{0,80}?Name[\s\.\:\-]+(.+?)(?:Gender|CNIC|Occupation|Contact|Mobile|$)",
            r"Name[\s\.\:\-]+(.+?)(?:Gender|CNIC|Occupation|Contact|Mobile|$)",
            r"Name\s*:?\s*(.+?)\s+Gender\s*:",
        ],
    )
    victim_name, victim_father_name = split_name_father(full_name)

    gender_raw = first_match(text, [r"Gender[\s\.\:\-]+(Male|Female|Other)", r"Gender[\s\.\:\-]+(\w+)"])
    victim_gender = gender_raw.lower() if gender_raw else None
    if not victim_gender and re.search(r"\bMale\b", text, re.I):
        victim_gender = "male"
    elif not victim_gender and re.search(r"\bFemale\b", text, re.I):
        victim_gender = "female"

    cnic_raw = first_match(
        text,
        [
            r"CNIC\s*No[\s\.\:\-]+([\d\-]{13,17})",
            r"CNIC\s*No[\s\.\:\-]+([\d]{13})",
            r"CNIC[\s\.\:\-]+([\d\-]{13,17})",
            r"CNIC[\s\.\:\-]+([\d]{13})",
        ],
    )
    victim_cnic = normalize_cnic(cnic_raw)

    victim_occupation = first_match(
        text,
        [r"Occupation[\s\.\:\-]+(.+?)(?:Contact|Mobile|Address|Email|Details|Addresses|$)", r"Occupation[\s\.\:\-]+(.+?)(?:\n|$)"],
    )

    victim_phone = first_match(
        text,
        [
            r"Mobile\s*Number[\s\.\:\-]+([\d\-\s]+)",
            r"Contact\s*Details[\s\S]{0,40}?Mobile\s*Number[\s\.\:\-]+([\d\-\s]+)",
            r"Contact\s*Details[\s\.\:\-]+([\d\-\s]+)",
            r"Contact\s*(?:No\.?|Number)?[\s\.\:\-]+(0?3\d{9})",
            r"\b(0?3\d{2}[\-\s]?\d{7})\b",
        ],
    )
    if victim_phone:
        victim_phone = re.sub(r"\D", "", victim_phone)
        if victim_phone.startswith("92") and len(victim_phone) > 10:
            victim_phone = victim_phone[2:]
        if len(victim_phone) == 10:
            victim_phone = "0" + victim_phone

    address = first_match(
        text,
        [
            r"Current\s*Address[\s\.\:\-]+(.+?)(?:Permanent|BRIEF|Brief|RECOMMEND|Online|$)",
            r"Addresses[\s\.\:\-]+Current\s*Address[\s\.\:\-]+(.+?)(?:Permanent|BRIEF|Brief|Online|$)",
        ],
    )
    permanent_address = first_match(
        text,
        [r"Permanent\s*Address[\s\.\:\-]+(.+?)(?:BRIEF|Brief|RECOMMEND|Online|Contact|$)"],
    )

    victim_email = first_match(
        text,
        [
            r"E-?mail\s*(?:Address)?[\s\.\:\-]+([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})",
            r"\b([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})\b",
        ],
    )

    crime_category = first_match(
        text,
        [
            r"BRIEF\s*DESCRIPTION\s*&\s*RECOMMENDATIONS?\s*[\n\r]+([^\n\r]+)",
            r"BRIEF\s*DESCRIPTION\s*OF\s*(?:THE\s*)?CASE[\s\.\:\-]+(.+?)(?:accuse|Accuse|During|Online|City|$)",
            r"Crime\s*Categor(?:y|ies)[\s\.\:\-]+(.+?)(?:accuse|City|Amount|$)",
            r"(Online\s+Job\s+Frauds)",
        ],
    )

    crime_description = first_match(
        text,
        [
            r"(accuse[d]?\s+defrauded.+?)(?:City|Amount|0f Occurrence|RECOMMEND|\n\s*\*|$)",
            r"(accuse.+?investment.+?)(?:City|Amount|RECOMMEND|$)",
            r"BRIEF\s*DESCRIPTION[\s\S]{0,80}?((?:During|The|accuse).+?)(?:City\s*of|Amount\s*Involved|RECOMMEND|$)",
        ],
    )

    accused_list = extract_accused_list_from_text(text)
    primary_accused = accused_list[0] if accused_list else {}

    accused_name = primary_accused.get("name") or first_match(
        text,
        [
            r"Accuse[d]?\s*(?:Name|Person)?[\s\.\:\-]+(.+?)(?:CNIC|Mobile|Address|City|Amount|$)",
            r"against\s+(?:the\s+)?accuse[d]?\s+([A-Za-z][A-Za-z\s\./]{2,60})",
        ],
    )
    accused_cnic = primary_accused.get("cnic") or normalize_cnic(
        first_match(text, [r"Accuse[d]?[^\n]{0,40}CNIC\s*(?:No\.?)?[\s\.\:\-]+([\d\-]+)"])
    )
    accused_phone = primary_accused.get("mobile_no") or primary_accused.get("phone") or first_match(
        text,
        [
            r"Accuse[d]?[^\n]{0,60}(?:Mobile|Phone|Contact)\s*(?:No\.?|Number)?[\s\.\:\-]+([\d\-]+)",
            r"واٹس\s*ایپ\s*نمبر[\s\:\-]+(0?3\d{9})",
        ],
    )
    if accused_phone:
        accused_phone = re.sub(r"\D", "", accused_phone)
        if accused_phone.startswith("92") and len(accused_phone) > 10:
            accused_phone = accused_phone[2:]
        if len(accused_phone) == 10:
            accused_phone = "0" + accused_phone

    city = first_match(
        text,
        [
            r"City\s*of\s*Occurrence[\s\.\:\-]+(.+?)(?:Complaint|Amount|RECOMMEND|BRIEF|$)",
            r"0f\s*Occurrence[\s\.\:\-]+(.+?)(?:Complaint|Amount|working|RECOMMEND|BRIEF|$)",
        ],
    )

    amount_raw = first_match(
        text,
        [
            r"Amount\s*Involved[\s\.\:\-]+(?:Rs\.?\s*)?([\d,\.]+)",
            r"defrauded\s+Rs\.?\s*([\d,\.]+)",
            r"Rs\.?\s*([\d,]{4,}(?:\.\d+)?)",
        ],
    )
    amount_involved = None
    if amount_raw:
        try:
            amount_involved = float(re.sub(r"[^\d.]", "", amount_raw))
        except ValueError:
            amount_involved = None

    recommendation_text = first_match(
        text,
        [
            r"(?:^|\n)\s*RECOMMENDATIONS?\s*[\n\r]+\s*([A-Za-z\s]{5,80})",
            r"RECOMMENDATIONS?[\s\.\:\-]+(Permission[^\n\r]+)",
            r"Per(?:m|i)ssion\s+to\s+Register\s+Enq(?:u|ui)ry",
        ],
    )
    recommendation = map_recommendation(recommendation_text or text)

    recommendation_full = first_match(
        text,
        [
            r"(During\s+the\s+course\s+of\s+verification.+?converted\s+into\s+a\s+regular\s+Enquiry[\.]?)",
            r"Justification[\s\.\:\-]+(.+?)(?:Reporting Officer|Assistant Sub|Signature|$)",
        ],
    )
    reporting_officer = first_match(
        text,
        [
            r"([A-Z\s]{3,30})\s*[\n\r]+\s*(?:Assistant Sub Inspector|Sub Inspector|Inspector|ASI|SI|ASP|DSP)",
            r"(?:Assistant Sub Inspector|Sub Inspector|Inspector|ASI|SI|ASP|DSP)[\s\.\:\-]+([A-Za-z\s\.]{2,40})",
            r"Reporting\s*Officer[\s\.\:\-]+(.+?)(?:Designation|Rank|Signature|$)",
        ],
    )

    city = clean_value(city)
    address = clean_value(address)
    permanent_address = clean_value(permanent_address)
    crime_category = clean_value(crime_category)
    crime_description = clean_value(crime_description)
    victim_occupation = clean_value(victim_occupation)
    accused_name = clean_value(accused_name)
    reporting_officer = clean_value(reporting_officer)
    if recommendation_text and len(recommendation_text) > 400:
        recommendation_text = recommendation_text[:400] + "…"

    inquiry_no = first_match(
        text,
        [r"E[/\-]\s*(\d+)\s*[/\-]\s*(\d+)", r"Inquiry\s*No\.?\s*:?\s*([A-Z0-9/\-]+)"],
    )
    if inquiry_no and not inquiry_no.upper().startswith("E"):
        m = re.match(r"(\d+)\s*[/\-]\s*(\d+)", inquiry_no)
        if m:
            inquiry_no = f"E/{m.group(1)}/{m.group(2)}"
    if inquiry_no:
        inquiry_no = re.sub(r"\s+", "", inquiry_no)
        inquiry_no = re.sub(r"^E[\-/]?(\d+)[\-/](\d+)$", r"E/\1/\2", inquiry_no, flags=re.I)
    if not inquiry_no:
        inquiry_no = inquiry_ref_from_filename(filename)

    confidence = 0
    for val in [tracking_no, victim_name, victim_cnic, victim_phone, crime_category, city, amount_involved, recommendation_text]:
        if val:
            confidence += 1

    return {
        "document_type": "verification_report",
        "tracking_no": tracking_no,
        "verification_date": verification_date,
        "assignment_date": assignment_date,
        "victim_name": victim_name,
        "victim_father_name": victim_father_name,
        "victim_gender": victim_gender,
        "victim_cnic": victim_cnic,
        "victim_occupation": victim_occupation,
        "victim_email": victim_email,
        "victim_phone": victim_phone,
        "victim_address": address,
        "victim_permanent_address": permanent_address,
        "crime_category": crime_category,
        "crime_description": crime_description,
        "accused_name": accused_name,
        "accused_cnic": accused_cnic,
        "accused_phone": accused_phone,
        "city": city,
        "amount_involved": amount_involved,
        "recommendation": recommendation,
        "recommendation_short": recommendation_text,
        "recommendation_full": recommendation_full,
        "reporting_officer": reporting_officer,
        "inquiry_no": inquiry_no,
        "confidence_score": confidence,
        "raw_text_preview": text[:8000],
    }


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: nccia_pdf_extract.py <pdf_path>"}))
        return 1

    pdf_path = sys.argv[1]
    filename = Path(pdf_path).name
    max_pages = 3
    if len(sys.argv) >= 3:
        try:
            max_pages = max(1, int(sys.argv[2]))
        except ValueError:
            max_pages = 3

    try:
        text, page_count, used_ocr = extract_text_from_pdf(pdf_path, max_pages=max_pages)
        parsed = parse_verification_report(text, filename)
        parsed["page_count"] = page_count
        parsed["used_ocr"] = used_ocr
        parsed["source_filename"] = filename
        print(json.dumps(parsed, ensure_ascii=False))
        return 0
    except Exception as exc:
        print(json.dumps({"error": str(exc), "source_filename": filename}))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
