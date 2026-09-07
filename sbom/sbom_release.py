"""Pure helpers for release SBOM generation. No syft/docker here (see generate.py)."""
from __future__ import annotations

import csv
import hashlib
import json
import subprocess
from pathlib import Path

import yaml

import tarfile
import tempfile as _tempfile


def resolve_commit(repo, tag: str) -> str:
    out = subprocess.check_output(
        ["git", "-C", str(repo), "rev-list", "-n", "1", tag],
        text=True, stderr=subprocess.DEVNULL,
    )
    return out.strip()


def git_archive_snapshot(repo, tag: str, dest) -> Path:
    dest = Path(dest)
    dest.mkdir(parents=True, exist_ok=True)
    with _tempfile.NamedTemporaryFile(suffix=".tar", delete=False) as tmp:
        archive = Path(tmp.name)
    try:
        subprocess.run(
            ["git", "-C", str(repo), "archive", "--format=tar",
             f"--output={archive}", tag],
            check=True,
        )
        with tarfile.open(archive) as tar:
            tar.extractall(dest, filter="data")
    finally:
        archive.unlink(missing_ok=True)
    return dest


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


def cdx_component_rows(cdx: dict, *, artifact: str) -> list:
    rows = []
    for c in cdx.get("components", []) or []:
        rows.append({
            "artifact": artifact,
            "type": c.get("type", ""),
            "name": c.get("name", ""),
            "version": c.get("version", ""),
            "purl": c.get("purl", ""),
        })
    return rows


def write_csv(rows: list, out_path) -> None:
    fields = ["artifact", "type", "name", "version", "purl"]
    with open(out_path, "w", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)


def build_release_manifest(*, tag, commit, image_ref, image_digest, image_version,
                           generated_at, syft_version, syft_sha256, config_sha256,
                           artifacts, validation_passed) -> dict:
    return {
        "product": "gc2",
        "git_tag": tag,
        "git_commit": commit,
        "image": {
            "ref": image_ref,
            "digest": image_digest,
            "platform": "linux/amd64",
            "image_version": image_version,
        },
        "generated_at": generated_at,
        "tool": {"name": "syft", "version": syft_version, "sha256": syft_sha256},
        "config_sha256": config_sha256,
        "artifacts": artifacts,
        "validation": {"all_passed": validation_passed},
        "scope": {
            "source": "tracked git-archive of tag",
            "image": "scanned by digest",
            "gis_native": "hand-maintained supplement from Dockerfile pins",
            "vulnerability_scan": "not performed in this step",
        },
    }
