"""Explain one verified Gitleaks false positive without exempting SQL files or JWTs."""

import base64
import binascii
import json
import re
import subprocess
import sys


def run_scanner(command, root):
    result = subprocess.run(command, cwd=root, capture_output=True, text=True)
    # Gitleaks can return zero after a Git text-conversion failure. An incomplete
    # scan must fail even if it produced an empty report.
    if result.stdout:
        print(result.stdout, end="")
    if result.stderr:
        print(result.stderr, end="", file=sys.stderr)
    if re.search(r"\bERR\b|\bfatal:", result.stderr):
        return 2
    return result.returncode


def encrypted_laravel_session(candidate):
    try:
        # A database session stores base64(encrypted Laravel JSON envelope),
        # which resembles Gitleaks' base64 JWT header regex without being a JWT.
        encoded = base64.b64decode(candidate, validate=True)
        envelope = json.loads(base64.b64decode(encoded, validate=True))
        if not isinstance(envelope, dict) or set(envelope) != {"iv", "value", "mac", "tag"}:
            return False
        if not all(isinstance(value, str) for value in envelope.values()):
            return False
        iv = base64.b64decode(envelope["iv"], validate=True)
        ciphertext = base64.b64decode(envelope["value"], validate=True)
        return (len(iv) == 16 and len(ciphertext) >= 16 and len(ciphertext) % 16 == 0
                and re.fullmatch(r"[0-9a-f]{64}", envelope["mac"]) is not None and envelope["tag"] == "")
    except (ValueError, TypeError, binascii.Error, UnicodeDecodeError):
        return False


def is_verified_session_finding(finding, read_lines):
    if finding["RuleID"] != "jwt-base64" or finding["File"].replace("\\", "/") != "realerp_nccia.sql":
        return False
    if finding["StartLine"] != finding["EndLine"]:
        return False
    try:
        line = read_lines(finding)[finding["StartLine"] - 1].encode("utf-8")
    except (IndexError, OSError, ValueError):
        return False
    for match in re.finditer(rb"ZXlK[A-Za-z0-9/+_=\-]{40,}", line):
        # Git diff findings include the leading '+' in their column positions;
        # directory findings don't. Match the reported span, never another token.
        for offset in (1, 2):
            if (finding["StartColumn"] == match.start() + offset
                    and finding["EndColumn"] == match.end() + offset - 1
                    and encrypted_laravel_session(match.group())):
                return True
    return False


def classify(findings, read_lines):
    blocked, ciphertext = [], []
    for finding in findings:
        if is_verified_session_finding(finding, read_lines):
            ciphertext.append(finding)
        else:
            blocked.append(finding)
    return {"blocking_findings": blocked, "verified_ciphertext_findings": ciphertext,
            "explanation": "Only jwt-base64 spans in realerp_nccia.sql that decode into a strict Laravel AES-CBC session envelope are classified as ciphertext. Historical database exposure still requires governance review; actual JWTs and other secrets remain blocking."}
