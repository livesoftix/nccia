"""Tests for the local OCR engine. Run: python -m unittest discover -s tests -v (from python/)."""

from __future__ import annotations

import dataclasses
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

import fitz

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from nccia_ocr.config import settings  # noqa: E402
from nccia_ocr.extract import extract  # noqa: E402
from nccia_ocr.fields import normalize_cnic, normalize_date, normalize_phone  # noqa: E402
from nccia_ocr.ocr import process_pages  # noqa: E402
from nccia_ocr.pdfio import PdfRejected, inspect, open_pdf  # noqa: E402
from tests import synthetic  # noqa: E402

CFG = settings()
HAS_TESSERACT = bool(CFG.tesseract_cmd)


def run_doc(path: Path, cfg=CFG):
    with open_pdf(str(path), cfg) as doc:
        count = doc.page_count
    pages = list(process_pages(str(path), 1, count, cfg))
    return pages, extract(pages)


class ValidatorTests(unittest.TestCase):
    def test_cnic(self):
        self.assertEqual(normalize_cnic("3520212345671", False)[0], "35202-1234567-1")
        self.assertEqual(normalize_cnic("35202-1234567-1", False)[0], "35202-1234567-1")
        self.assertIsNone(normalize_cnic("35202-123456-1", False)[0])        # 12 digits
        self.assertIsNone(normalize_cnic("95202-1234567-1", False)[0])       # invalid province
        value, issues = normalize_cnic("3520Z-1234S67-1", True)              # OCR glyph confusion
        self.assertEqual(value, "35202-1234567-1")
        self.assertTrue(any("corrected" in i for i in issues))
        self.assertIsNone(normalize_cnic("3520Z-1234S67-1", False)[0])       # never "fix" native text

    def test_phone(self):
        self.assertEqual(normalize_phone("+92 300 1234567", False)[0], "03001234567")
        self.assertEqual(normalize_phone("0300-1234567", False)[0], "03001234567")
        self.assertEqual(normalize_phone("3001234567", False)[0], "03001234567")
        self.assertIsNone(normalize_phone("12345", False)[0])

    def test_date(self):
        self.assertEqual(normalize_date("12-03-2026", False)[0], "2026-03-12")
        self.assertIsNone(normalize_date("31-02-2026", False)[0])
        self.assertIsNone(normalize_date("01-01-2099", False)[0])


class LayoutIntegrityTests(unittest.TestCase):
    def test_layout_patterns_have_no_control_characters(self):
        from nccia_ocr.extract import available_layouts, load_layout

        for name in available_layouts():
            layout = load_layout(name)
            patterns = layout.get("detect", []) + layout.get("circle_patterns", [])
            for spec in layout["fields"]:
                patterns += spec["patterns"] + ([spec["format"]] if spec.get("format") else [])
            for pattern in patterns:
                with self.subTest(layout=name, pattern=pattern[:40]):
                    self.assertFalse(any(c in pattern for c in "\x08\n\r\t"), "escape sequence was lost when editing JSON")


class ExtractionTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.dir = Path(self.tmp.name)

    def tearDown(self):
        self.tmp.cleanup()

    def assert_fields(self, result, truth, keys=synthetic.TRUTH_KEYS):
        for key in keys:
            with self.subTest(field=key):
                field = result.fields[key]
                self.assertTrue(field.valid, f"{key} invalid: {field.issues} raw={field.raw!r}")
                self.assertEqual(str(field.value).casefold(), str(truth[key]).casefold())
                self.assertIsNotNone(field.page)

    def test_searchable_pdf(self):
        body, truth = synthetic.sample(1)
        pages, result = run_doc(synthetic.text_pdf(self.dir / "t.pdf", body))
        self.assertEqual(pages[0]["method"], "text")
        self.assert_fields(result, truth)
        self.assertEqual(result.circle_hint, "Lahore")

    def test_missing_values_are_not_invented(self):
        pdf = synthetic.text_pdf(self.dir / "m.pdf", "VERIFICATION REPORT\nName: Only Name Given\nGender: Male\n")
        _, result = run_doc(pdf)
        cnic = result.fields["victim_cnic"]
        self.assertIsNone(cnic.value)
        self.assertFalse(cnic.valid)
        self.assertIn("required field not found", cnic.issues)
        self.assertIsNone(result.fields["victim_phone"].value)

    def test_reference_with_ocr_space_is_rejoined_and_bad_format_rejected(self):
        page = {"page": 1, "method": "ocr", "confidence": 0.95,
                "text": "Tracking No: CCW-LHR-0001 0/2026\nInquiry No: E/12/2026\nCNIC No: 35202-1234567-1"}
        result = extract([page])
        self.assertEqual(result.fields["tracking_no"].value, "CCW-LHR-00010/2026")
        page["text"] = "Tracking No: CCW-LHR-12AB\nCNIC No: 35202-1234567-1"
        bad = extract([page]).fields["tracking_no"]
        self.assertIsNone(bad.value)
        self.assertFalse(bad.valid)

    def test_conflicting_values_are_flagged(self):
        body, _ = synthetic.sample(2)
        body += "\nCNIC No: 61101-7654321-9\n"
        _, result = run_doc(synthetic.text_pdf(self.dir / "c.pdf", body))
        cnic = result.fields["victim_cnic"]
        self.assertFalse(cnic.valid)
        self.assertTrue(any("conflict" in i for i in cnic.issues))
        self.assertGreaterEqual(len(cnic.candidates), 2)

    def test_mixed_text_and_scanned_pages(self):
        body, truth = synthetic.sample(3)
        scanned = synthetic.scanned_pdf(self.dir / "s.pdf", body)
        doc = fitz.open()
        doc.new_page().insert_text((72, 72), "Covering letter with enough native text to skip OCR on this page.", fontsize=11)
        doc.insert_pdf(fitz.open(scanned))
        doc.save(self.dir / "mixed.pdf")
        if not HAS_TESSERACT:
            self.skipTest("tesseract not installed")
        pages, result = run_doc(self.dir / "mixed.pdf")
        self.assertEqual([p["method"] for p in pages], ["text", "ocr"])
        self.assertEqual(result.fields["victim_cnic"].page, 2)
        self.assert_fields(result, truth, ("victim_cnic", "victim_phone"))

    @unittest.skipUnless(HAS_TESSERACT, "tesseract not installed")
    def test_scanned_pdf(self):
        body, truth = synthetic.sample(4)
        pages, result = run_doc(synthetic.scanned_pdf(self.dir / "s.pdf", body))
        self.assertEqual(pages[0]["method"], "ocr")
        self.assertGreater(pages[0]["confidence"], 0.6)
        self.assert_fields(result, truth)

    @unittest.skipUnless(HAS_TESSERACT, "tesseract not installed")
    def test_rotated_scan(self):
        body, truth = synthetic.sample(5)
        pages, result = run_doc(synthetic.scanned_pdf(self.dir / "r.pdf", body, rotate=90))
        self.assertIn(pages[0]["rotation"], (90, 270))
        self.assert_fields(result, truth, ("victim_cnic", "victim_phone"))

    @unittest.skipUnless(HAS_TESSERACT, "tesseract not installed")
    def test_skewed_noisy_scan(self):
        body, truth = synthetic.sample(6)
        pages, result = run_doc(synthetic.scanned_pdf(self.dir / "k.pdf", body, skew=3.0, noise=4))
        self.assertNotEqual(pages[0]["skew"], 0.0)
        self.assert_fields(result, truth, ("victim_cnic", "victim_phone"))

    def test_scanned_pages_beyond_limit_are_skipped_not_ocrd(self):
        body, _ = synthetic.sample(23)
        scanned = synthetic.scanned_pdf(self.dir / "s.pdf", body)
        doc = fitz.open()
        doc.new_page().insert_text((72, 72), "Covering letter with enough native text to skip OCR on this page.", fontsize=11)
        doc.insert_pdf(fitz.open(scanned))
        doc.save(self.dir / "limit.pdf")
        pages = list(process_pages(str(self.dir / "limit.pdf"), 1, 2, dataclasses.replace(CFG, max_ocr_pages=1)))
        self.assertEqual([p["method"] for p in pages], ["text", "skipped"])
        self.assertEqual(pages[1]["text"], "")

    def test_urdu_rtl_text_layer(self):
        pages, result = run_doc(synthetic.urdu_text_pdf(self.dir / "u.pdf"))
        self.assertIn("شناختی", pages[0]["text"])
        self.assertEqual(result.fields["victim_cnic"].value, "35202-1234567-1")

    def test_large_pdf_is_processed_in_ranges(self):
        body, _ = synthetic.sample(7)
        pdf = synthetic.text_pdf(self.dir / "big.pdf", body, extra_pages=799)
        self.assertEqual(inspect(str(pdf), CFG)["page_count"], 800)
        chunk = list(process_pages(str(pdf), 401, 425, CFG))
        self.assertEqual([p["page"] for p in chunk], list(range(401, 426)))
        self.assertTrue(all(p["error"] is None for p in chunk))


class RejectionTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.dir = Path(self.tmp.name)

    def tearDown(self):
        self.tmp.cleanup()

    def test_not_a_pdf(self):
        path = self.dir / "fake.pdf"
        path.write_bytes(b"MZ\x90\x00 this is not a pdf")
        with self.assertRaises(PdfRejected):
            inspect(str(path), CFG)

    def test_truncated_pdf(self):
        body, _ = synthetic.sample(8)
        good = synthetic.text_pdf(self.dir / "g.pdf", body).read_bytes()
        bad = self.dir / "bad.pdf"
        bad.write_bytes(good[:200])
        with self.assertRaises(PdfRejected):
            with open_pdf(str(bad), CFG) as doc:
                doc[0].get_text()

    def test_encrypted_pdf(self):
        doc = fitz.open()
        doc.new_page().insert_text((72, 72), "secret")
        path = self.dir / "enc.pdf"
        doc.save(path, encryption=fitz.PDF_ENCRYPT_AES_256, user_pw="x", owner_pw="y")
        with self.assertRaises(PdfRejected):
            inspect(str(path), CFG)

    def test_page_limit(self):
        body, _ = synthetic.sample(9)
        pdf = synthetic.text_pdf(self.dir / "p.pdf", body, extra_pages=5)
        with self.assertRaises(PdfRejected):
            inspect(str(pdf), dataclasses.replace(CFG, max_pages=3))

    def test_file_size_limit(self):
        body, _ = synthetic.sample(10)
        pdf = synthetic.text_pdf(self.dir / "s.pdf", body)
        with self.assertRaises(PdfRejected):
            inspect(str(pdf), dataclasses.replace(CFG, max_file_mb=0))


class CliTests(unittest.TestCase):
    def test_pages_then_extract_round_trip(self):
        with tempfile.TemporaryDirectory() as tmp:
            body, truth = synthetic.sample(11)
            pdf = synthetic.text_pdf(Path(tmp) / "c.pdf", body)
            root = Path(__file__).resolve().parents[1]
            out = subprocess.run([sys.executable, "-m", "nccia_ocr", "pages", str(pdf), "--start", "1", "--end", "5"],
                                 cwd=root, capture_output=True, text=True, encoding="utf-8", check=True)
            pages_file = Path(tmp) / "pages.jsonl"
            pages_file.write_text(out.stdout, encoding="utf-8")
            res = subprocess.run([sys.executable, "-m", "nccia_ocr", "extract", "--pages-file", str(pages_file)],
                                 cwd=root, capture_output=True, text=True, encoding="utf-8", check=True)
            data = json.loads(res.stdout)
            self.assertEqual(data["fields"]["victim_cnic"]["value"], truth["victim_cnic"])

    def test_rejected_pdf_exit_code(self):
        with tempfile.TemporaryDirectory() as tmp:
            bad = Path(tmp) / "x.pdf"
            bad.write_text("nope")
            root = Path(__file__).resolve().parents[1]
            res = subprocess.run([sys.executable, "-m", "nccia_ocr", "inspect", str(bad)], cwd=root,
                                 capture_output=True, text=True)
            self.assertEqual(res.returncode, 2)
            self.assertTrue(json.loads(res.stdout)["rejected"])


if __name__ == "__main__":
    unittest.main()
