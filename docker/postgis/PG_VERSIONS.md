# PostgreSQL-versioner og Debian-images

Hvilket Debian-image (`DEBIAN_CODENAME`) der skal bruges til en given PostgreSQL- og
PostGIS-version, når postgis-imaget bygges.

Listen bygger på pakkelisterne i PGDG-repoerne (`apt.postgresql.org` og
`apt-archive.postgresql.org`) pr. 2026-10-27. Kun PG11 og PG17 er bygget og testet.
De andre kombinationer er udledt af pakkerne.

## Anbefalet Debian-image pr. PG-version

| PG | PostGIS 2 (2.5.5) | PostGIS 3 | Bemærkning |
|---|---|---|---|
| 11 | `bullseye` | `bookworm` (3.3.4) | Testet. PostGIS og pgRouting til PG11 kan ikke installeres på trixie. |
| 12 | `bullseye` | `trixie` (3.6.4) | PostGIS 2.5 kun fra PGDG-arkivet |
| 13 | `bullseye` | `trixie` (3.6.4) | PostGIS 2.5 kun fra PGDG-arkivet |
| 14 | – | `trixie` (3.6.4) | |
| 15 | – | `trixie` (3.6.4) | |
| 16 | – | `trixie` (3.6.4) | |
| 17 | – | `trixie` (3.6.4) | |
| 18 | – | `trixie` (3.6.4) | Bullseye har ikke PostGIS eller pgRouting til 18 |

## Hvad hver Debian-version giver med PostGIS 3

| | bullseye | bookworm | trixie |
|---|---|---|---|
| PG 11 | 3.3.4 | 3.3.4 | ✗ (kræver libgdal33, som trixie ikke har) |
| PG 12 | 3.5.2 | 3.6.4 | 3.6.4 |
| PG 13–17 | 3.5.2 | 3.6.4 | 3.6.4 |
| PG 18 | ✗ | 3.6.4 | 3.6.4 |
| pgRouting | 3.5–3.8 | 3.5–4.0.1 | 3.6.3–4.0.1 |
| PHP 8.4, MS ODBC 17/18 | ✓ | ✓ | ✓ |
| Debian-support | Udløbet (archive.debian.org) | Aktiv | Aktiv |

ogr_fdw 1.1.9 findes til alle kombinationerne.

## Tommelfingerregel

Brug den nyeste Debian, der har alle pakkerne i det aktive repo:

- trixie til PG12 og nyere med PostGIS 3
- bookworm til PG11 med PostGIS 3
- bullseye når du skal have PostGIS 2

PostGIS 2.5 findes kun til PG11–13 og kun på bullseye. Bullseye får ikke længere
sikkerhedsopdateringer.

## Byg

```bash
# PG17 + PostGIS 3 på trixie (standard)
docker build -t postgis:pg17-postgis3 .

# PG11 + PostGIS 2.5
docker build --build-arg POSTGRES_VERSION=11 --build-arg POSTGIS_VERSION=2 --build-arg DEBIAN_CODENAME=bullseye -t postgis:pg11-postgis2 .

# PG11 + PostGIS 3
docker build --build-arg POSTGRES_VERSION=11 --build-arg POSTGIS_VERSION=3 --build-arg DEBIAN_CODENAME=bookworm -t postgis:pg11-postgis3 .
```

## Tjek i Dockerfilen

`Dockerfile` tjekker kombinationen af `POSTGIS_VERSION`, `POSTGRES_VERSION` og
`DEBIAN_CODENAME` mod tabellerne ovenfor og stopper buildet med det samme, hvis
kombinationen ikke kan installeres. Ændrer du tabellerne, skal tjekket også rettes.

Testet: PG11 med PostGIS 2 (bullseye), PG11 med PostGIS 3 (bookworm) og PG17 med
PostGIS 3 (trixie).
