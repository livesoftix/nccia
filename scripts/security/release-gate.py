#!/usr/bin/env python3
"""Run blocking source/release checks; requires separate recent deployment evidence."""

import argparse
from datetime import datetime, timezone
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("staging_smoke", Path(__file__).with_name("staging-smoke.py"))
smoke = importlib.util.module_from_spec(spec)
spec.loader.exec_module(smoke)


def validate_deployment(preflight, staging, evidence, fingerprint):
    failures = []
    if preflight.get("schema_version") != 1 or not preflight.get("database_inspected") or preflight.get("status") not in ("passed", "incomplete"):
        failures.append("production_preflight")
    checks = {item.get("id"): item.get("status") for item in preflight.get("checks", [])}
    required = ["production_environment", "debug_disabled", "application_key", "dedicated_audit_key", "https_origin",
                "secure_sessions", "bounded_sessions", "mandatory_mfa", "argon2id_passwords", "upload_scan_required",
                "audit_writes_required", "curl_available",
                "smtp_transport", "sms_outbound", "adp_outbound", "database_security_schema", "audit_integrity",
                "database_nonadmin_account", "database_runtime_grants", "database_transport_tls"]
    if any(checks.get(key) != "pass" for key in required) or any(value == "fail" for value in checks.values()):
        failures.append("production_configuration_database")
    if staging.get("status") != "passed" or not staging.get("production_tls") or staging.get("source_fingerprint") != fingerprint:
        failures.append("staging_tls_source_match")
    expected_probes = {"health", "environment_blocked", "git_blocked", "database_dump_blocked", "storage_blocked", "uploads_blocked", "users_require_auth"}
    probes = {item.get("id"): item.get("status") for item in staging.get("checks", [])}
    if any(probes.get(key) != "pass" for key in expected_probes):
        failures.append("staging_required_probes")
    for report in (preflight, staging, evidence):
        try:
            age = (datetime.now(timezone.utc) - datetime.fromisoformat(report["checked_at"])).total_seconds()
            if age < 0 or age > 86400:
                raise ValueError("Stale evidence")
        except (KeyError, TypeError, ValueError):
            failures.append("recent_deployment_evidence")
    if evidence.get("source_fingerprint") != fingerprint or evidence.get("schema_version") != 1:
        failures.append("operator_evidence_source_match")
    fields = ["runtime_permissions_verified", "malware_definitions_current", "backup_restore_verified",
              "independent_audit_checkpoint_verified", "historical_credentials_remediated"]
    if any(evidence.get(key) is not True for key in fields):
        failures.append("operator_deployment_evidence")
    return sorted(set(failures))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--execute", action="store_true", help="Run tests, audits, builds, SAST and current/full-history scans")
    parser.add_argument("--php", default="php")
    parser.add_argument("--php-argument", action="append", default=[], help="Repeat for process-local extension flags; use --php-argument=-d")
    parser.add_argument("--composer", default="composer", help="Composer executable or .phar")
    parser.add_argument("--semgrep")
    parser.add_argument("--gitleaks")
    parser.add_argument("--preflight-report", type=Path)
    parser.add_argument("--staging-report", type=Path)
    parser.add_argument("--deployment-evidence", type=Path)
    parser.add_argument("--report", type=Path, default=ROOT / "security-reports/release-gate.json")
    args = parser.parse_args()
    if not args.execute:
        print(json.dumps({"status": "plan", "required": ["--execute", "verified --semgrep and --gitleaks binaries",
                          "redacted --preflight-report from production security:preflight --database --json",
                          "recent --staging-report from explicit approved staging target", "protected --deployment-evidence"],
                          "behavior": "No deployment, migration or live database access; gate only creates reports/build assets."}))
        return 0
    if not args.semgrep or not args.gitleaks:
        parser.error("Explicit verified scanner binaries are required")
    php = [args.php] + args.php_argument
    composer = php + [args.composer] if args.composer.endswith(".phar") else [args.composer]
    npm = shutil.which("npm")
    if not npm:
        parser.error("npm is required")
    steps = [("composer_validate", composer + ["validate", "--strict"]),
             ("composer_audit", composer + ["audit", "--locked", "--no-interaction", "--abandoned=fail"]),
             ("isolated_php_tests", php + ["scripts/security/run-tests.php"]),
             ("root_npm_audit", [npm, "audit", "--audit-level=moderate"]),
             ("react_npm_audit", [npm, "--prefix", "react-app", "audit", "--audit-level=moderate"]),
             ("mobile_npm_audit", [npm, "--prefix", "mobile-app", "audit", "--audit-level=moderate"]),
             ("react_lint", [npm, "--prefix", "react-app", "run", "lint"]),
             ("root_build", [npm, "run", "build"]), ("react_build", [npm, "--prefix", "react-app", "run", "build"]),
             ("source_sast", [args.semgrep, "scan", "--config", "scripts/security/semgrep.yml", "--error", "--strict", "--metrics=off", "--disable-version-check", "app", "routes", "config", "react-app/src"]),
             ("worktree_secrets", [sys.executable, "scripts/security/scan-worktree.py", "--scanner", args.gitleaks]),
             ("history_secrets", [sys.executable, "scripts/security/scan-history.py", "--scanner", args.gitleaks]),
             ("reproducible_sbom", [sys.executable, "scripts/security/generate-sbom.py"])]
    results = []
    for identifier, command in steps:
        print("Running " + identifier, flush=True)
        try:
            # Output is intentionally not forwarded: dependency lifecycle/build tools may log local details.
            result = subprocess.run(command, cwd=ROOT, env=os.environ.copy(), capture_output=True, timeout=600)
            results.append({"id": identifier, "status": "pass" if result.returncode == 0 else "fail", "exit_code": result.returncode})
        except (OSError, subprocess.TimeoutExpired):
            results.append({"id": identifier, "status": "fail", "detail": "Tool unavailable or exceeded bounded timeout."})
    failures = []
    try:
        if not all((args.preflight_report, args.staging_report, args.deployment_evidence)):
            raise ValueError("Deployment reports required")
        reports = [json.loads(path.read_text(encoding="utf-8")) for path in (args.preflight_report, args.staging_report, args.deployment_evidence)]
        failures = validate_deployment(*reports, smoke.source_fingerprint())
    except (OSError, ValueError, TypeError):
        failures = ["deployment_evidence_missing_or_invalid"]
    failed = any(item["status"] == "fail" for item in results)
    status = "failed" if failed else ("incomplete" if failures else "passed")
    report = {"schema_version": 1, "status": status, "source_checks": results, "deployment_evidence_failures": failures}
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report))
    return 1 if failed else (2 if failures else 0)


if __name__ == "__main__":
    raise SystemExit(main())
