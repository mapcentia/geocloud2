#!/usr/bin/env python3
"""Generate a versioned release SBOM (source + delivered image + GIS-native)
for one GC2 git tag. See docs/RELEASE-SBOM.md."""
from __future__ import annotations

import argparse
import json
import os
import subprocess
import tempfile
from pathlib import Path

import sbom_release as sr

SOURCE_CDX = "gc2-source.cdx.json"
SOURCE_SYFT = "gc2-source.syft.json"
IMAGE_CDX = "gc2-image.cdx.json"
IMAGE_SYFT = "gc2-image.syft.json"
GIS_CDX = "gis-native.cdx.json"
VULNS_CDX = "vulns.cdx.json"


def _syft_env():
    return {k: v for k, v in os.environ.items() if not k.startswith("SYFT_")}


def _syft_version(syft, capture) -> str:
    try:
        out = capture([str(syft), "version", "-o", "json"], text=True, env=_syft_env())
        return json.loads(out).get("version", "unknown")
    except Exception:
        return "unknown"


def resolve_image_digest(image, run, capture) -> str:
    run(["docker", "pull", image], check=True)
    out = capture(
        ["docker", "inspect", "--format", "{{if .RepoDigests}}{{index .RepoDigests 0}}{{end}}", image],
        text=True,
    ).strip()
    if "@sha256:" not in out:
        raise SystemExit(f"No RepoDigest for {image}; push it first")
    return out.split("@", 1)[1]  # sha256:...


def _scan_dir(syft, config, snapshot, name, version, out_cdx, out_syft, run):
    run([
        str(syft), "-c", str(config), "scan", f"dir:{snapshot}",
        "--source-name", name, "--source-version", version,
        "-o", f"cyclonedx-json@1.7={out_cdx}", "-o", f"syft-json={out_syft}",
    ], check=True, env=_syft_env())


def _scan_image(syft, image_at_digest, name, version, out_cdx, out_syft, run):
    run([
        str(syft), "scan", image_at_digest,
        "--source-name", name, "--source-version", version,
        "-o", f"cyclonedx-json@1.7={out_cdx}", "-o", f"syft-json={out_syft}",
    ], check=True, env=_syft_env())


def _coverage(root, source_cdx) -> dict:
    present = sr.sbom_name_versions(source_cdx)
    entries = []
    composer = root / "app" / "composer.lock"
    if composer.is_file():
        groups = sr.parse_composer_lock(composer)
        for group, locked in groups.items():
            entries.append(sr.compute_lockfile_coverage(
                locked, present, path="app/composer.lock", group=group))
    for npm_path in ["package-lock.json", "dashboard/package-lock.json"]:
        p = root / npm_path
        if p.is_file():
            entries.append(sr.compute_lockfile_coverage(
                sr.parse_npm_lock(p), present, path=npm_path, group="npm"))
    return {
        "coverageCheck": "name/version presence; not full dependency-graph validation",
        "lockfileCoverage": entries,
    }


def _grype_env():
    return {k: v for k, v in os.environ.items() if not k.startswith("GRYPE_")}


def _grype_version(grype, capture) -> str:
    try:
        out = capture([str(grype), "version", "-o", "json"], text=True, env=_grype_env())
        return json.loads(out).get("version", "unknown")
    except Exception:
        return "unknown"


def _grype_db_status(grype, capture) -> dict:
    try:
        out = capture([str(grype), "db", "status", "-o", "json"], text=True, env=_grype_env())
        status = json.loads(out)
        return {k: status[k] for k in ("schemaVersion", "built", "checksum", "location")
                if k in status}
    except Exception:
        return {}


def _scan_vulns(grype, image_cdx, out_path, capture) -> None:
    """Scan the image SBOM for known vulnerabilities; write CycloneDX (VEX-style)."""
    vulns = capture([str(grype), f"sbom:{image_cdx}", "-o", "cyclonedx-json"],
                    text=True, env=_grype_env())
    Path(out_path).write_text(vulns if vulns.endswith("\n") else vulns + "\n")


def generate(*, repo, tag, image, image_version, syft, output, config_dir,
             gis_yaml, generated_at, grype=None, run=subprocess.run,
             capture=subprocess.check_output) -> int:
    repo = Path(repo)
    output = Path(output)
    config_dir = Path(config_dir)
    output.mkdir(parents=True, exist_ok=False)  # immutability

    commit = sr.resolve_commit(repo, tag)

    # 1) source SBOM from git-archive snapshot
    with tempfile.TemporaryDirectory(prefix="gc2-src-") as tmp:
        snapshot = sr.git_archive_snapshot(repo, tag, Path(tmp) / "src")
        _scan_dir(syft, config_dir / "syft-config.yaml", snapshot, "gc2", tag,
                  output / SOURCE_CDX, output / SOURCE_SYFT, run)
        coverage_data = _coverage(snapshot, json.loads((output / SOURCE_CDX).read_text()))

    # 2) image SBOM by digest
    digest = resolve_image_digest(image, run, capture)
    image_at_digest = f"{image.split(':')[0]}@{digest}"
    _scan_image(syft, image_at_digest, "gc2-image", image_version,
                output / IMAGE_CDX, output / IMAGE_SYFT, run)

    # 3) GIS-native supplement
    gis_bom = sr.gis_components_to_cyclonedx(sr.load_gis_components(gis_yaml), tag=tag)
    (output / GIS_CDX).write_text(json.dumps(gis_bom, indent=2) + "\n")

    # 4) validate all cdx (hard: schema + refs)
    validation_out = output / "validation.json"
    result = run([
        "node", str(config_dir / "validate.cjs"),
        "--modules", str(config_dir / "node_modules"),
        "--out", str(validation_out),
        str(output / SOURCE_CDX), str(output / IMAGE_CDX), str(output / GIS_CDX),
    ], check=False)
    validation_passed = getattr(result, "returncode", 1) == 0

    # 5) coverage + CSV
    source_cdx = json.loads((output / SOURCE_CDX).read_text())
    image_cdx = json.loads((output / IMAGE_CDX).read_text())
    (output / "coverage.json").write_text(json.dumps(coverage_data, indent=2) + "\n")
    rows = (sr.cdx_component_rows(source_cdx, artifact="source")
            + sr.cdx_component_rows(image_cdx, artifact="image")
            + sr.cdx_component_rows(gis_bom, artifact="gis-native"))
    sr.write_csv(rows, output / "components.csv")

    # 5b) optional vulnerability scan of the image SBOM (Grype)
    vuln_meta = None
    if grype is not None:
        _scan_vulns(grype, output / IMAGE_CDX, output / VULNS_CDX, capture)
        vuln_meta = {
            "tool": {"name": "grype", "version": _grype_version(grype, capture),
                     "sha256": sr.sha256_file(grype)},
            "db": _grype_db_status(grype, capture),
            "target": IMAGE_CDX,
            "output": VULNS_CDX,
            "note": ("Findings are candidates; exploitability is decided in triage. "
                     "GIS-native components (generic/github purls) are not matched by "
                     "the vulnerability feeds and must be tracked upstream manually."),
        }

    # 6) provenance + release manifest
    syft_sha = sr.sha256_file(syft)
    config_sha = sr.sha256_file(config_dir / "syft-config.yaml")
    syft_ver = _syft_version(syft, capture)
    dirty = bool(capture(["git", "-C", str(repo), "status", "--porcelain"], text=True).strip())
    artifacts = {
        name: {"sha256": sr.sha256_file(output / name), "format": "CycloneDX 1.7",
               "components": len(json.loads((output / name).read_text()).get("components", []))}
        for name in [SOURCE_CDX, IMAGE_CDX, GIS_CDX]
    }
    (output / "provenance.json").write_text(json.dumps({
        "generated_at": generated_at,
        "tool": {"name": "syft", "version": syft_ver, "sha256": syft_sha},
        "config_sha256": config_sha,
        "commit": commit,
        "scope": "git-archive snapshot of tag + delivered image by digest + GIS-native supplement",
        "working_tree_dirty": dirty,
    }, indent=2) + "\n")
    manifest = sr.build_release_manifest(
        tag=tag, commit=commit, image_ref=image, image_digest=digest,
        image_version=image_version, generated_at=generated_at,
        syft_version=syft_ver, syft_sha256=syft_sha, config_sha256=config_sha,
        artifacts=artifacts, validation_passed=validation_passed)
    if vuln_meta is not None:
        manifest["vulnerability_scan"] = vuln_meta
        manifest["scope"]["vulnerability_scan"] = (
            "performed with grype; findings require triage (see docs/RELEASE-SBOM.md)")
    (output / "release.json").write_text(json.dumps(manifest, indent=2) + "\n")

    # 7) checksums over every file in the dir
    lines = []
    for f in sorted(output.iterdir()):
        if f.name == "sha256sums.txt" or not f.is_file():
            continue
        lines.append(f"{sr.sha256_file(f)}  {f.name}")
    (output / "sha256sums.txt").write_text("\n".join(lines) + "\n")

    return 0 if validation_passed else 1


def main(argv=None) -> int:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("--syft", required=True, type=Path, help="Path to pinned syft binary")
    p.add_argument("--tag", help="Git tag to release (default: current exact tag)")
    p.add_argument("--image", required=True, help="e.g. mapcentia/gc2:php8.4-2")
    p.add_argument("--output", type=Path, help="Default: sbom/<tag>")
    p.add_argument("--grype", type=Path,
                   help="Path to pinned grype binary; when given, scan the image "
                        "SBOM for vulnerabilities into vulns.cdx.json")
    from datetime import datetime, timezone
    args = p.parse_args(argv)
    repo = Path(__file__).resolve().parents[1]
    tag = args.tag or subprocess.check_output(
        ["git", "-C", str(repo), "describe", "--tags", "--exact-match"],
        text=True).strip()
    image_version = args.image.split(":", 1)[1] if ":" in args.image else "unknown"
    output = args.output or (repo / "sbom" / tag)
    generated_at = datetime.now(timezone.utc).isoformat()
    return generate(
        repo=repo, tag=tag, image=args.image, image_version=image_version,
        syft=args.syft, output=output, config_dir=repo / "sbom" / "config",
        gis_yaml=repo / "sbom" / "gis-native-components.yaml",
        grype=args.grype, generated_at=generated_at)


if __name__ == "__main__":
    raise SystemExit(main())
