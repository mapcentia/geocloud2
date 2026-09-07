"""Pure helpers for release SBOM generation. No syft/docker here (see generate.py)."""
from __future__ import annotations

import csv
import hashlib
import json
import subprocess
from pathlib import Path

import yaml


def sha256_file(path) -> str:
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def load_gis_components(yaml_path) -> list:
    data = yaml.safe_load(Path(yaml_path).read_text())
    components = (data or {}).get("components", []) or []
    for c in components:
        if not c.get("name") or not c.get("version"):
            raise ValueError(f"GIS component missing name/version: {c!r}")
    return components


def _purl_for(component: dict) -> str:
    if component.get("purl"):
        return component["purl"]
    return f"pkg:generic/{component['name']}@{component['version']}"


def gis_components_to_cyclonedx(components: list, *, tag: str) -> dict:
    libs = []
    seen = set()
    for c in components:
        purl = _purl_for(c)
        if purl in seen:
            raise ValueError(f"Duplicate purl/bom-ref: {purl}")
        seen.add(purl)
        entry = {
            "type": "library",
            "name": c["name"],
            "version": str(c["version"]),
            "purl": purl,
            "bom-ref": purl,
        }
        if c.get("notes"):
            entry["description"] = c["notes"]
        libs.append(entry)
    return {
        "bomFormat": "CycloneDX",
        "specVersion": "1.7",
        "metadata": {
            "component": {
                "type": "application",
                "name": "gc2-gis-native",
                "version": tag,
                "bom-ref": f"gc2-gis-native@{tag}",
            }
        },
        "components": libs,
    }


def parse_composer_lock(lock_path) -> dict:
    data = json.loads(Path(lock_path).read_text())
    return {
        group: [(p["name"], p["version"]) for p in data.get(group, []) or []]
        for group in ("packages", "packages-dev")
    }


def parse_npm_lock(lock_path) -> list:
    """Parse npm lockfile (v1 or v2/v3) and return [(name, version), ...] tuples.

    Handles both npm lockfile formats:
    - v2/v3: packages map with entries like "node_modules/<name>": {version: "..."}
    - v1: dependencies map with entries like "<name>": {version: "..."}

    In v2/v3 format, skips root entry (empty string key).
    """
    data = json.loads(Path(lock_path).read_text())
    pairs = []
    # Try v2/v3 format first (with "packages" map)
    packages = data.get("packages")
    if packages:
        for key, meta in packages.items():
            if not key:  # root project entry
                continue
            name = key.split("node_modules/")[-1]
            version = (meta or {}).get("version")
            if name and version:
                pairs.append((name, version))
    else:
        # Fall back to v1 format (with "dependencies")
        for name, meta in (data.get("dependencies") or {}).items():
            version = (meta or {}).get("version")
            if name and version:
                pairs.append((name, version))
    return pairs


def sbom_name_versions(cdx: dict) -> set:
    return {
        (c.get("name"), c.get("version"))
        for c in cdx.get("components", []) or []
        if c.get("name") and c.get("version")
    }


def compute_lockfile_coverage(locked, present, *, path, group) -> dict:
    unique = sorted(set(locked))
    missing = [list(nv) for nv in unique if nv not in present]
    return {
        "path": path,
        "group": group,
        "lockedEntries": len(locked),
        "uniqueNameVersions": len(unique),
        "missingNameVersions": missing,
    }
