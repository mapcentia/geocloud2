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
