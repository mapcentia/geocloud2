# Release SBOM procedure (GC2)

Hver udgivelse (= ét git-tag) skal have en committet, valideret SBOM under
`sbom/<tag>/`, jf. CRA Bilag I, del II, pkt. 1. Design:
`docs/superpowers/specs/2026-09-07-release-sbom-design.md`.

## Engangsopsætning

1. Installér validator-afhængigheder: `cd sbom/config && npm ci && cd ../..`
2. Hent den pinnede syft-binær (samme major/minor som baseline, 1.51.x):
   download fra https://github.com/anchore/syft/releases og notér `sha256`.
   Binæren committes ikke; stien angives ved kørsel.
3. (Valgfrit, til sårbarhedsscan) hent den pinnede grype-binær fra
   https://github.com/anchore/grype/releases, og opdatér dens sårbarheds-DB
   før scan: `grype db update`. Binæren committes ikke; stien angives med
   `--grype`.

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
4. Generér SBOM'en (tilføj `--grype`, hvis du vil sårbarhedsscanne i samme kørsel):

       python3 sbom/generate.py --syft /sti/til/syft \
         --tag YYYY.MINOR.PATCH --image mapcentia/gc2:php8.4-N \
         --grype /sti/til/grype

   Med `--grype` scannes image-SBOM'en (`gc2-image.cdx.json`); resultatet
   skrives til `sbom/<tag>/vulns.cdx.json` (CycloneDX med `vulnerabilities`),
   og grype-version + DB-status optages i `release.json`.
5. Kontrollér `sbom/<tag>/validation.json` → `all_passed: true`, og gennemgå
   `coverage.json` for nye huller (fx branch-pinnede GIS-libs, composer
   packages-dev). Scriptet returnerer non-zero, hvis valideringen fejler.
6. **Triagér sårbarhedsfund** (hvis du kørte `--grype`): gennemgå
   `vulns.cdx.json`. Få en prioriteret oversigt (severity, CISA KEV,
   EPSS-shortlist med fix-status) med:

       python3 sbom/triage.py sbom/<tag>/vulns.cdx.json

   Et fund er en kandidat, ikke en dom — vurdér relevans og
   udnyttelighed pr. fund og registrér beslutning (rettelse, eller begrundet
   undtagelse med ejer/udløb) jf. `[[vulnerability-management]]`. Et automatisk
   severity-tal afgør ikke alene udnyttelighed. GIS-libs i `gis-native.cdx.json`
   matches ikke af feeds og skal følges manuelt hos upstream.
7. Commit resultatet:

       git add sbom/<tag>
       git commit -m "chore(sbom): release SBOM for YYYY.MINOR.PATCH"

## Fremtid

- GitHub Actions på `push: tags` der installerer pinnet syft/grype og kalder
  `sbom/generate.py` (scriptet er allerede ren CLI).
- Formaliseret triage-/VEX-register og advisory-flow oven på `vulns.cdx.json`
  (vulnerability-management).
