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
        if "scan" in cmd:  # syft scan (syft is passed as a temp path, so match by verb)
            for i, a in enumerate(cmd):
                if a == "-o":
                    target = pathlib.Path(cmd[i + 1].split("=", 1)[1])
                    target.write_text(json.dumps(MIN_CDX))
        elif pathlib.Path(cmd[0]).name == "node":
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
