import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import threading
import unittest
from http.server import BaseHTTPRequestHandler, HTTPServer

SCRIPTS = Path(__file__).resolve().parents[1]


def load(name):
    spec = importlib.util.spec_from_file_location(name, SCRIPTS / (name + ".py"))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class Handler(BaseHTTPRequestHandler):
    insecure = False

    def do_GET(self):
        status = 200 if self.path == "/up" else (401 if self.path == "/api/users" else 404)
        self.send_response(status)
        if not self.insecure:
            self.send_header("X-Frame-Options", "DENY")
            self.send_header("X-Content-Type-Options", "nosniff")
            self.send_header("Content-Security-Policy", "default-src 'none'")
        self.end_headers()
        self.wfile.write(b"fictional-private-response-never-output")

    def log_message(self, *args):
        pass


class DeploymentToolsTest(unittest.TestCase):
    def test_plan_modes_do_not_request_any_network_target(self):
        for name in ["staging-smoke", "release-gate"]:
            result = subprocess.run([sys.executable, str(SCRIPTS / (name + ".py"))], capture_output=True, text=True)
            self.assertEqual(result.returncode, 0)
            self.assertEqual(json.loads(result.stdout)["status"], "plan")

    def test_origins_require_verified_https_or_explicit_loopback_ip(self):
        smoke = load("staging-smoke")
        self.assertEqual(smoke.validate_origin("https://stage.example.invalid/"), "https://stage.example.invalid")
        self.assertEqual(smoke.validate_origin("http://127.0.0.1:1234", True), "http://127.0.0.1:1234")
        for url in ["http://stage.example.invalid", "http://localhost", "https://user:pass@stage.example.invalid", "https://stage.example.invalid/?credential=x"]:
            with self.assertRaises(ValueError):
                smoke.validate_origin(url, True)

    def test_local_fixture_probes_check_headers_and_do_not_print_response_bodies(self):
        smoke = load("staging-smoke")
        server = HTTPServer(("127.0.0.1", 0), Handler)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        try:
            origin = "http://127.0.0.1:" + str(server.server_port)
            Handler.insecure = False
            report = smoke.run_smoke(origin, timeout=2)
            self.assertEqual(report["status"], "passed")
            self.assertFalse(report["production_tls"])
            self.assertNotIn("fictional-private-response", json.dumps(report))
            Handler.insecure = True
            self.assertEqual(smoke.run_smoke(origin, timeout=2)["status"], "failed")
        finally:
            Handler.insecure = False
            server.shutdown()
            server.server_close()
            thread.join(timeout=2)

    def test_release_evidence_cannot_pass_with_empty_old_or_mismatched_reports(self):
        gate = load("release-gate")
        self.assertTrue(gate.validate_deployment({}, {}, {}, "a" * 64))
        preflight = {"schema_version": 1, "status": "passed", "database_inspected": True, "checks": []}
        stage = {"status": "passed", "production_tls": True, "source_fingerprint": "b" * 64,
                 "checked_at": "2000-01-01T00:00:00+00:00", "checks": []}
        failures = gate.validate_deployment(preflight, stage, {}, "a" * 64)
        self.assertIn("production_configuration_database", failures)
        self.assertIn("staging_tls_source_match", failures)
        self.assertIn("recent_deployment_evidence", failures)


if __name__ == "__main__":
    unittest.main()
