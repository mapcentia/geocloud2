import pathlib
import sys
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))
import triage as tr  # noqa: E402

# Minimal CycloneDX-with-vulnerabilities document exercising each rating kind.
BOM = {
    "components": [{"type": "library"}, {"type": "library"}],
    "vulnerabilities": [
        {  # KEV + high + high EPSS + a fix (unaffected range) available
            "id": "CVE-KEV-1",
            "ratings": [
                {"score": 8.8, "severity": "high", "method": "CVSSv31"},
                {"source": {"name": "FIRST"}, "score": 0.99, "method": "other"},
                {"source": {"name": "CISA KEV Catalog"}, "score": 1, "method": "other"},
            ],
            "affects": [{
                "ref": "pkg:pypi/pillow@9.4.0?package-id=ab816015c6ab3bba",
                "versions": [
                    {"version": "9.4.0", "status": "affected"},
                    {"range": "vers:pypi/>=10.0.1", "status": "unaffected"},
                ],
            }],
        },
        {  # critical but low EPSS (below threshold), no fix
            "id": "CVE-CRIT-2",
            "ratings": [
                {"score": 9.1, "severity": "critical", "method": "CVSSv31"},
                {"source": {"name": "FIRST"}, "score": 0.10, "method": "other"},
            ],
            "affects": [{"ref": "pkg:deb/libfoo@1.0"}],
        },
        {  # medium
            "id": "CVE-MED-3",
            "ratings": [{"score": 5.0, "severity": "medium", "method": "CVSSv31"}],
            "affects": [{"ref": "pkg:deb/bar@2"}],
        },
        {  # no severity rating -> "unknown"
            "id": "CVE-NONE-4",
            "ratings": [{"source": {"name": "FIRST"}, "score": 0.2, "method": "other"}],
            "affects": [{"ref": "pkg:deb/baz@3"}],
        },
        {  # high + high EPSS, no fix, not KEV
            "id": "CVE-HIGH-5",
            "ratings": [
                {"score": 7.5, "severity": "high", "method": "CVSSv31"},
                {"source": {"name": "FIRST"}, "score": 0.80, "method": "other"},
            ],
            "affects": [{"ref": "pkg:generic/node@14.21.3"}],
        },
    ],
}


class FieldExtractionTest(unittest.TestCase):
    def test_primary_severity(self):
        self.assertEqual(tr.primary_severity(BOM["vulnerabilities"][0]), "high")
        self.assertEqual(tr.primary_severity(BOM["vulnerabilities"][3]), "unknown")

    def test_scores(self):
        v = BOM["vulnerabilities"][0]
        self.assertEqual(tr.cvss_score(v), 8.8)
        self.assertEqual(tr.epss_score(v), 0.99)
        self.assertIsNone(tr.epss_score(BOM["vulnerabilities"][2]))

    def test_is_kev(self):
        self.assertTrue(tr.is_kev(BOM["vulnerabilities"][0]))
        self.assertFalse(tr.is_kev(BOM["vulnerabilities"][4]))

    def test_affected_ref_strips_query(self):
        self.assertEqual(tr.affected_ref(BOM["vulnerabilities"][0]), "pkg:pypi/pillow@9.4.0")

    def test_fix_available(self):
        self.assertTrue(tr.fix_available(BOM["vulnerabilities"][0]))
        self.assertFalse(tr.fix_available(BOM["vulnerabilities"][4]))


class AggregationTest(unittest.TestCase):
    def test_severity_breakdown(self):
        self.assertEqual(
            tr.severity_breakdown(BOM),
            {"critical": 1, "high": 2, "medium": 1, "unknown": 1},
        )

    def test_kev_findings(self):
        kev = tr.kev_findings(BOM)
        self.assertEqual([v["id"] for v in kev], ["CVE-KEV-1"])

    def test_shortlist_filters_and_sorts(self):
        rows = tr.shortlist(BOM, min_epss=0.5)
        # Only high/critical with EPSS >= 0.5, sorted by EPSS desc.
        self.assertEqual([r["id"] for r in rows], ["CVE-KEV-1", "CVE-HIGH-5"])
        self.assertTrue(rows[0]["kev"])
        self.assertTrue(rows[0]["fix"])
        self.assertFalse(rows[1]["kev"])
        self.assertFalse(rows[1]["fix"])

    def test_shortlist_threshold_excludes_low_epss_critical(self):
        ids = [r["id"] for r in tr.shortlist(BOM, min_epss=0.5)]
        self.assertNotIn("CVE-CRIT-2", ids)  # critical but EPSS 0.10


class ReportTest(unittest.TestCase):
    def test_format_report_smoke(self):
        text = tr.format_report(BOM, min_epss=0.5, source="test.json")
        self.assertIn("CISA KEV", text)
        self.assertIn("CVE-KEV-1", text)
        self.assertIn("critical 1", text)


if __name__ == "__main__":
    unittest.main()
