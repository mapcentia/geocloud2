import json
import pathlib
import sys
import tempfile
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

    def test_parse_npm_lock_v2v3_packages_map_skips_root_extracts_node_modules_names(self):
        """Test v2/v3 packages-map path: root entry ("") skipped, node_modules/ names extracted."""
        import tempfile
        import json
        # Synthetic v2/v3 lockfile with packages map
        v2v3_lock = {
            "lockfileVersion": 3,
            "packages": {
                "": {"name": "root", "version": "1.0.0"},  # root entry, should be skipped
                "node_modules/left-pad": {"version": "1.3.0"},
                "node_modules/@scope/pkg": {"version": "2.0.0"},
            }
        }
        p = pathlib.Path(tempfile.mkstemp(suffix=".json")[1])
        p.write_text(json.dumps(v2v3_lock))
        try:
            pairs = sr.parse_npm_lock(p)
            # Convert to set for order-independent comparison
            pairs_set = set(pairs)
            # Verify root entry is skipped (should not have ("root", ...))
            root_entries = [nv for nv in pairs_set if nv[0] == "root"]
            self.assertEqual(len(root_entries), 0, "root entry should be skipped")
            # Verify expected packages are present
            self.assertIn(("left-pad", "1.3.0"), pairs_set)
            self.assertIn(("@scope/pkg", "2.0.0"), pairs_set)
            # Verify only expected entries (no extra entries from root)
            self.assertEqual(len(pairs_set), 2)
        finally:
            p.unlink()


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


if __name__ == "__main__":
    unittest.main()
