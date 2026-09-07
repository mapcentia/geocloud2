# Release SBOM Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gør en versionsbundet, valideret SBOM (kilde + leveret runtime-image + fra-kilde GIS-libs) til en fast, committet del af hver GC2-udgivelse (= ét git-tag).

**Architecture:** Et Python-orkestreringsscript (`sbom/generate.py`) kalder en pinnet syft-binær til at scanne dels et `git archive`-snapshot af tagget, dels det leverede image `mapcentia/gc2:php8.4-N` ved dets `sha256`-digest. Rene, testbare hjælpefunktioner (`sbom/sbom_release.py`) bygger et GIS-supplement, coverage, CSV og et `release.json`-manifest, der binder tag ↔ commit ↔ image-digest ↔ `php8.4-N`. En generaliseret Node-validator (`sbom/config/validate.cjs`) skema-validerer alle CycloneDX-filer. Alt skrives immutabelt til `sbom/<tag>/`.

**Tech Stack:** Python 3 (stdlib + PyYAML 6.0.1), Syft (pinnet binær, CycloneDX JSON 1.7), Node.js (Ajv 8 / ajv-formats 3), Docker CLI, git.

**Spec:** `docs/superpowers/specs/2026-09-07-release-sbom-design.md`

## Global Constraints

- **Format:** CycloneDX JSON **1.7** for alle `*.cdx.json`. Syft-output pinnes eksplicit til `-o cyclonedx-json@1.7` (deterministisk; baseline brugte bar `cyclonedx-json`).
- **Immutabilitet:** outputmappen oprettes med `exist_ok=False`; eksisterende resultater overskrives aldrig.
- **Pinnet værktøj:** syft leveres som binær via `--syft <sti>`; scriptet auto-downloader aldrig. `tool_sha256` optages.
- **Ingen dependency-installation under scan:** kilde-scan bruger `git archive`-snapshot; aldrig `git checkout`/`npm install`/`composer install`. Miljø renses for `SYFT_*`.
- **Image kun ved digest:** scan aldrig et flydende tag; digest skal resolves via `docker inspect` ellers fejler kørslen.
- **Test-tooling:** kun stdlib `unittest` + PyYAML (ingen pytest). Node-validatoren bruger `ajv` + `ajv-formats` fra `sbom/config/node_modules`.
- **Validering er hård:** skema- eller reference-fejl giver non-zero exit. Manglende `purl`/`version`/licens tælles (soft), kaster ikke.
- **Ikke-mål (v1):** ingen sårbarhedsscanning, ingen Dockerfile-ændring, ingen CI, kun produkt `gc2`, kun image `mapcentia/gc2:php8.4-N`.
- **Kør tests fra repo-roden:** `python3 -m unittest discover -s sbom/tests -t . -v`.

---

## File Structure

**Nye filer:**
- `sbom/sbom_release.py` — rene, testbare hjælpefunktioner (ingen syft/docker).
- `sbom/generate.py` — CLI-orkestrator; wirer git/syft/docker/node + skriver alle filer.
- `sbom/gis-native-components.yaml` — vedligeholdt liste over fra-kilde GIS-libs (pins fra Dockerfilen).
- `sbom/config/syft-config.yaml` — syft cataloger-config (kopi fra wiki).
- `sbom/config/validate.cjs` — generaliseret CycloneDX 1.7-validator (rewrite af wiki-versionen).
- `sbom/config/package.json` — `ajv` + `ajv-formats` til validatoren.
- `sbom/config/validation-schemas/*.json` — CycloneDX 1.7-skemaer (kopi fra wiki).
- `sbom/tests/test_sbom_release.py` — unittest for de rene funktioner.
- `sbom/tests/test_generate.py` — unittest for orkestratoren via injiceret fake runner.
- `sbom/tests/fixtures/valid.cdx.json`, `sbom/tests/fixtures/invalid.cdx.json` — validator-fixtures.
- `docs/RELEASE-SBOM.md` — trin-for-trin release-procedure.

**Ændrede filer:**
- `sbom/readme.md` — peg på baseline + release-mapper; beskriv proceduren.
- `.gitignore` — ignorér `sbom/config/node_modules/`.

**Uændret:** `docker/Dockerfile`, `sbom/2026-09-07-7477faaba479/` (historik).

Kildecommit for grounding (findes i dette repo): tag `2026.6.6` → `7c3e2384f3f7…`. `app/composer.lock`: 99 `packages`, 60 `packages-dev`. Lockfiles til stede: `app/composer.lock`, `package-lock.json`, `dashboard/package-lock.json`.

---

## Task 1: Selvindeholdt config + validator-afhængigheder

**Files:**
- Create: `sbom/config/syft-config.yaml`
- Create: `sbom/config/validation-schemas/bom-1.7.schema.json`, `.../spdx.schema.json`, `.../jsf-0.82.schema.json`, `.../cryptography-defs.schema.json`
- Create: `sbom/config/package.json`
- Modify: `.gitignore`

**Interfaces:**
- Produces: `sbom/config/` med syft-config, skemaer og `node_modules` (efter `npm install`) som Task 6 og `generate.py` bruger.

- [ ] **Step 1: Kopiér config og skemaer fra wiki-eksporten**

```bash
mkdir -p sbom/config/validation-schemas
cp "/home/mh/Documents/Obsidian Vault/exports/sbom/2026-09-07/syft-config.yaml" sbom/config/syft-config.yaml
cp "/home/mh/Documents/Obsidian Vault/exports/sbom/2026-09-07/validation-schemas/"*.json sbom/config/validation-schemas/
ls sbom/config/validation-schemas/
```
Expected: `bom-1.7.schema.json  cryptography-defs.schema.json  jsf-0.82.schema.json  spdx.schema.json`

- [ ] **Step 2: Opret `sbom/config/package.json`**

```json
{
  "name": "gc2-sbom-validate",
  "private": true,
  "version": "1.0.0",
  "description": "CycloneDX 1.7 validation for GC2 release SBOMs",
  "dependencies": {
    "ajv": "^8.17.1",
    "ajv-formats": "^3.0.1"
  }
}
```

- [ ] **Step 3: Installér validator-afhængigheder**

Run: `cd sbom/config && npm install && cd ../..`
Expected: `sbom/config/node_modules/ajv` og `.../ajv-formats` findes.

- [ ] **Step 4: Ignorér node_modules i git**

Tilføj denne linje til `.gitignore` (bevar eksisterende indhold):

```
sbom/config/node_modules/
```

- [ ] **Step 5: Verificér at ajv kan loades**

Run: `node -e "require('./sbom/config/node_modules/ajv'); require('./sbom/config/node_modules/ajv-formats'); console.log('ok')"`
Expected: `ok`

- [ ] **Step 6: Commit**

```bash
git add sbom/config/syft-config.yaml sbom/config/validation-schemas sbom/config/package.json sbom/config/package-lock.json .gitignore
git commit -m "chore(sbom): vendor syft config, CycloneDX 1.7 schemas and validator deps"
```

---

## Task 2: GIS-supplement (YAML → CycloneDX)

**Files:**
- Create: `sbom/gis-native-components.yaml`
- Create: `sbom/sbom_release.py`
- Test: `sbom/tests/test_sbom_release.py`

**Interfaces:**
- Produces:
  - `load_gis_components(yaml_path: Path) -> list[dict]` — læser `components:`; hæver `ValueError` hvis `name`/`version` mangler.
  - `gis_components_to_cyclonedx(components: list[dict], *, tag: str) -> dict` — CycloneDX 1.7 bom; hver komponent bliver en `library` med `version`, `purl` (fallback `pkg:generic/<name>@<version>`) og `bom-ref` = purl; `metadata.component` = `{type:"application", name:"gc2-gis-native", version:tag, bom-ref:"gc2-gis-native@<tag>"}`.

- [ ] **Step 1: Opret `sbom/gis-native-components.yaml`**

```yaml
# Fra-kilde-byggede GIS-libs i docker/Dockerfile som syft ikke fanger som pakker.
# Pins SKAL holdes i sync med docker/Dockerfile ved hver image-version (php8.4-N).
# Hvor 'purl' udelades, genereres pkg:generic/<name>@<version>.
components:
  - name: gdal
    version: "3.9.2"
    source: "http://download.osgeo.org/gdal/3.9.2/gdal392.zip"
    notes: "Bygget med ECW 3.3 + LibKML (ECW_VERSION=3)"
  - name: mapserver
    version: "8.0-rsvg_fix"
    purl: "pkg:github/mapcentia/mapserver@rsvg_fix"
    notes: "Branch-klon; commit ikke pinnet i Dockerfile"
  - name: mapcache
    version: "branch-1-12"
    purl: "pkg:github/mapserver/mapcache@branch-1-12"
    notes: "Bygget med WITH_MEMCACHE=1"
  - name: swig
    version: "master"
    purl: "pkg:github/swig/swig@master"
    notes: "Kloned fra master; PHP8-support"
  - name: ogr2postgis
    version: "gui"
    purl: "pkg:github/mapcentia/ogr2postgis@gui"
  - name: libecwj2
    version: "3.3"
    notes: "ECW/JPEG2000 SDK; ECW_VERSION build-arg styrer 3.3 vs 5.3"
  - name: qgis-server
    version: "apt-ltr"
    notes: "Fra qgis.org debian-ltr; faktisk version aflaeses i imaget"
```

- [ ] **Step 2: Skriv de fejlende tests**

Create `sbom/tests/test_sbom_release.py`:

```python
import json
import pathlib
import sys
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))
import sbom_release as sr  # noqa: E402

REPO = pathlib.Path(__file__).resolve().parents[2]
GIS_YAML = REPO / "sbom" / "gis-native-components.yaml"


class GisComponentsTest(unittest.TestCase):
    def test_load_returns_components(self):
        comps = sr.load_gis_components(GIS_YAML)
        names = {c["name"] for c in comps}
        self.assertIn("gdal", names)
        self.assertIn("mapserver", names)

    def test_load_rejects_missing_version(self, tmp=None):
        import tempfile
        p = pathlib.Path(tempfile.mkstemp(suffix=".yaml")[1])
        p.write_text("components:\n  - name: gdal\n")
        with self.assertRaises(ValueError):
            sr.load_gis_components(p)

    def test_to_cyclonedx_every_library_has_purl_and_version(self):
        comps = sr.load_gis_components(GIS_YAML)
        bom = sr.gis_components_to_cyclonedx(comps, tag="2026.6.7")
        self.assertEqual(bom["bomFormat"], "CycloneDX")
        self.assertEqual(bom["specVersion"], "1.7")
        self.assertEqual(bom["metadata"]["component"]["version"], "2026.6.7")
        for c in bom["components"]:
            self.assertEqual(c["type"], "library")
            self.assertTrue(c["version"])
            self.assertTrue(c["purl"])
            self.assertEqual(c["bom-ref"], c["purl"])

    def test_to_cyclonedx_generates_generic_purl_fallback(self):
        bom = sr.gis_components_to_cyclonedx(
            [{"name": "libecwj2", "version": "3.3"}], tag="t"
        )
        self.assertEqual(bom["components"][0]["purl"], "pkg:generic/libecwj2@3.3")

    def test_to_cyclonedx_unique_bom_refs(self):
        comps = sr.load_gis_components(GIS_YAML)
        bom = sr.gis_components_to_cyclonedx(comps, tag="t")
        refs = [c["bom-ref"] for c in bom["components"]]
        refs.append(bom["metadata"]["component"]["bom-ref"])
        self.assertEqual(len(refs), len(set(refs)))


if __name__ == "__main__":
    unittest.main()
```

- [ ] **Step 3: Kør testen og bekræft at den fejler**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: FAIL med `ModuleNotFoundError: No module named 'sbom_release'`.

- [ ] **Step 4: Skriv minimal implementation**

Create `sbom/sbom_release.py`:

```python
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
```

- [ ] **Step 5: Kør testen og bekræft at den passerer**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add sbom/gis-native-components.yaml sbom/sbom_release.py sbom/tests/test_sbom_release.py
git commit -m "feat(sbom): GIS-native component supplement to CycloneDX"
```

---

## Task 3: Lockfile-coverage

**Files:**
- Modify: `sbom/sbom_release.py`
- Test: `sbom/tests/test_sbom_release.py`

**Interfaces:**
- Produces:
  - `parse_composer_lock(lock_path) -> dict[str, list[tuple[str, str]]]` — `{"packages":[(name,version)...], "packages-dev":[...]}`.
  - `parse_npm_lock(lock_path) -> list[tuple[str, str]]` — name/version fra npm lockfile v2/v3 `packages`-map (root-entry `""` springes over; navn = tekst efter sidste `node_modules/`).
  - `sbom_name_versions(cdx: dict) -> set[tuple[str, str]]` — (name, version)-par fra en cdx.
  - `compute_lockfile_coverage(locked, present, *, path, group) -> dict` — `{path, group, lockedEntries, uniqueNameVersions, missingNameVersions}` hvor `missingNameVersions` er sorterede `[name, version]`-lister ikke fundet i `present`.

- [ ] **Step 1: Skriv de fejlende tests (tilføj til `test_sbom_release.py`)**

```python
class CoverageTest(unittest.TestCase):
    def test_parse_composer_lock_groups(self):
        groups = sr.parse_composer_lock(REPO / "app" / "composer.lock")
        self.assertEqual(len(groups["packages"]), 99)
        self.assertEqual(len(groups["packages-dev"]), 60)
        self.assertIn(("amphp/amp", "v3.1.0"), groups["packages"])

    def test_parse_npm_lock_pairs(self):
        pairs = sr.parse_npm_lock(REPO / "package-lock.json")
        self.assertTrue(pairs)
        for name, version in pairs[:5]:
            self.assertTrue(name)
            self.assertTrue(version)

    def test_coverage_reports_missing(self):
        locked = [("a", "1"), ("b", "2"), ("a", "1")]
        present = {("a", "1")}
        cov = sr.compute_lockfile_coverage(
            locked, present, path="x.lock", group="packages"
        )
        self.assertEqual(cov["lockedEntries"], 3)
        self.assertEqual(cov["uniqueNameVersions"], 2)
        self.assertEqual(cov["missingNameVersions"], [["b", "2"]])

    def test_sbom_name_versions_ignores_incomplete(self):
        cdx = {"components": [
            {"name": "x", "version": "1"},
            {"name": "y"},
        ]}
        self.assertEqual(sr.sbom_name_versions(cdx), {("x", "1")})
```

- [ ] **Step 2: Kør og bekræft fejl**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: FAIL med `AttributeError: module 'sbom_release' has no attribute 'parse_composer_lock'`.

- [ ] **Step 3: Implementér (tilføj til `sbom_release.py`)**

```python
def parse_composer_lock(lock_path) -> dict:
    data = json.loads(Path(lock_path).read_text())
    return {
        group: [(p["name"], p["version"]) for p in data.get(group, []) or []]
        for group in ("packages", "packages-dev")
    }


def parse_npm_lock(lock_path) -> list:
    data = json.loads(Path(lock_path).read_text())
    pairs = []
    for key, meta in (data.get("packages") or {}).items():
        if not key:  # root project entry
            continue
        name = key.split("node_modules/")[-1]
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
```

- [ ] **Step 4: Kør og bekræft pass**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: PASS (alle tidligere + 4 nye).

- [ ] **Step 5: Commit**

```bash
git add sbom/sbom_release.py sbom/tests/test_sbom_release.py
git commit -m "feat(sbom): lockfile coverage from composer/npm vs SBOM"
```

---

## Task 4: CSV, checksums og release-manifest

**Files:**
- Modify: `sbom/sbom_release.py`
- Test: `sbom/tests/test_sbom_release.py`

**Interfaces:**
- Produces:
  - `cdx_component_rows(cdx: dict, *, artifact: str) -> list[dict]` — rækker `{artifact, type, name, version, purl}`.
  - `write_csv(rows: list, out_path) -> None` — kolonner `artifact,type,name,version,purl`.
  - `build_release_manifest(*, tag, commit, image_ref, image_digest, image_version, generated_at, syft_version, syft_sha256, config_sha256, artifacts, validation_passed) -> dict` — jf. spec §4.4.
  - `sha256_file(path) -> str` (allerede fra Task 2).

- [ ] **Step 1: Skriv de fejlende tests (tilføj)**

```python
import tempfile


class ManifestCsvTest(unittest.TestCase):
    def test_cdx_rows(self):
        cdx = {"components": [
            {"type": "library", "name": "x", "version": "1", "purl": "pkg:generic/x@1"},
        ]}
        rows = sr.cdx_component_rows(cdx, artifact="source")
        self.assertEqual(rows, [{
            "artifact": "source", "type": "library",
            "name": "x", "version": "1", "purl": "pkg:generic/x@1",
        }])

    def test_write_csv_has_header(self):
        p = pathlib.Path(tempfile.mkstemp(suffix=".csv")[1])
        sr.write_csv([{"artifact": "a", "type": "library",
                       "name": "n", "version": "v", "purl": "p"}], p)
        text = p.read_text()
        self.assertTrue(text.startswith("artifact,type,name,version,purl"))
        self.assertIn("a,library,n,v,p", text)

    def test_release_manifest_shape(self):
        m = sr.build_release_manifest(
            tag="2026.6.7", commit="abc", image_ref="mapcentia/gc2:php8.4-2",
            image_digest="sha256:dead", image_version="php8.4-2",
            generated_at="2026-09-07T12:00:00Z", syft_version="1.51.1",
            syft_sha256="ff", config_sha256="cc",
            artifacts={"gc2-source.cdx.json": {"sha256": "11", "components": 2385}},
            validation_passed=True,
        )
        self.assertEqual(m["product"], "gc2")
        self.assertEqual(m["git_tag"], "2026.6.7")
        self.assertEqual(m["image"]["digest"], "sha256:dead")
        self.assertEqual(m["image"]["image_version"], "php8.4-2")
        self.assertTrue(m["validation"]["all_passed"])
        self.assertEqual(m["scope"]["vulnerability_scan"], "not performed in this step")
```

- [ ] **Step 2: Kør og bekræft fejl**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: FAIL med `AttributeError: ... 'cdx_component_rows'`.

- [ ] **Step 3: Implementér (tilføj til `sbom_release.py`)**

```python
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
```

- [ ] **Step 4: Kør og bekræft pass**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: PASS (alle + 3 nye).

- [ ] **Step 5: Commit**

```bash
git add sbom/sbom_release.py sbom/tests/test_sbom_release.py
git commit -m "feat(sbom): CSV export and release.json manifest builder"
```

---

## Task 5: Git-helpers (commit-resolve + archive-snapshot)

**Files:**
- Modify: `sbom/sbom_release.py`
- Test: `sbom/tests/test_sbom_release.py`

**Interfaces:**
- Produces:
  - `resolve_commit(repo, tag) -> str` — fuld commit-SHA via `git -C <repo> rev-list -n 1 <tag>`; hæver `subprocess.CalledProcessError` ved ukendt tag.
  - `git_archive_snapshot(repo, tag, dest) -> Path` — pakker `git archive <tag>` ud i `dest` og returnerer `dest`.

- [ ] **Step 1: Skriv de fejlende tests (tilføj)**

```python
class GitHelpersTest(unittest.TestCase):
    def test_resolve_commit_known_tag(self):
        sha = sr.resolve_commit(REPO, "2026.6.6")
        self.assertTrue(sha.startswith("7c3e2384f3f7"))
        self.assertEqual(len(sha), 40)

    def test_resolve_commit_unknown_tag_raises(self):
        import subprocess as sp
        with self.assertRaises(sp.CalledProcessError):
            sr.resolve_commit(REPO, "no-such-tag-xyz")

    def test_git_archive_snapshot_extracts_files(self):
        dest = pathlib.Path(tempfile.mkdtemp())
        sr.git_archive_snapshot(REPO, "2026.6.6", dest)
        self.assertTrue((dest / "app" / "composer.json").is_file())
```

- [ ] **Step 2: Kør og bekræft fejl**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: FAIL med `AttributeError: ... 'resolve_commit'`.

- [ ] **Step 3: Implementér (tilføj til `sbom_release.py`)**

```python
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
```

- [ ] **Step 4: Kør og bekræft pass**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: PASS (alle + 3 nye).

- [ ] **Step 5: Commit**

```bash
git add sbom/sbom_release.py sbom/tests/test_sbom_release.py
git commit -m "feat(sbom): git tag commit-resolve and archive snapshot helpers"
```

---

## Task 6: Generaliseret CycloneDX-validator

**Files:**
- Create: `sbom/config/validate.cjs`
- Create: `sbom/tests/fixtures/valid.cdx.json`, `sbom/tests/fixtures/invalid.cdx.json`

**Interfaces:**
- Consumes: `sbom/config/node_modules` (Task 1), `sbom/config/validation-schemas/` (Task 1).
- Produces: CLI `node sbom/config/validate.cjs --modules <node_modules> --out <validation.json> <cdx>...`; skriver `validation.json` med `all_passed` + pr.-fil-statistik; exit 0 hvis alle skema+reference-checks passerer, ellers exit 1. `purl`/`version`/licens tælles men fejler ikke.

- [ ] **Step 1: Opret fixtures**

`sbom/tests/fixtures/valid.cdx.json`:

```json
{
  "bomFormat": "CycloneDX",
  "specVersion": "1.7",
  "metadata": {
    "component": {
      "type": "application",
      "name": "fixture",
      "version": "1.0.0",
      "bom-ref": "fixture@1.0.0"
    }
  },
  "components": [
    {
      "type": "library",
      "name": "lib-a",
      "version": "1.2.3",
      "purl": "pkg:generic/lib-a@1.2.3",
      "bom-ref": "pkg:generic/lib-a@1.2.3"
    }
  ]
}
```

`sbom/tests/fixtures/invalid.cdx.json` (mangler påkrævet `specVersion` → skema-fejl):

```json
{
  "bomFormat": "CycloneDX",
  "metadata": { "component": { "type": "application", "name": "bad", "version": "1" } },
  "components": []
}
```

- [ ] **Step 2: Skriv den fejlende test (kør validatoren; den findes ikke endnu)**

Run:
```bash
node sbom/config/validate.cjs --modules sbom/config/node_modules \
  --out /tmp/val.json sbom/tests/fixtures/valid.cdx.json; echo "exit=$?"
```
Expected: FAIL — `Error: Cannot find module '.../sbom/config/validate.cjs'`.

- [ ] **Step 3: Implementér `sbom/config/validate.cjs`**

```javascript
#!/usr/bin/env node
// Usage: node validate.cjs --modules <node_modules> --out <validation.json> <cdx> [<cdx> ...]
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

function parseArgs(argv) {
  const opts = { modules: null, out: null, files: [] };
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === '--modules') opts.modules = argv[++i];
    else if (argv[i] === '--out') opts.out = argv[++i];
    else opts.files.push(argv[i]);
  }
  if (!opts.modules || !opts.out || opts.files.length === 0) {
    throw new Error('Usage: validate.cjs --modules <node_modules> --out <validation.json> <cdx>...');
  }
  return opts;
}

const opts = parseArgs(process.argv.slice(2));
const modules = path.resolve(opts.modules);
const Ajv = require(path.join(modules, 'ajv'));
const addFormats = require(path.join(modules, 'ajv-formats'));
const ajv = new Ajv({ strict: false, allErrors: true });
addFormats(ajv);
ajv.addFormat('iri-reference', true);
ajv.addFormat('idn-email', true);

const schemaDir = path.join(__dirname, 'validation-schemas');
const read = p => JSON.parse(fs.readFileSync(p, 'utf8'));
const sha = p => crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
for (const name of ['spdx.schema.json', 'jsf-0.82.schema.json', 'cryptography-defs.schema.json']) {
  ajv.addSchema(read(path.join(schemaDir, name)), 'http://cyclonedx.org/schema/' + name);
}
const validate = ajv.compile(read(path.join(schemaDir, 'bom-1.7.schema.json')));

const report = {
  schema: 'CycloneDX 1.7',
  validator: 'Ajv',
  validatorVersion: require(path.join(modules, 'ajv/package.json')).version,
  uncheckedFormats: ['iri-reference', 'idn-email'],
  files: {},
};
let allPassed = true;

for (const file of opts.files) {
  const cdx = read(file);
  const name = path.basename(file);
  const errors = [];
  const schemaValid = validate(cdx);
  if (!schemaValid) errors.push({ schema: validate.errors });

  const comps = cdx.components || [];
  const allRefs = comps.map(c => c['bom-ref']).filter(Boolean);
  if (cdx.metadata && cdx.metadata.component && cdx.metadata.component['bom-ref']) {
    allRefs.push(cdx.metadata.component['bom-ref']);
  }
  const refs = new Set(allRefs);
  let referencesValid = refs.size === allRefs.length;
  if (!referencesValid) errors.push({ duplicateRefs: true });
  for (const d of cdx.dependencies || []) {
    for (const ref of [d.ref, ...(d.dependsOn || [])]) {
      if (!refs.has(ref)) { referencesValid = false; errors.push({ missingRef: ref }); }
    }
  }

  const libs = comps.filter(c => c.type === 'library');
  const fileReport = {
    schemaValid,
    referencesValid,
    libraryComponents: libs.length,
    fileComponents: comps.length - libs.length,
    librariesMissingVersion: libs.filter(c => !c.version).length,
    librariesMissingPurl: libs.filter(c => !c.purl).length,
    librariesWithoutLicenseMetadata: libs.filter(c => !(c.licenses && c.licenses.length)).length,
    dependencyGraphNodes: (cdx.dependencies || []).length,
    sha256: sha(file),
  };
  if (errors.length) fileReport.errors = errors;
  report.files[name] = fileReport;
  if (!schemaValid || !referencesValid) allPassed = false;
}

report.all_passed = allPassed;
fs.writeFileSync(opts.out, JSON.stringify(report, null, 2) + '\n');
console.log(JSON.stringify(report, null, 2));
process.exit(allPassed ? 0 : 1);
```

- [ ] **Step 4: Kør mod fixtures og bekræft opførsel**

Run:
```bash
node sbom/config/validate.cjs --modules sbom/config/node_modules \
  --out /tmp/val-ok.json sbom/tests/fixtures/valid.cdx.json; echo "valid exit=$?"
node sbom/config/validate.cjs --modules sbom/config/node_modules \
  --out /tmp/val-bad.json sbom/tests/fixtures/invalid.cdx.json; echo "invalid exit=$?"
python3 -c "import json;print('all_passed(valid)=',json.load(open('/tmp/val-ok.json'))['all_passed'])"
```
Expected: `valid exit=0`, `invalid exit=1`, `all_passed(valid)= True`.

- [ ] **Step 5: Commit**

```bash
git add sbom/config/validate.cjs sbom/tests/fixtures/valid.cdx.json sbom/tests/fixtures/invalid.cdx.json
git commit -m "feat(sbom): generalized CycloneDX 1.7 validator (multi-file, soft purl/version)"
```

---

## Task 7: Orkestrator `generate.py`

**Files:**
- Create: `sbom/generate.py`
- Test: `sbom/tests/test_generate.py`

**Interfaces:**
- Consumes: alt fra `sbom_release` (Task 2–5), `validate.cjs` (Task 6), `sbom/config/` (Task 1), `sbom/gis-native-components.yaml` (Task 2).
- Produces: `generate(*, repo, tag, image, syft, output, config_dir, gis_yaml, generated_at, image_version, run=subprocess.run, capture=subprocess.check_output) -> int` — orkestrerer alle trin, skriver hele `sbom/<tag>/`-filsættet og returnerer exit-koden (0 = validering bestået). `main(argv=None)` parser CLI og kalder `generate`. Injicerede `run`/`capture` gør den testbar uden syft/docker.

- [ ] **Step 1: Skriv den fejlende test**

Create `sbom/tests/test_generate.py`:

```python
import json
import pathlib
import sys
import tempfile
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))
import generate as gen  # noqa: E402

REPO = pathlib.Path(__file__).resolve().parents[2]
CONFIG = REPO / "sbom" / "config"
GIS_YAML = REPO / "sbom" / "gis-native-components.yaml"

MIN_CDX = {
    "bomFormat": "CycloneDX", "specVersion": "1.7",
    "metadata": {"component": {"type": "application", "name": "gc2",
                               "version": "x", "bom-ref": "gc2@x"}},
    "components": [{"type": "library", "name": "amphp/amp", "version": "v3.1.0",
                    "purl": "pkg:composer/amphp/amp@v3.1.0",
                    "bom-ref": "pkg:composer/amphp/amp@v3.1.0"}],
}


class FakeTools:
    """Simulerer syft (skriver cdx/syft-filer), docker (digest) og node (validation.json)."""
    def __init__(self, out):
        self.out = pathlib.Path(out)
        self.calls = []

    def run(self, cmd, **kw):
        self.calls.append(cmd)
        prog = pathlib.Path(cmd[0]).name
        if prog == "syft":
            for i, a in enumerate(cmd):
                if a == "-o":
                    target = pathlib.Path(cmd[i + 1].split("=", 1)[1])
                    target.write_text(json.dumps(MIN_CDX))
        elif prog == "node":
            out_idx = cmd.index("--out")
            pathlib.Path(cmd[out_idx + 1]).write_text(
                json.dumps({"all_passed": True, "files": {}}))
        return type("R", (), {"returncode": 0})()

    def capture(self, cmd, **kw):
        self.calls.append(cmd)
        prog = pathlib.Path(cmd[0]).name
        if prog == "docker" and "inspect" in cmd:
            return "mapcentia/gc2@sha256:deadbeef\n"
        return ""


class GenerateTest(unittest.TestCase):
    def _run(self):
        out = pathlib.Path(tempfile.mkdtemp()) / "2026.6.6"
        fake = FakeTools(out)
        # syft binær-sti behøver ikke eksistere ud over sha256 af en rigtig fil:
        syft = pathlib.Path(tempfile.mkstemp()[1])
        syft.write_text("stub")
        code = gen.generate(
            repo=REPO, tag="2026.6.6", image="mapcentia/gc2:php8.4-2",
            image_version="php8.4-2", syft=syft, output=out,
            config_dir=CONFIG, gis_yaml=GIS_YAML,
            generated_at="2026-09-07T12:00:00Z",
            run=fake.run, capture=fake.capture,
        )
        return out, code, fake

    def test_writes_full_file_set(self):
        out, code, _ = self._run()
        self.assertEqual(code, 0)
        for f in ["gc2-source.cdx.json", "gc2-image.cdx.json", "gis-native.cdx.json",
                  "release.json", "provenance.json", "coverage.json",
                  "validation.json", "components.csv", "sha256sums.txt"]:
            self.assertTrue((out / f).is_file(), f"missing {f}")

    def test_release_json_links_tag_commit_digest(self):
        out, _, _ = self._run()
        rel = json.loads((out / "release.json").read_text())
        self.assertEqual(rel["git_tag"], "2026.6.6")
        self.assertTrue(rel["git_commit"].startswith("7c3e2384f3f7"))
        self.assertEqual(rel["image"]["digest"], "sha256:deadbeef")
        self.assertEqual(rel["image"]["image_version"], "php8.4-2")

    def test_refuses_existing_output(self):
        out, _, fake = self._run()
        with self.assertRaises(FileExistsError):
            gen.generate(
                repo=REPO, tag="2026.6.6", image="mapcentia/gc2:php8.4-2",
                image_version="php8.4-2",
                syft=pathlib.Path(tempfile.mkstemp()[1]), output=out,
                config_dir=CONFIG, gis_yaml=GIS_YAML,
                generated_at="t", run=fake.run, capture=fake.capture,
            )


if __name__ == "__main__":
    unittest.main()
```

- [ ] **Step 2: Kør og bekræft fejl**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: FAIL med `ModuleNotFoundError: No module named 'generate'`.

- [ ] **Step 3: Implementér `sbom/generate.py`**

```python
#!/usr/bin/env python3
"""Generate a versioned release SBOM (source + delivered image + GIS-native)
for one GC2 git tag. See docs/RELEASE-SBOM.md."""
from __future__ import annotations

import argparse
import json
import subprocess
import tempfile
from pathlib import Path

import sbom_release as sr

SOURCE_CDX = "gc2-source.cdx.json"
SOURCE_SYFT = "gc2-source.syft.json"
IMAGE_CDX = "gc2-image.cdx.json"
IMAGE_SYFT = "gc2-image.syft.json"
GIS_CDX = "gis-native.cdx.json"


def _syft_version(syft, capture) -> str:
    try:
        out = capture([str(syft), "version", "-o", "json"], text=True)
        return json.loads(out).get("version", "unknown")
    except Exception:
        return "unknown"


def resolve_image_digest(image, run, capture) -> str:
    run(["docker", "pull", image], check=True)
    out = capture(
        ["docker", "inspect", "--format", "{{index .RepoDigests 0}}", image],
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
    ], check=True)


def _scan_image(syft, image_at_digest, name, version, out_cdx, out_syft, run):
    run([
        str(syft), "scan", image_at_digest,
        "--source-name", name, "--source-version", version,
        "-o", f"cyclonedx-json@1.7={out_cdx}", "-o", f"syft-json={out_syft}",
    ], check=True)


def _coverage(repo, source_cdx) -> dict:
    present = sr.sbom_name_versions(source_cdx)
    entries = []
    composer = repo / "app" / "composer.lock"
    if composer.is_file():
        groups = sr.parse_composer_lock(composer)
        for group, locked in groups.items():
            entries.append(sr.compute_lockfile_coverage(
                locked, present, path="app/composer.lock", group=group))
    for npm_path in ["package-lock.json", "dashboard/package-lock.json"]:
        p = repo / npm_path
        if p.is_file():
            entries.append(sr.compute_lockfile_coverage(
                sr.parse_npm_lock(p), present, path=npm_path, group="npm"))
    return {
        "coverageCheck": "name/version presence; not full dependency-graph validation",
        "lockfileCoverage": entries,
    }


def generate(*, repo, tag, image, image_version, syft, output, config_dir,
             gis_yaml, generated_at, run=subprocess.run,
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
    (output / "coverage.json").write_text(
        json.dumps(_coverage(repo, source_cdx), indent=2) + "\n")
    rows = (sr.cdx_component_rows(source_cdx, artifact="source")
            + sr.cdx_component_rows(image_cdx, artifact="image")
            + sr.cdx_component_rows(gis_bom, artifact="gis-native"))
    sr.write_csv(rows, output / "components.csv")

    # 6) provenance + release manifest
    syft_sha = sr.sha256_file(syft)
    config_sha = sr.sha256_file(config_dir / "syft-config.yaml")
    syft_ver = _syft_version(syft, capture)
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
    }, indent=2) + "\n")
    manifest = sr.build_release_manifest(
        tag=tag, commit=commit, image_ref=image, image_digest=digest,
        image_version=image_version, generated_at=generated_at,
        syft_version=syft_ver, syft_sha256=syft_sha, config_sha256=config_sha,
        artifacts=artifacts, validation_passed=validation_passed)
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
        generated_at=generated_at)


if __name__ == "__main__":
    raise SystemExit(main())
```

- [ ] **Step 4: Kør og bekræft pass**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: PASS (alle tests, inkl. 3 nye i test_generate).

- [ ] **Step 5: Commit**

```bash
git add sbom/generate.py sbom/tests/test_generate.py
git commit -m "feat(sbom): release SBOM orchestrator (source + image + GIS) with release.json"
```

---

## Task 8: Procedure-doc + opdateret sbom/readme + integrations-smoke

**Files:**
- Create: `docs/RELEASE-SBOM.md`
- Modify: `sbom/readme.md`

**Interfaces:**
- Consumes: hele værktøjskæden fra Task 1–7.

- [ ] **Step 1: Opret `docs/RELEASE-SBOM.md`**

```markdown
# Release SBOM procedure (GC2)

Hver udgivelse (= ét git-tag) skal have en committet, valideret SBOM under
`sbom/<tag>/`, jf. CRA Bilag I, del II, pkt. 1. Design:
`docs/superpowers/specs/2026-09-07-release-sbom-design.md`.

## Engangsopsætning

1. Installér validator-afhængigheder: `cd sbom/config && npm ci && cd ../..`
2. Hent den pinnede syft-binær (samme major/minor som baseline, 1.51.x):
   download fra https://github.com/anchore/syft/releases og notér `sha256`.
   Binæren committes ikke; stien angives ved kørsel.

## Ved hver udgivelse

1. Byg og push runtime-imaget: `mapcentia/gc2:php8.4-N` (eksisterende flow).
2. Opret git-tagget: `git tag YYYY.MINOR.PATCH && git push --tags`.
3. Opdatér `sbom/gis-native-components.yaml`, hvis Dockerfilens GIS-pins er
   ændret siden sidst.
4. Generér SBOM'en:

       python3 sbom/generate.py --syft /sti/til/syft \
         --tag YYYY.MINOR.PATCH --image mapcentia/gc2:php8.4-N

5. Kontrollér `sbom/<tag>/validation.json` → `all_passed: true`, og gennemgå
   `coverage.json` for nye huller (fx branch-pinnede GIS-libs, composer
   packages-dev). Scriptet returnerer non-zero, hvis valideringen fejler.
6. Commit resultatet:

       git add sbom/<tag>
       git commit -m "chore(sbom): release SBOM for YYYY.MINOR.PATCH"

## Fremtid (ikke i v1)

- GitHub Actions på `push: tags` der installerer pinnet syft og kalder
  `sbom/generate.py` (scriptet er allerede ren CLI).
- Sårbarhedsscanning + triage pr. release (vulnerability-management).
- `GC2_REF` build-arg i docker/Dockerfile for reproducerbart image↔tag.
```

- [ ] **Step 2: Opdatér `sbom/readme.md`**

Erstat indholdet så det beskriver både baseline og den nye per-tag-proces (bevar henvisningen til baseline-mappen):

```markdown
# GC2 SBOM

## Baseline (historisk)

`2026-09-07-7477faaba479/` er den første kildekode-SBOM (Syft 1.51.1,
CycloneDX JSON 1.7, commit `7477faaba479…`). Immutabel; beskriver ikke senere
commits. Se mappens filer for evidens, coverage og validering.

## Per-release SBOM

Fra og med release-SBOM-proceduren får hvert git-tag sin egen mappe
`sbom/<tag>/` med kilde-SBOM, image-SBOM (ved digest), GIS-native-supplement,
`release.json`-manifest, coverage, validering, CSV og checksums.

Generér med `sbom/generate.py`. Fuld procedure: `docs/RELEASE-SBOM.md`.
Genererings-config og validator lever i `sbom/config/`; de fra-kilde-byggede
GIS-libs vedligeholdes i `sbom/gis-native-components.yaml`.
```

- [ ] **Step 3: Kør hele unittest-suiten en sidste gang**

Run: `python3 -m unittest discover -s sbom/tests -t . -v`
Expected: PASS (alle tests grønne).

- [ ] **Step 4: (Valgfri) manuel integrations-smoke — kræver syft + et lokalt bygget image**

Run (kun hvis syft-binær og `mapcentia/gc2:php8.4-N` er tilgængelige lokalt):
```bash
python3 sbom/generate.py --syft /sti/til/syft \
  --tag 2026.6.6 --image mapcentia/gc2:php8.4-2 \
  --output /tmp/sbom-smoke/2026.6.6
python3 -c "import json;print(json.load(open('/tmp/sbom-smoke/2026.6.6/validation.json'))['all_passed'])"
```
Expected: filsæt genereret; `True`. (Springes over hvis værktøjer mangler — enhedstesten i Task 7 dækker orkestreringen med fakes.)

- [ ] **Step 5: Commit**

```bash
git add docs/RELEASE-SBOM.md sbom/readme.md
git commit -m "docs(sbom): release SBOM procedure and updated sbom readme"
```

---

## Self-Review

**1. Spec coverage:**
- §2 Mål (SBOM pr. tag, kilde+image+GIS, release.json, selvindeholdt config, CycloneDX 1.7, procedure-doc, non-zero exit) → Task 1–8. ✓
- §4.1 Mappelayout → Task 7 (`generate.py` skriver hele sættet). ✓
- §4.2 Script + argumenter + trin → Task 7. ✓
- §4.3 GIS-supplement → Task 2. ✓
- §4.4 release.json-kobling → Task 4 (builder) + Task 7 (udfyldning). ✓
- §4.5 Selvindeholdt config → Task 1. ✓
- §4.6 Procedure-doc → Task 8. ✓
- §5 Dataflow, §6 Fejlhåndtering (exist_ok=False, digest-krav, validering hård) → Task 7 + Task 6. ✓
- §7 Test → unittests i alle kodetasks + smoke i Task 8. ✓
- §8 Berørte filer → dækket; `.gitignore` i Task 1. ✓
- §9 Kendte begrænsninger → coverage.json noterer huller (Task 3/7); GIS branch-pins noteret i YAML (Task 2). ✓

**2. Placeholder-scan:** Ingen TBD/TODO; al kode er konkret. `/sti/til/syft` i doc'et er en brugerangivet runtime-parameter, ikke en plan-placeholder. ✓

**3. Type-konsistens:** Funktionsnavne og signaturer i `sbom_release` (`resolve_commit`, `git_archive_snapshot`, `gis_components_to_cyclonedx`, `parse_composer_lock`, `parse_npm_lock`, `sbom_name_versions`, `compute_lockfile_coverage`, `cdx_component_rows`, `write_csv`, `build_release_manifest`, `sha256_file`) matcher deres brug i `generate.py` og i testene. Filnavns-konstanter (`SOURCE_CDX` osv.) er konsistente med validering, coverage, CSV og release.json. ✓
