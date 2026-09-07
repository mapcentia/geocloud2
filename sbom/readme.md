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
