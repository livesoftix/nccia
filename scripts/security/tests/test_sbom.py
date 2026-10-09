import base64
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("sbom", Path(__file__).resolve().parents[1] / "generate-sbom.py")
sbom = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sbom)


class SbomTest(unittest.TestCase):
    def test_integrity_decodes_sha512_without_losing_bytes(self):
        digest = bytes(range(64))
        integrity = "sha512-" + base64.b64encode(digest).decode("ascii")
        self.assertEqual(sbom.integrity_hashes(integrity), [{"alg": "SHA-512", "content": digest.hex()}])
        with self.assertRaises(ValueError):
            sbom.integrity_hashes("sha512-AA==")

    def test_scoped_package_urls_encode_at_sign(self):
        self.assertEqual(sbom.package_url("npm", "@capacitor/core", "8.5.3"), "pkg:npm/%40capacitor/core@8.5.3")

    def test_nested_npm_dependencies_resolve_before_hoisted_packages(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            lock = {"name": "test", "lockfileVersion": 3, "packages": {
                "": {"dependencies": {"parent": "1.0.0"}},
                "node_modules/parent": {"version": "1.0.0", "dependencies": {"child": "1.0.0"}},
                "node_modules/parent/node_modules/child": {"version": "1.0.0"},
                "node_modules/child": {"version": "2.0.0"},
            }}
            (root / "package-lock.json").write_text(json.dumps(lock), encoding="utf-8")
            _, components, dependencies = sbom.npm_inventory(root, "")
            edges = {edge["ref"]: edge["dependsOn"] for edge in dependencies}
            self.assertEqual(edges["npm:root:node_modules/parent@1.0.0"],
                             ["npm:root:node_modules/parent/node_modules/child@1.0.0"])
            self.assertEqual(len(components), 4)

    def test_actual_inventory_is_reproducible_complete_and_references_exist(self):
        root = Path(__file__).resolve().parents[3]
        first = sbom.generate(root)
        second = sbom.generate(root)
        self.assertEqual(json.dumps(first, sort_keys=True), json.dumps(second, sort_keys=True))
        refs = {component["bom-ref"] for component in first["components"]} | {"application:nccia"}
        self.assertEqual(len(refs), len(first["components"]) + 1)
        for edge in first["dependencies"]:
            self.assertIn(edge["ref"], refs)
            self.assertTrue(set(edge["dependsOn"]).issubset(refs))
        php = sbom.read_json(root / "composer.lock")
        expected = len(php["packages"]) + len(php["packages-dev"]) + 4
        for directory in (root, root / "react-app", root / "mobile-app"):
            expected += len(sbom.read_json(directory / "package-lock.json")["packages"]) - 1
        self.assertEqual(len(first["components"]), expected)


if __name__ == "__main__":
    unittest.main()
