#!/usr/bin/env python3
"""Scan tracked and unignored new source files without reading local .env secrets."""

import argparse
import json
from pathlib import Path
import shutil
import subprocess
import tempfile

from secret_report import classify, run_scanner


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--scanner", required=True, help="Path to the verified gitleaks binary")
    parser.add_argument("--report", type=Path, default=Path("security-reports/gitleaks-worktree.json"))
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[2]
    report_path = args.report.resolve()
    report_path.parent.mkdir(parents=True, exist_ok=True)
    result = subprocess.run(["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"],
                            cwd=root, check=True, capture_output=True)
    paths = sorted(set(result.stdout.decode("utf-8").split("\0")) - {""})
    with tempfile.TemporaryDirectory(prefix="nccia-source-scan-") as temporary:
        snapshot = Path(temporary)
        for name in paths:
            source = root / name
            if source.is_symlink() or not source.resolve().is_relative_to(root.resolve()):
                raise ValueError(f"Refusing source symlink or external path: {name}")
            if not source.is_file():
                continue  # Deleted files have no current contents to scan.
            destination = snapshot / name
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(source, destination)
        print(f"Scanning {len(paths)} tracked and new paths (secret output redacted)", flush=True)
        returncode = run_scanner([str(Path(args.scanner).resolve()), "dir", str(snapshot), "--redact=100", "--no-banner",
                                  "--config", str(root / "scripts/security/gitleaks.toml"),
                                  "--report-format", "json", "--report-path", str(report_path)], root)
        if report_path.is_file():
            findings = json.loads(report_path.read_text(encoding="utf-8"))
            for finding in findings:
                file = Path(finding["File"])
                if file.is_absolute() and file.is_relative_to(snapshot):
                    finding["File"] = file.relative_to(snapshot).as_posix()
            if returncode not in (0, 1):
                return returncode
            report = classify(findings, lambda finding: (snapshot / finding["File"]).read_text(encoding="utf-8", errors="replace").splitlines())
            report_path.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
            print(f"Worktree: {len(report['blocking_findings'])} blocking secrets; {len(report['verified_ciphertext_findings'])} verified encrypted session payloads")
            return 1 if report["blocking_findings"] else 0
        return returncode or 2


if __name__ == "__main__":
    raise SystemExit(main())
