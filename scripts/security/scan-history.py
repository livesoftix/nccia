#!/usr/bin/env python3
"""Scan all Git history, redact secrets, and classify verified session ciphertext."""

import argparse
import json
from pathlib import Path
import re
import subprocess
import tempfile

from secret_report import classify, run_scanner


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--scanner", required=True)
    parser.add_argument("--report", type=Path, default=Path("security-reports/gitleaks-history.json"))
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[2]
    with tempfile.TemporaryDirectory(prefix="nccia-history-scan-") as temporary:
        raw_report = Path(temporary) / "findings.json"
        returncode = run_scanner([str(Path(args.scanner).resolve()), "git", "--redact=100", "--no-banner",
                                  "--config", str(root / "scripts/security/gitleaks.toml"),
                                  "--log-opts=--all --no-textconv", "--report-format", "json", "--report-path", str(raw_report), "."], root)
        if returncode not in (0, 1) or not raw_report.is_file():
            return returncode or 2
        findings = json.loads(raw_report.read_text(encoding="utf-8"))
        cache = {}

        def read_lines(finding):
            commit = finding["Commit"]
            if not re.fullmatch(r"[0-9a-f]{40,64}", commit):
                raise ValueError("Invalid scan commit")
            key = (commit, finding["File"])
            if key not in cache:
                source = subprocess.run(["git", "show", f"{commit}:{finding['File']}"], cwd=root, capture_output=True, check=True)
                cache[key] = source.stdout.decode("utf-8", errors="replace").splitlines()
            return cache[key]

        report = classify(findings, read_lines)
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
        print(f"History: {len(report['blocking_findings'])} blocking secrets; {len(report['verified_ciphertext_findings'])} verified encrypted session payloads")
        return 1 if report["blocking_findings"] else 0


if __name__ == "__main__":
    raise SystemExit(main())
