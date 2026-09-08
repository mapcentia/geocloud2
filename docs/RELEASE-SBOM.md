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

1. Opret git-tagget: `git tag YYYY.MINOR.PATCH && git push --tags`.
2. Byg og push runtime-imaget `mapcentia/gc2:php8.4-N` pinnet til tagget:

       docker build -f docker/Dockerfile --target cron \
         --build-arg GC2_REF=YYYY.MINOR.PATCH \
         -t mapcentia/gc2:php8.4-N docker/

   `GC2_REF` binder imagets GC2-kildekode til tagget (default er `master`),
   så `release.json`'s image↔tag-kobling bliver reproducerbart *bevist* og
   ikke kun registreret via digest.
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
