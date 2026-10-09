import base64
import hashlib
import hmac
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from secret_report import classify, encrypted_laravel_session


def encode(value):
    return base64.b64encode(value).decode("ascii")


def ciphertext():
    envelope = {"iv": encode(bytes(range(16))), "value": encode(bytes(range(32))),
                "mac": "0" * 64, "tag": ""}
    return encode(encode(json.dumps(envelope).encode()).encode())


def jwt():
    def url_encode(value):
        return base64.urlsafe_b64encode(value).rstrip(b"=").decode("ascii")
    header = url_encode(b'{"alg":"HS256","typ":"JWT"}')
    payload = url_encode(b'{"sub":"scanner-fixture","exp":2000000000}')
    data = f"{header}.{payload}"
    signature = url_encode(hmac.new(b"public-security-scanner-fixture", data.encode(), hashlib.sha256).digest())
    return f"{data}.{signature}"


class SecretReportTest(unittest.TestCase):
    @unittest.skipUnless(os.environ.get("GITLEAKS_BIN"), "Set GITLEAKS_BIN for the scanner integration check")
    def test_literal_mail_password_defaults_are_blocked_and_redacted(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            # Construct public synthetic examples at runtime; no credentials are stored here.
            value = "public" + "-scanner-fixture-only"
            (root / "mail.php").write_text("<?php $a = env('MAIL_PASSWORD', '" + value + "');\n"
                                           + "$b = env('MAIL_PASSWORD', '');\n", encoding="utf-8")
            report = root / "report.json"
            config = Path(__file__).resolve().parents[1] / "gitleaks.toml"
            result = subprocess.run([os.environ["GITLEAKS_BIN"], "dir", str(root), "--config", str(config),
                                     "--redact=100", "--no-banner", "--report-format", "json", "--report-path", str(report)],
                                    capture_output=True, text=True)
            self.assertEqual(result.returncode, 1)
            findings = json.loads(report.read_text(encoding="utf-8"))
            matches = [item for item in findings if item['RuleID'] == 'php-mail-password-fallback']
            self.assertEqual(len(matches), 1)
            self.assertEqual(matches[0]['StartLine'], 1)
            self.assertNotIn(value, json.dumps(findings) + result.stdout + result.stderr)

    def test_only_valid_ciphertext_envelopes_are_recognized(self):
        self.assertTrue(encrypted_laravel_session(ciphertext()))
        self.assertFalse(encrypted_laravel_session(encode(jwt().encode())))
        self.assertFalse(encrypted_laravel_session(encode(encode(b'{"iv":"plaintext"}').encode())))

    def test_exception_requires_exact_rule_path_and_reported_span(self):
        value = ciphertext()
        line = f"'{value}'"
        finding = {"RuleID": "jwt-base64", "File": "realerp_nccia.sql", "StartLine": 1,
                   "EndLine": 1, "StartColumn": 2, "EndColumn": len(value) + 1}
        self.assertEqual(len(classify([finding], lambda _: [line])["verified_ciphertext_findings"]), 1)
        for key, replacement in [("RuleID", "generic-api-key"), ("File", "other.sql"), ("StartColumn", 10)]:
            changed = {**finding, key: replacement}
            self.assertEqual(len(classify([changed], lambda _: [line])["blocking_findings"]), 1)

    @unittest.skipUnless(os.environ.get("GITLEAKS_BIN"), "Set GITLEAKS_BIN to run the scanner integration check")
    def test_real_scanner_flags_and_redacts_synthetic_jwt_in_same_sql_path(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / "source"
            source.mkdir()
            token = encode(jwt().encode())
            line = f"INSERT INTO fixtures VALUES ('{token}');"
            (source / "realerp_nccia.sql").write_text(line + "\n", encoding="utf-8")
            report = root / "report.json"
            result = subprocess.run([os.environ["GITLEAKS_BIN"], "dir", str(source), "--redact=100", "--no-banner",
                                     "--report-format", "json", "--report-path", str(report)], capture_output=True, text=True)
            self.assertEqual(result.returncode, 1)
            findings = json.loads(report.read_text(encoding="utf-8"))
            self.assertTrue(findings)
            for finding in findings:
                finding["File"] = Path(finding["File"]).name
                self.assertNotIn(token, finding["Secret"])
            self.assertTrue(classify(findings, lambda _: [line])["blocking_findings"])


if __name__ == "__main__":
    unittest.main()
