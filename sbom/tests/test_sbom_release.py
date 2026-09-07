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
