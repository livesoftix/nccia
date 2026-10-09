#!/usr/bin/env python3
"""Create a deterministic CycloneDX 1.5 inventory from committed dependency locks.

Includes runtime, development and optional packages for PHP, web and Android.
This is a lockfile inventory, not an assertion about the deployed environment.
No dependencies, network calls, environment files or application boot required.
"""

import argparse
import base64
import hashlib
import json
from pathlib import Path
from urllib.parse import quote


def read_json(path):
    return json.loads(path.read_text(encoding="utf-8"))


def package_url(ecosystem, name, version):
    return f"pkg:{ecosystem}/{quote(name, safe='/')}@{quote(version, safe='')}"


def integrity_hashes(integrity):
    hashes = []
    algorithms = {"sha256": "SHA-256", "sha384": "SHA-384", "sha512": "SHA-512", "sha1": "SHA-1"}
    lengths = {"sha256": 32, "sha384": 48, "sha512": 64, "sha1": 20}
    for value in integrity.split():
        algorithm, _, encoded = value.partition("-")
        if algorithm not in algorithms:
            continue
        digest = base64.b64decode(encoded, validate=True)
        if len(digest) != lengths[algorithm]:
            raise ValueError(f"Invalid {algorithm} integrity hash")
        hashes.append({"alg": algorithms[algorithm], "content": digest.hex()})
    return sorted(hashes, key=lambda item: item["alg"])


def library(ecosystem, name, version, reference, licenses=(), development=False):
    namespace, _, simple_name = name.rpartition("/")
    component = {
        "type": "library", "bom-ref": reference, "name": simple_name or name,
        "version": version, "purl": package_url(ecosystem, name, version),
        "scope": "optional" if development else "required",
    }
    if namespace:
        component["group"] = namespace
    if licenses:
        component["licenses"] = [{"license": {"name": value}} for value in sorted(set(licenses))]
    return component


def composer_inventory(root):
    lock = read_json(root / "composer.lock")
    manifest = read_json(root / "composer.json")
    app_ref = "application:composer"
    components = [{"type": "application", "bom-ref": app_ref, "name": manifest["name"]}]
    packages = [(package, False) for package in lock["packages"]]
    packages += [(package, True) for package in lock.get("packages-dev", [])]
    references = {package["name"]: package_url("composer", package["name"], package["version"].removeprefix("v"))
                  for package, _ in packages}
    # Frameworks can replace virtual packages such as illuminate/support.
    for package, _ in packages:
        for replaced in package.get("replace", {}):
            references.setdefault(replaced, references[package["name"]])
    dependencies = []
    for package, development in packages:
        reference = references[package["name"]]
        component = library("composer", package["name"], package["version"].removeprefix("v"),
                            reference, package.get("license", []), development)
        component["properties"] = [{"name": "nccia:lockfile", "value": "composer.lock"}]
        shasum = package.get("dist", {}).get("shasum")
        if shasum and len(shasum) == 40:
            component["hashes"] = [{"alg": "SHA-1", "content": shasum}]
        components.append(component)
        dependencies.append({"ref": reference, "dependsOn": sorted({references[name] for name in package.get("require", {})
                                                                     if name in references and references[name] != reference})})
    names = set(manifest.get("require", {})) | set(manifest.get("require-dev", {}))
    dependencies.append({"ref": app_ref, "dependsOn": sorted({references[name] for name in names if name in references})})
    return app_ref, components, dependencies


def npm_inventory(root, directory):
    prefix = f"{directory}/" if directory else ""
    lockfile = f"{prefix}package-lock.json"
    lock = read_json(root / lockfile)
    if lock.get("lockfileVersion", 0) < 2:
        raise ValueError(f"{lockfile}: npm lockfile v2 or v3 is required")
    packages = lock["packages"]
    app_ref = f"application:npm:{directory or 'root'}"
    components = [{"type": "application", "bom-ref": app_ref, "name": lock.get("name", directory or "nccia-web")}]
    references = {}
    for path, package in packages.items():
        if not path:
            continue
        if package.get("link") or not package.get("version"):
            raise ValueError(f"{lockfile}: unsupported link or unversioned package at {path}")
        name = package.get("name") or path.rsplit("node_modules/", 1)[-1]
        reference = f"npm:{directory or 'root'}:{path}@{package['version']}"
        references[path] = reference
        component = library("npm", name, package["version"], reference,
                            [package["license"]] if package.get("license") else [],
                            package.get("dev", False) or package.get("optional", False))
        component["properties"] = [{"name": "nccia:lockfile", "value": lockfile},
                                   {"name": "nccia:install-path", "value": path}]
        if package.get("integrity"):
            component["hashes"] = integrity_hashes(package["integrity"])
        components.append(component)

    def resolve(path, name):
        # Node resolves nested packages before hoisted parent/root packages.
        current = path
        while current:
            candidate = f"{current}/node_modules/{name}"
            if candidate in references:
                return references[candidate]
            current = current.rsplit("/node_modules/", 1)[0] if "/node_modules/" in current else ""
        return references.get(f"node_modules/{name}")

    dependencies = []
    for path, package in packages.items():
        names = set(package.get("dependencies", {})) | set(package.get("optionalDependencies", {})) | set(package.get("peerDependencies", {}))
        if not path:
            names |= set(package.get("devDependencies", {}))
        targets = {resolve(path, name) for name in names}
        targets.discard(None)
        reference = references.get(path, app_ref)
        targets.discard(reference)
        dependencies.append({"ref": reference, "dependsOn": sorted(targets)})
    return app_ref, components, dependencies


def generate(root):
    components, dependencies, applications = [], [], []
    for app, items, edges in [composer_inventory(root)] + [npm_inventory(root, directory) for directory in ("", "react-app", "mobile-app")]:
        applications.append(app)
        components.extend(items)
        dependencies.extend(edges)
    dependencies.append({"ref": "application:nccia", "dependsOn": sorted(applications)})
    lockfiles = ("composer.lock", "package-lock.json", "react-app/package-lock.json", "mobile-app/package-lock.json")
    properties = [{"name": f"nccia:sha256:{path}", "value": hashlib.sha256((root / path).read_bytes()).hexdigest()} for path in lockfiles]
    return {"$schema": "http://cyclonedx.org/schema/bom-1.5.schema.json", "bomFormat": "CycloneDX", "specVersion": "1.5", "version": 1,
            "metadata": {"component": {"type": "application", "bom-ref": "application:nccia", "name": "NCCIA CMS"}, "properties": properties},
            "components": sorted(components, key=lambda item: item["bom-ref"]),
            "dependencies": sorted(dependencies, key=lambda item: item["ref"])}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[2])
    parser.add_argument("--output", type=Path, default=Path("security-reports/sbom.cdx.json"))
    args = parser.parse_args()
    bom = generate(args.root.resolve())
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(bom, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"SBOM: {len(bom['components'])} locked components -> {args.output}")


if __name__ == "__main__":
    main()
