# SBOM i release-proceduren — design

- **Dato:** 2026-09-07
- **Status:** Godkendt design, klar til implementeringsplan
- **Emne:** Gør generering, validering og arkivering af en versionsbundet SBOM til en fast del af GC2-releaseproceduren (CRA Bilag I, del II, pkt. 1)
- **Kilder:** Centia-wiki `[[cra-readiness-plan]]`, `[[gc2-vidi-sbom-baseline]]`, `[[software-bill-of-materials]]`, `[[2026-09-07-cra-regulation]]`

## 1. Formål og baggrund

CRA (Bilag I, del II, pkt. 1) kræver en maskinlæsbar SBOM knyttet til det **faktisk leverede artefakt**, med mindst direkte afhængigheder, som del af producentens sårbarhedshåndtering. Readiness-planens færdigkriterium for de første 30 dage er, at *"én release kan spores fra kode til leverance, berørte afhængigheder og godkendelse"*, og planen kræver eksplicit: *"Gør generering, validering og arkivering til en del af releaseprocessen. Gem commit, image-/artefaktdigest, platform, SBOM, scan og beslutning samlet."*

I dag findes kun en **kildekode-baseline** (7. september 2026, `sbom/2026-09-07-7477faaba479/`), genereret manuelt fra et wiki-script. Den dækker npm- og Composer-manifester, men **ikke** OS-, native-, GDAL-, MapServer-, QGIS- eller PHP-runtime-komponenter — dvs. netop det, der først findes i det byggede Docker-image. Der er ingen CI i repoet.

Dette design gør SBOM'en til en gentagelig del af hver udgivelse (= ét git-tag) og lukker OS/native-hullet ved også at scanne det leverede runtime-image.

## 2. Mål og ikke-mål

### Mål

- Én selvstændig, valideret SBOM-pakke pr. git-tag, committet i repoet under `sbom/<tag>/`.
- Dækning af **kilde** (git-tag-snapshot) + **leveret runtime-image** `mapcentia/gc2:php8.4-N` (ved digest) + **fra-kilde-byggede GIS-libs**.
- Et `release.json`-manifest der binder git-tag ↔ commit ↔ image-ref+digest ↔ `php8.4-N` ↔ tool-versioner ↔ filhashes sammen.
- Selvindeholdt generering: script, syft-config og valideringsskemaer lever i repoet.
- CycloneDX JSON 1.7 som primærformat (samme som baseline), Syft JSON som detaljeret evidens, CSV som læsbar komponentliste.
- En skrevet, trin-for-trin release-procedure.
- Ikke-nul exit ved fejlet skemavalidering, så en ugyldig SBOM ikke committes ubemærket.

### Ikke-mål (v1, bevidst YAGNI)

- **Sårbarhedsscanning / triage.** Behandles som et separat trin i `[[vulnerability-management]]`; SBOM'en er inputtet, ikke vurderingen.
- **Auto-download af syft.** Scriptet tager en pinnet binær som argument (bevarer `tool_sha256`-provenance).
- **Ændring af `docker/Dockerfile`.** `GC2_REF`-tag-pinning (reproducerbart image↔tag) kan lægges på additivt i en senere iteration.
- **GitHub Actions-workflow.** Sømmen forberedes (scriptet er ren CLI), men CI bygges ikke nu.
- **Vidi, SDK, CLI, MCP-server.** Kun GC2 i denne iteration; mønstret udvides senere jf. readiness-planen.

## 3. Kontekst i repoet

- **Release = git-tag.** Eksisterende tags følger `YYYY.MINOR.PATCH` (fx `2026.6.6`).
- **Docker-images fra `docker/Dockerfile`** (versioneres uafhængigt med `php8.4-N`):
  - `mapcentia/mapserver:php8.4-N` (target `mapserver`) — base med OS/GDAL/MapServer/QGIS/PHP.
  - `mapcentia/gc2:php8.4-N` (target `cron`) — **den leverede app-runtime**; dette er det eneste image v1 scanner.
- **Kobling i dag:** Dockerfilen kloner GC2 fra `--branch master` *inde i* imaget. Git-tag og image-tag er derfor løst koblet; `release.json` **registrerer** koblingen via digest (den garanteres ikke reproducerbart i v1 — se §9).
- **Fra-kilde GIS-libs** (bygges i Dockerfilen, fanges ikke som pakker af syft): GDAL 3.9.2, MapServer branch `rsvg_fix`, MapCache `branch-1-12`, SWIG (master), ogr2postgis (`gui`), libecwj2 3.3 / ECW 5.3, QGIS-server (apt).
- **Tooling til stede:** `node` v24, `python3`, `docker`, `jq`. **Syft er IKKE installeret** — leveres som pinnet binær ved kørsel.

## 4. Arkitektur og komponenter

### 4.1 Mappelayout: `sbom/<tag>/`

Baseline-mappen `sbom/2026-09-07-7477faaba479/` bevares **urørt** som historik. Hver ny udgivelse får sin egen mappe navngivet efter tagget:

```
sbom/
  config/                        # selvindeholdt genererings-config (§4.5)
    syft-config.yaml
    validate-sboms.cjs
    validation-schemas/
  gis-native-components.yaml     # vedligeholdt kilde for fra-kilde GIS-libs (§4.3)
  generate.py                    # generatorscript (§4.2)
  readme.md                      # opdateres til at pege på både baseline og release-mapper
  2026-09-07-7477faaba479/       # eksisterende baseline (uændret)
  <tag>/                         # fx 2026.6.7/
    gc2-source.cdx.json          # kilde-SBOM, CycloneDX 1.7
    gc2-source.syft.json         # kilde-SBOM, Syft JSON (evidens)
    gc2-image.cdx.json           # image-SBOM, CycloneDX 1.7
    gc2-image.syft.json          # image-SBOM, Syft JSON (evidens)
    gis-native.cdx.json          # fra-kilde GIS-komponenter, CycloneDX 1.7
    components.csv               # samlet læsbar komponentliste
    release.json                 # manifest / kobling (§4.4)
    provenance.json              # tool, config, tidsstempel, git-archive-hash
    coverage.json                # hvilke lockfiles/manifester blev dækket + kendte huller
    validation.json              # skemavalideringsresultat pr. SBOM-fil
    sha256sums.txt               # checksums over alle filer i mappen
    readme.md                    # pr.-release pointer/oversigt
```

### 4.2 Generatorscript: `sbom/generate.py`

Port af wiki-scriptet `generate-sboms.py`, generaliseret fra to hardcodede targets til ét tag + ét image.

**Argumenter:**

| Flag | Påkrævet | Default | Beskrivelse |
| --- | --- | --- | --- |
| `--syft <sti>` | ja | — | Sti til pinnet syft-binær; `tool_sha256` optages i provenance |
| `--tag <git-tag>` | nej | nuværende exact tag (`git describe --tags --exact-match`) | Git-tag der udgives |
| `--image <ref>` | ja | — | Fx `mapcentia/gc2:php8.4-2` |
| `--output <sti>` | nej | `sbom/<tag>` | Outputmappe; **oprettes med `exist_ok=False`** — eksisterende resultater overskrives aldrig |

**Trin (rækkefølge):**

1. **Validér tag:** bekræft at `--tag` peger på en eksisterende, annoteret/let tag og resolv commit-SHA (`git rev-list -n1 <tag>`).
2. **Kilde-SBOM:** `git archive --format=tar <tag>` → udpak til temp → `syft -c sbom/config/syft-config.yaml scan dir:<snapshot> --source-name gc2 --source-version <tag>` → skriv `gc2-source.cdx.json` + `gc2-source.syft.json`. Optag hvilke manifester/lockfiles blev fundet (som baseline `inputs`-blokken).
3. **Resolv image-digest:** `docker inspect --format '{{index .RepoDigests 0}}' <image>` (pull først hvis nødvendigt). Fejl hvis intet digest kan resolves — vi vil ikke scanne et flydende tag uden digest.
4. **Image-SBOM:** `syft scan <image>@sha256:… --source-name gc2-image --source-version <php8.4-N>` → `gc2-image.cdx.json` + `gc2-image.syft.json`.
5. **GIS-supplement:** læs `sbom/gis-native-components.yaml` → emittér CycloneDX 1.7 `gis-native.cdx.json` (§4.3).
6. **Validering:** kør `node sbom/config/validate-sboms.cjs` mod alle tre `*.cdx.json`; skriv `validation.json`. **Non-zero exit hvis nogen fejler.**
7. **Manifest & metadata:** skriv `release.json` (§4.4), `provenance.json`, `coverage.json`, `components.csv`, `readme.md`.
8. **Checksums:** skriv `sha256sums.txt` over alle genererede filer.

**Bevaret fra baseline-scriptet:** scan af immutable git-archive-snapshot (aldrig `git checkout`/`npm install`/`composer install`), miljø renset for `SYFT_*`, provenance med tool- og config-sha256, `working_tree_dirty`-registrering.

### 4.3 GIS-supplement: `sbom/gis-native-components.yaml`

Vedligeholdt, review'et "single source of truth" for de komponenter Dockerfilen bygger fra kilde, og som syft ikke fanger som pakker. Formatet er minimalt og oversættes 1:1 til CycloneDX-komponenter:

```yaml
# Pins skal holdes i sync med docker/Dockerfile ved hver image-version.
components:
  - name: gdal
    version: "3.9.2"
    purl: "pkg:generic/gdal@3.9.2"
    source: "http://download.osgeo.org/gdal/3.9.2/gdal392.zip"
    notes: "Bygget med ECW 3.3 + LibKML"
  - name: mapserver
    version: "8.0-rsvg_fix"
    purl: "pkg:github/mapcentia/mapserver@rsvg_fix"
    notes: "Branch-klon; commit ikke pinnet i Dockerfile"
  - name: mapcache
    version: "branch-1-12"
    purl: "pkg:github/mapserver/mapcache@branch-1-12"
  - name: swig
    version: "master"
    purl: "pkg:github/swig/swig@master"
  - name: ogr2postgis
    version: "gui"
    purl: "pkg:github/mapcentia/ogr2postgis@gui"
  - name: libecwj2
    version: "3.3"
    notes: "ECW/JPEG2000 SDK; ECW_VERSION build-arg styrer 3.3 vs 5.3"
  - name: qgis-server
    version: "apt-ltr"
    notes: "Fra qgis.org debian-ltr; faktisk version aflæses i imaget"
```

Hvor en pin er en branch og ikke et commit, registreres det som en kendt begrænsning i `coverage.json` (jf. §9). Ideelt beriges disse med den faktiske version aflæst i det byggede image, hvor det er muligt.

### 4.4 `release.json` — koblingen

Manifestet der gør udgivelsen sporbar fra kode til leverance. Skema (illustrativt):

```json
{
  "product": "gc2",
  "git_tag": "2026.6.7",
  "git_commit": "7477faaba479bbc1859f8529a0cf62a351c5da5c",
  "image": {
    "ref": "mapcentia/gc2:php8.4-2",
    "digest": "sha256:…",
    "platform": "linux/amd64",
    "image_version": "php8.4-2"
  },
  "generated_at": "2026-09-07T12:00:00Z",
  "tool": { "name": "syft", "version": "1.51.1", "sha256": "…" },
  "config_sha256": "…",
  "artifacts": {
    "gc2-source.cdx.json": { "sha256": "…", "format": "CycloneDX 1.7", "components": 2385 },
    "gc2-image.cdx.json":  { "sha256": "…", "format": "CycloneDX 1.7", "components": 0 },
    "gis-native.cdx.json": { "sha256": "…", "format": "CycloneDX 1.7", "components": 7 }
  },
  "validation": { "all_passed": true },
  "scope": {
    "source": "tracked git-archive of tag",
    "image": "scanned by digest",
    "gis_native": "hand-maintained supplement from Dockerfile pins",
    "vulnerability_scan": "not performed in this step"
  }
}
```

`git_commit`-koblingen er registrerende, ikke bevisende: image'et klonede `master`, ikke tagget (se §9). `image.digest` er den autoritative identifikation af det leverede artefakt.

### 4.5 Selvindeholdt config: `sbom/config/`

Kopieres byte-identisk fra wiki-eksporten `exports/sbom/2026-09-07/`:

- `syft-config.yaml` — samme cataloger-udvalg og `include-dev-dependencies: true`.
- `validate-sboms.cjs` — CycloneDX-skemavalidering via Node.
- `validation-schemas/` — `bom-1.7.schema.json` m.fl.

Dette opfylder baseline-readmes krav: *"Keep generation configuration and automation in this repository when implementing the release workflow."*

### 4.6 Procedure-dokument: `docs/RELEASE-SBOM.md`

Trin-for-trin release-procedure:

1. Byg og push `mapcentia/gc2:php8.4-N` (eksisterende flow).
2. Hent den pinnede syft-binær (version + sha256 dokumenteret i doc'et).
3. Opret git-tag `YYYY.MINOR.PATCH`.
4. Kør `python3 sbom/generate.py --syft <sti> --tag <tag> --image mapcentia/gc2:php8.4-N`.
5. Verificér `validation.all_passed == true` og gennemgå `coverage.json` for nye huller.
6. Commit `sbom/<tag>/` og push tagget.

Doc'et noterer også, hvordan et fremtidigt `.github/workflows/release-sbom.yml` (trigget på `push: tags`) blot skal installere den pinnede syft og kalde samme script.

## 5. Dataflow

```
git tag <tag>                        image build+push (mapcentia/gc2:php8.4-N)
     │                                          │
     ▼                                          ▼
git archive <tag> ──► syft dir:  ──► gc2-source.*     docker inspect ─► sha256 digest
                                        │                                  │
gis-native-components.yaml ─► gis-native.cdx.json      syft <img>@sha256 ─► gc2-image.*
                                        │                                  │
                                        └──────────┬───────────────────────┘
                                                   ▼
                                     validate (node) ─► validation.json
                                                   ▼
                              release.json + provenance + coverage + csv + sha256sums
                                                   ▼
                                        git add sbom/<tag>/ ; git push --tags
```

## 6. Fejlhåndtering og validering

- **Ukendt/uklart tag:** scriptet fejler før scanning.
- **Manglende image-digest:** scriptet fejler; vi scanner aldrig et flydende tag uden pinnet digest.
- **Eksisterende outputmappe:** `mkdir(exist_ok=False)` — resultater overskrives aldrig (immutabilitet).
- **Fejlet skemavalidering:** non-zero exit; `validation.json` gemmes med detaljer, men mappen bør ikke committes før fejl er rettet.
- **Beskidt arbejdstræ ved kilde-scan:** registreres i provenance (`working_tree_dirty`) — kilde-SBOM'en bruger altid git-archive af tagget, ikke arbejdstræet.

## 7. Test og verifikation

- **Tørkørsel mod nyeste eksisterende tag** (fx `2026.6.6`) + et lokalt bygget `mapcentia/gc2`-image: bekræft at alle filer produceres, at CycloneDX-validering passerer, og at `release.json` binder tag/commit/digest korrekt.
- **Regressions-sammenligning mod baseline:** kilde-scan af baseline-commit skal give komponentantal i samme størrelsesorden som `2026-09-07-7477faaba479/` (≈2.385).
- **Idempotens/immutabilitet:** anden kørsel mod samme `--output` skal fejle rent.
- **Coverage-kontrol:** `coverage.json` skal eksplicit liste kendte huller (Composer packages-dev, branch-pinnede GIS-libs, licensmetadata).

## 8. Berørte filer og leverancer

**Nye:**
- `sbom/generate.py`
- `sbom/gis-native-components.yaml`
- `sbom/config/syft-config.yaml`, `sbom/config/validate-sboms.cjs`, `sbom/config/validation-schemas/*`
- `docs/RELEASE-SBOM.md`

**Ændret:**
- `sbom/readme.md` (peg på både baseline og release-mapper; beskriv proceduren)
- Evt. `.gitignore` (sørg for at `sbom/<tag>/` **ikke** ignoreres)

**Uændret:** `docker/Dockerfile`, `sbom/2026-09-07-7477faaba479/` (historik).

## 9. Åbne spørgsmål / kendte begrænsninger

- **Image↔tag reproducerbarhed.** Imaget klonede `master`, ikke tagget. `release.json` registrerer koblingen via digest, men beviser den ikke. Additiv hærdning: `GC2_REF` build-arg i Dockerfilen så app-koden bygges fra tagget. Anbefales som næste iteration.
- **Branch-pinnede GIS-libs.** MapServer/MapCache/SWIG/ogr2postgis klones på branch, ikke commit — versionerne er ikke entydige. Berig fra det byggede image hvor muligt.
- **Composer packages-dev.** Fanges ikke fuldt af nuværende syft-config (baseline: 60 dev-pakker manglede). Image-scanningen dækker de faktisk installerede runtime-pakker; dev-hullet noteres i coverage.
- **Licensmetadata.** Mangler for et stort antal poster (baseline). Berigelse er en separat opgave.
- **Sårbarhedsscanning.** Bevidst udenfor v1; SBOM'en er inputtet til `[[vulnerability-management]]`.

## 10. Fremtidige udvidelser

1. `GC2_REF`-tag-pinning i Dockerfilen (reproducerbart build).
2. GitHub Actions-workflow på `push: tags` der kalder `generate.py`.
3. Sårbarhedsscanning af SBOM/image + triage-record pr. release.
4. Udvidelse af mønstret til Vidi, SDK, CLI og MCP-server.
5. Vedhæftning af SBOM-pakken til GitHub Release som assets (supplement til repo-commit).
