#!/usr/bin/env python3
"""Read-only HTTP deployment probes. No network is used until --base-url is explicit."""

import argparse
from datetime import datetime, timezone
import hashlib
import ipaddress
import json
from pathlib import Path
import re
import ssl
import subprocess
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]


def source_fingerprint():
    result = subprocess.run(["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"],
                            cwd=ROOT, capture_output=True, check=True)
    digest = hashlib.sha256()
    for name in sorted(set(result.stdout.decode("utf-8").split("\0")) - {""}):
        if name == ".phpunit.result.cache":
            continue
        path = ROOT / name
        if path.is_symlink() or not path.resolve().is_relative_to(ROOT.resolve()):
            raise ValueError("Source contains a link outside the workspace")
        if path.is_file():
            digest.update(name.encode("utf-8") + b"\0")
            with path.open("rb") as source:
                for block in iter(lambda: source.read(1024 * 1024), b""):
                    digest.update(block)
            digest.update(b"\0")
    return digest.hexdigest()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, new_url):
        return None


def validate_origin(url, allow_loopback_http=False):
    parts = urllib.parse.urlsplit(url)
    if not parts.hostname or parts.username or parts.password or parts.query or parts.fragment or parts.path not in ("", "/"):
        raise ValueError("Supply a credential-free origin without a path, query or fragment")
    loopback = False
    try:
        loopback = ipaddress.ip_address(parts.hostname).is_loopback
    except ValueError:
        pass
    if parts.scheme != "https" and not (parts.scheme == "http" and allow_loopback_http and loopback):
        raise ValueError("HTTPS is required; HTTP requires an explicit loopback IP and opt-in")
    _ = parts.port  # Validate the port before opening a socket.
    return urllib.parse.urlunsplit((parts.scheme, parts.netloc, "", "", ""))


def probe(origin, path, timeout):
    # Verified certificates, no environment proxy, no redirect, no credentials or bodies.
    client = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect(),
                                        urllib.request.HTTPSHandler(context=ssl.create_default_context()))
    request = urllib.request.Request(origin + path, headers={"Accept": "application/json", "User-Agent": "NCCIA-readonly-security-probe/1"})
    try:
        response = client.open(request, timeout=timeout)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, {key.lower(): value for key, value in response.headers.items()}


def run_smoke(origin, timeout=10):
    checks = []
    cases = [("health", "/up", {200}), ("environment_blocked", "/.env", {403, 404}),
             ("git_blocked", "/.git/config", {403, 404}), ("database_dump_blocked", "/realerp_nccia.sql", {403, 404}),
             ("storage_blocked", "/storage/security-probe.txt", {403, 404}),
             ("uploads_blocked", "/uploads/security-probe.txt", {403, 404}),
             ("users_require_auth", "/api/users", {401, 403})]
    for identifier, path, accepted in cases:
        try:
            status, headers = probe(origin, path, timeout)
            good = status in accepted
            if identifier == "health":
                good = good and headers.get("x-frame-options", "").upper() == "DENY" and headers.get("x-content-type-options", "").lower() == "nosniff"
                good = good and bool(headers.get("content-security-policy"))
                if origin.startswith("https://"):
                    age = re.search(r"(?:^|;)\s*max-age=(\d+)", headers.get("strict-transport-security", ""), re.I)
                    good = good and age is not None and int(age[1]) >= 31536000
            checks.append({"id": identifier, "status": "pass" if good else "fail", "http_status": status})
        except Exception:
            # Never include exception URLs, server responses, private certificate names or response bodies.
            checks.append({"id": identifier, "status": "fail", "detail": "Connection, TLS or HTTP probe failed."})
    return {"schema_version": 1, "status": "passed" if all(item["status"] == "pass" for item in checks) else "failed",
            "target_origin": origin, "production_tls": origin.startswith("https://"),
            "checked_at": datetime.now(timezone.utc).isoformat(), "source_fingerprint": source_fingerprint(), "checks": checks}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url")
    parser.add_argument("--allow-loopback-http", action="store_true")
    parser.add_argument("--timeout", type=float, default=10)
    parser.add_argument("--report", type=Path, default=ROOT / "security-reports/staging-smoke.json")
    args = parser.parse_args()
    if not args.base_url:
        print(json.dumps({"status": "plan", "required": "Explicit --base-url=https://approved-staging-origin",
                          "actions": "GET-only probes; verified TLS; no redirects; no login or sensitive response bodies"}))
        return 0
    try:
        if not 0 < args.timeout <= 30:
            raise ValueError("Timeout must be bounded")
        origin = validate_origin(args.base_url, args.allow_loopback_http)
        report = run_smoke(origin, args.timeout)
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
        print(json.dumps(report))
        return 0 if report["status"] == "passed" else 1
    except Exception:
        print(json.dumps({"status": "failed", "detail": "Invalid target or deployment probe could not complete."}))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
