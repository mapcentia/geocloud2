#!/usr/bin/env python3
"""Triage helper for a release's vulns.cdx.json (grype CycloneDX output).

Prints a severity breakdown, the CISA KEV (actively-exploited) findings, and a
shortlist of critical/high findings with a high EPSS (exploitation-likelihood)
score. A finding is a candidate, not a verdict: see docs/RELEASE-SBOM.md and the
vulnerability-management process for triage/VEX. GIS-native components are not
matched by the feeds and must be tracked upstream manually.

Usage: python3 sbom/triage.py sbom/<tag>/vulns.cdx.json [--min-epss 0.5]
"""
from __future__ import annotations

import argparse
import json
from pathlib import Path

SEVERITY_ORDER = ["critical", "high", "medium", "low", "none", "unknown"]


def load_bom(path) -> dict:
    return json.loads(Path(path).read_text())


def _ratings(vuln: dict) -> list:
    return vuln.get("ratings") or []


def primary_severity(vuln: dict) -> str:
    for r in _ratings(vuln):
        if r.get("severity"):
            return r["severity"]
    return "unknown"


def cvss_score(vuln: dict):
    for r in _ratings(vuln):
        method = r.get("method")
        if isinstance(method, str) and "CVSS" in method:
            return r.get("score")
    return None


def epss_score(vuln: dict):
    for r in _ratings(vuln):
        if (r.get("source") or {}).get("name") == "FIRST":
            return r.get("score")
    return None


def is_kev(vuln: dict) -> bool:
    return any((r.get("source") or {}).get("name") == "CISA KEV Catalog"
               for r in _ratings(vuln))


def affected_ref(vuln: dict):
    for a in vuln.get("affects") or []:
        ref = a.get("ref")
        if ref:
            return ref.split("?", 1)[0]  # drop the ?package-id=... query
    return None


def fix_available(vuln: dict) -> bool:
    for a in vuln.get("affects") or []:
        for ver in a.get("versions") or []:
            if ver.get("status") == "unaffected":
                return True
    return False


def severity_breakdown(bom: dict) -> dict:
    counts: dict = {}
    for v in bom.get("vulnerabilities") or []:
        s = primary_severity(v)
        counts[s] = counts.get(s, 0) + 1
    return counts


def kev_findings(bom: dict) -> list:
    return [v for v in bom.get("vulnerabilities") or [] if is_kev(v)]


def shortlist(bom: dict, *, min_epss: float = 0.5,
              severities=("critical", "high")) -> list:
    rows = []
    for v in bom.get("vulnerabilities") or []:
        sev = primary_severity(v)
        epss = epss_score(v)
        if sev in severities and epss is not None and epss >= min_epss:
            rows.append({
                "id": v.get("id"),
                "severity": sev,
                "cvss": cvss_score(v),
                "epss": epss,
                "ref": affected_ref(v),
                "kev": is_kev(v),
                "fix": fix_available(v),
            })
    rows.sort(key=lambda r: r["epss"] if r["epss"] is not None else -1, reverse=True)
    return rows


def format_report(bom: dict, *, min_epss: float = 0.5, source: str = "") -> str:
    counts = severity_breakdown(bom)
    ncomp = len(bom.get("components") or [])
    nvuln = len(bom.get("vulnerabilities") or [])
    lines = []
    lines.append(f"Triage: {source}" if source else "Triage")
    lines.append(f"Components: {ncomp} | Vulnerabilities: {nvuln}")
    lines.append("")
    sev = "  ".join(f"{name} {counts[name]}" for name in SEVERITY_ORDER if name in counts)
    lines.append(f"Severity:  {sev}")
    lines.append("")

    kev = kev_findings(bom)
    lines.append(f"CISA KEV (actively exploited — fix first): {len(kev)}")
    for v in kev:
        lines.append(f"  {v.get('id')}  {affected_ref(v) or ''}")
    lines.append("")

    rows = shortlist(bom, min_epss=min_epss)
    lines.append(f"Shortlist (critical/high, EPSS >= {min_epss:g}), by EPSS desc: {len(rows)}")
    lines.append(f"  {'EPSS':<6} {'CVSS':<4} {'SEV':<8} {'FIX':<3} {'ID':<24} PACKAGE")
    for r in rows:
        epss = f"{r['epss']:.3f}" if r["epss"] is not None else "-"
        cvss = f"{r['cvss']}" if r["cvss"] is not None else "-"
        fix = "yes" if r["fix"] else "no"
        tag = "  [KEV]" if r["kev"] else ""
        lines.append(f"  {epss:<6} {cvss:<4} {r['severity']:<8} {fix:<3} "
                     f"{(r['id'] or ''):<24} {r['ref'] or ''}{tag}")
    lines.append("")
    lines.append("A finding is a candidate, not a verdict — triage relevance and "
                 "exploitability; see docs/RELEASE-SBOM.md.")
    return "\n".join(lines)


def main(argv=None) -> int:
    p = argparse.ArgumentParser(description=__doc__,
                                formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("vulns", type=Path, help="Path to vulns.cdx.json")
    p.add_argument("--min-epss", type=float, default=0.5,
                   help="EPSS threshold for the shortlist (default 0.5)")
    args = p.parse_args(argv)
    bom = load_bom(args.vulns)
    print(format_report(bom, min_epss=args.min_epss, source=str(args.vulns)))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
