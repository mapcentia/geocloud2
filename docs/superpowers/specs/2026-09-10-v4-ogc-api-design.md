# OGC API Features og OGC API Maps under v4 — design

- **Dato:** 2026-09-10
- **Status:** Godkendt design (grundlag for implementeringsplan)
- **Emne:** Udstil GC2-lag som OGC API Features (Part 1 Core + Part 2 CRS, læsning) og OGC API Maps (Part 1 Core) under `api/v4`, med geofence-regler, versionering og workflow håndhævet af de eksisterende motorer.
- **Referencer:** OGC API Features Part 1 (17-069r4), Part 2 CRS (18-058r1); OGC API Maps Part 1 (20-058); OGC API Common Part 1 (19-072); `docs/superpowers/specs/2026-09-07-stac-catalog-design.md` §6 (niveau 3); `docs/superpowers/specs/2026-08-07-v4-ows-api-design.md`; `docs/superpowers/specs/2026-05-07-wfs-v4-worker-safe-design.md`.

## 1. Konklusion i kort form

Feature API'et bygges oven på den in-process WFS-motor (`app/wfs`), som allerede håndhæver geofence-regler (`TableWalkerRule`, service `wfst`), versionering (`gc2_version_end_date IS NULL` eller time slice) og workflow i `GetFeature::buildTableState`. Motoren mangler to ting: offset og GeoJSON-output. Begge tilføjes i motoren via en `FeatureWriterInterface` og en `GeoJsonWriter`, så GML-omvejen i `Feature.php` undgås.

Map API'et bygges oven på OWS-proxyen (`app/ows`), som allerede evaluerer regler (service `ows`) og versioneringsfilter og patcher dem ind i mapfilen. OGC API Maps-parametre oversættes til WMS 1.3.0 GetMap.

Ingen databasemigration. Tre nye controllere, to nye writer-/service-pakker, to små udtræk fra `Ows.php`/`Wfs.php` til delte klasser.

## 2. Beslutninger truffet

| Spørgsmål | Valg | Begrundelse |
|---|---|---|
| Motor bag Feature API | WFS-motor + GeoJSON-writer | Regler, versionering og workflow følger gratis med; én sandhed for hvad et lag eksponerer. |
| Skrivning (Part 4) | Ikke i denne leverance | CRUD findes i `/api/v4/schemas/{s}/tables/{t}/features`. Part 4 kan senere genbruge samme WFS-T-vej. |
| URL-layout | Én landing page pr. database, collection-id = `schema.table` | Matcher STAC-designet og WMS-lagnavne; OGC-klienter (QGIS, pygeoapi-klienter) forventer én rod. |
| Map-omfang | Core via WMS-proxy inkl. `datetime` | Genbrug af `Proxy`/`MapfilePatcher`; time slice er et ekstra WHERE-filter. |
| Delt identitetslogik | Udtrækkes til `app\inc\PublicIdentity` | `Ows.php` og `Wfs.php` har i dag hver sin kopi; en tredje kopi er ikke acceptabel. Eksisterende Ows/Wfs-tests dækker refaktoreringen. |

## 3. Mål og ikke-mål

### Mål

- Landing page, conformance og collections for én database, synlige efter samme auth-regler som WMS GetCapabilities.
- `/collections/{id}/items` som streamet GeoJSON FeatureCollection med `bbox`, `bbox-crs`, `crs`, `limit`, `offset`, `datetime`, korrekt `numberMatched`/`numberReturned` og `next`/`prev`-links.
- `/collections/{id}/items/{fid}` som GeoJSON Feature.
- `/collections/{id}/map` og `/map?collections=` som PNG/JPEG via MapServer/QGIS Server med regler og versionering.
- Geofence `deny` giver 403, `limit` filtrerer rækker (features) og pixels (map).
- Versionerede lag viser nuværende version som default og en historisk version med `datetime`.
- Workflow-filtrering for sub-users som i WFS.
- OpenAPI via `OA\`-attributter, Codeception api-tests, unit-tests af writer og CRS-oversættelse.

### Ikke-mål

- Part 3 Filtering/CQL2 og `queryables`. Attributfiltre kan tilføjes senere via `Request::filter`.
- Part 4 Create/Replace/Update/Delete.
- OGC API Tiles (MapCache-proxyen dækker WMTS/XYZ).
- OGC API Styles og `/collections/{id}/styles/{styleId}/map`.
- HTML-output (`f=html`). Kun JSON/GeoJSON og billeder.
- `sortby`, `properties`-projektion, `skipGeometry`.
- Ændring af den legacy `/wfs/`-bootstrap eller MapServer-mapfiler.

## 4. Kontekst i repoet

### 4.1 WFS-motoren (`app/wfs`)

- `Request` er en readonly value object med `typeNames`, `featureIds`, `bbox`, `srs`, `maxFeatures`, `timeSlice`, `filter`, `outputFormat`. Ingen offset. `normalizeOutputFormat` kollapser ukendte formater til GML2.
- `GetFeature::buildTableState` bygger SELECT (geometri som `ST_AsGml`), FROM med versionerings-join ved time slice, WHERE fra `featureIds`/`bbox`/`filter`, versioneringsfilter og workflow-filter. `countFeatures` returnerer `maxFeatures` direkte, når det er sat. `streamFeatures` kører `TableWalkerRule` (service `wfst`, request `select`) og streamer via cursor til `GmlWriter::writeFeature`.
- `Server::dispatch` validerer protokol, tjekker `enableows` (privat `checkLayerEnabled`) og kalder handleren. `HandlerInterface::handle(Request, GmlWriter)`.
- `GmlWriter` ejer al XML-output; `suppressFlush` bruges af `Feature.php`, som fanger output i en buffer og konverterer GML til GeoJSON med XML_Unserializer. Den vej er for tung til hele collections.

### 4.2 OWS-proxyen (`app/ows`) og `Ows.php`

- `Ows::buildContext` (bearer/Basic/anonym/trusted), `authorizeLayers` (per-lag auth med 60 s cache for token/Basic, aldrig for anonym) og `applyRules` (geofence service `ows` + `gc2_version_end_date IS NULL`) er private metoder.
- `app\ows\Request::parse('GET', $query, $queryString, null)` er offentlig og bygger en request ud fra et query-array.
- `Proxy::resolve` vælger MapServer/QGIS Server/ekstern WMS og patcher en tmp-mapfile med `MapfilePatcher` ved filtre. Mapfilen er pr. `{database}_{schema}_wms.map`, så ét GetMap kan kun tegne lag fra ét skema. `MAXSIZE 16384`.
- `Wfs::buildContext` er en næsten identisk kopi af `Ows::buildContext`.

### 4.3 Routing

- v4-controllere opsamles automatisk fra `app/api/v4/controllers/*.php` via `#[Controller(route, scope)]`.
- `Route2::matchSignature` afviser requests med flere segmenter end ruten, og literal-segmenter skal matche. Derfor kan `…/[p1]/[p2]`, `…/collections/{collection}/items/[feature]` og `…/collections/{collection}/map` sameksistere uden overlap.
- `public/index.php` giver `api/v4/ows` en separat, højere rate limit-bucket. Map-trafik under `api/v4/ogc` skal i samme bucket.

### 4.4 Metadata og auth

- `settings.getColumns(...)` med `enableows=true` er lagkilden for både WFS GetCapabilities og mapfilen. `Layer::getAll` bruger `authentication`-where: anonym ser kun `None`/`Write`.
- `Authorization::check(relName, transaction, isAuth, subUser, userGroup, rels)` håndhæver sub-user-privilegier for `Read/write`/`Write`-lag.
- `Layer::getEstExtent($_key_, $srs)` giver estimeret extent i valgfri SRS. `getGeometryColumns` er cachet under `<db>*_geometryColumns`, som `Table::clearCacheOnSchemaChanges()` buster.
- `App::$param['advertisedSrs']` (default `EPSG:4326 EPSG:3857 EPSG:3044 EPSG:25832`) er WMS'ens SRS-liste.

## 5. Design

### 5.1 Endpoints og controllere

Tre controllere, alle `Scope::PUBLIC`, alle med `AcceptableMethods(['GET','HEAD','OPTIONS'])`:

| Controller | Route | Håndterer |
|---|---|---|
| `app/api/v4/controllers/Ogc.php` | `api/v4/ogc/database/{database}/[p1]/[p2]` | `/` (landing), `/conformance`, `/collections`, `/collections/{id}`, `/map` (multi-collection) |
| `app/api/v4/controllers/OgcFeatures.php` | `api/v4/ogc/database/{database}/collections/{collection}/items/[feature]` | items-liste og enkelt item |
| `app/api/v4/controllers/OgcMaps.php` | `api/v4/ogc/database/{database}/collections/{collection}/map` | map for én collection |

`Ogc.php` dispatcher på `p1`: tom → landing, `conformance`, `collections` (med `p2` = id), `map`. Ukendt `p1` → 404 JSON.

Collection-id er `schema.table`. Alle links er absolutte (`Util::host()`), som i OWS-capabilities.

### 5.2 Landing page, conformance, collections

**Landing page**

```json
{
  "title": "<database> OGC API",
  "description": "OGC API Features and Maps for database <database>",
  "links": [
    {"rel": "self", "type": "application/json", "href": ".../ogc/database/<db>"},
    {"rel": "service-desc", "type": "application/vnd.oai.openapi+json;version=3.0", "href": ".../api/v4/swagger.json"},
    {"rel": "service-doc", "type": "text/html", "href": ".../swagger-ui/"},
    {"rel": "conformance", "type": "application/json", "href": ".../conformance"},
    {"rel": "data", "type": "application/json", "href": ".../collections"}
  ]
}
```

`service-desc`/`service-doc` peger på den eksisterende OpenAPI-generering; de nye controllere får `OA\`-attributter, så stierne optræder der.

**Conformance**

```
http://www.opengis.net/spec/ogcapi-common-1/1.0/conf/core
http://www.opengis.net/spec/ogcapi-common-1/1.0/conf/landing-page
http://www.opengis.net/spec/ogcapi-common-1/1.0/conf/json
http://www.opengis.net/spec/ogcapi-common-2/1.0/conf/collections
http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/core
http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/oas30
http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/geojson
http://www.opengis.net/spec/ogcapi-features-2/1.0/conf/crs
http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/core
http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/spatial-subsetting
http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/crs
http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/datetime
http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/png
http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/jpeg
http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/collections-selection
```

**Collections** (`app\ogc\Collections`)

- Kilde: `settings.getColumns('<where>','<where>')` hvor `<where>` er `enableows=true AND (<auth-where>)`. `<auth-where>` er som i `Layer::getAll`: anonym → `authentication IN ('None','Write')`; med identitet → alle. Sub-users filtreres derefter pr. lag: `Authorization::check` kastes → laget udelades. Superuser (parent) ser alle.
- `?limit` (default 100, max 1000) og `?offset` på listen med `next`-link, så store databaser svarer hurtigt.
- Hver collection:

```json
{
  "id": "geodanmark.vejmidte",
  "title": "Vejmidte",
  "description": "…",
  "itemType": "feature",
  "extent": {"spatial": {"bbox": [[8.0, 54.5, 15.2, 57.8]], "crs": "http://www.opengis.net/def/crs/OGC/1.3/CRS84"}},
  "crs": ["http://www.opengis.net/def/crs/OGC/1.3/CRS84", "http://www.opengis.net/def/crs/EPSG/0/4326", "http://www.opengis.net/def/crs/EPSG/0/3857", "http://www.opengis.net/def/crs/EPSG/0/25832"],
  "storageCrs": "http://www.opengis.net/def/crs/EPSG/0/25832",
  "links": [
    {"rel": "self", "type": "application/json", "href": ".../collections/geodanmark.vejmidte"},
    {"rel": "items", "type": "application/geo+json", "href": ".../collections/geodanmark.vejmidte/items"},
    {"rel": "http://www.opengis.net/def/rel/ogc/1.0/map", "type": "image/png", "href": ".../collections/geodanmark.vejmidte/map"}
  ]
}
```

- `crs`-listen er CRS84, EPSG:4326, lagets `srid` og `advertisedSrs`, dedupliceret. `storageCrs` = lagets `srid`.
- Rasterlag (`type = RASTER`): `itemType` udelades, intet `items`-link, kun `map`-link, `extent` fra `settings.viewer` extents eller verden.
- Versionerede lag får `extent.temporal.interval = [[min(gc2_version_start_date), null]]` (cachet).
- Extent: `Layer::getEstExtent(_key_, 4326)`; ved `NULL` (ingen statistik, view, tom tabel) én `ST_Extent` med `statement_timeout` 5 s og fallback til verden. Cachet pr. lag under `<db>_<schema>.<table>_ogcExtent` med tag `<db>_geometryColumns`, TTL 24 timer.
- Ukendt collection → 404. En collection, der findes, men som kalderen ikke må læse: 401 med `WWW-Authenticate: Basic` for anonyme (ingen credentials), 403 `INSUFFICIENT_PRIVILEGES` for Bearer/Basic-identiteter uden privilegium. Listen `/collections` udelader fortsat skjulte lag. (Besluttet 2026-09-11: eksistensen af `Read/write`-lag må gerne være synlig i statuskoden.)

### 5.3 Identitet: `app\inc\PublicIdentity`

Udtrækkes fra `Ows::buildContext`/`Wfs::buildContext`:

```php
final readonly class PublicIdentity
{
    public function __construct(
        public Connection $connection,
        public string     $database,
        public ?string    $schema,
        public string     $user,        // JWT uid, Basic-bruger eller databasenavnet (anonym)
        public ?array     $userGroup,
        public bool       $parentUser,
        public bool       $trusted,
        public ?string    $bearer,      // rå token, til auth-cache-nøgler
        public bool       $anonymous,   // hverken bearer eller Basic
    ) {}

    /** @throws GC2Exception 401 ved ugyldig token eller token for anden database */
    public static function resolve(string $database, ?string $schema = null): self;

    /** Geofence-identitet: brugeren selv, eller "*" for anonym (Ows::applyRules-semantik). */
    public function geofenceUser(): string;

    public function wfsContext(string $schema, ?int $srs = null): \app\wfs\Context;
    public function owsContext(string $schema): \app\ows\Context;
}
```

`Ows.php` og `Wfs.php` omskrives til at bruge `resolve()` + `owsContext()`/`wfsContext()`. Fejltyper bevares: Ows kaster `ServiceException`, Wfs `OwsException`, ved at wrappe `GC2Exception` fra `resolve()` i deres egne `catch`-blokke.

### 5.4 Per-lag auth: `app\ows\LayerGate`

Udtrækkes fra `Ows::authorizeLayers` (inkl. 60 s allow-cache, aldrig for anonyme):

```php
final class LayerGate
{
    public function __construct(private PublicIdentity $id) {}
    /** @param list<string> $rels "schema.table"; kaster GC2Exception 401/403, eller challenger Basic */
    public function authorizeRead(array $rels): void;
}
```

Semantik som i dag: `Read/write` kræver Bearer (`Authorization::check`) eller Basic (`BasicAuth::authenticate`, som challenger 401); anonym tilladt under det niveau; Basic-header uden `Read/write` verificeres alligevel. OgcFeatures og OgcMaps kalder `authorizeRead([$rel])` før noget streames.

### 5.5 Regler og versionering for maps: `app\ows\RuleFilters`

Udtrækkes fra `Ows::applyRules`:

```php
final class RuleFilters
{
    public function __construct(private PublicIdentity $id, private Model $model) {}
    /**
     * @param list<string> $layers "schema.table"
     * @param array<string,list<string>> $clientFilters fra FILTERS-param (Ows) eller tom (OGC)
     * @return array<string,list<string>> filtre pr. lag
     * @throws ServiceException 'DENY'
     */
    public function forLayers(array $layers, array $clientFilters = [], ?string $timeSlice = null): array;
}
```

- Geofence med service `ows`, request `select`, `iprange` `*`; `deny` kaster, `limit` tilføjer `(filter)`.
- Versionering: uden `timeSlice` → `gc2_version_end_date IS NULL`; med → `gc2_version_start_date <= '<ts>' AND (gc2_version_end_date > '<ts>' OR gc2_version_end_date IS NULL)`. `<ts>` valideres som ISO 8601 og parameteriseres som quoted literal via `pg_escape_literal`-ækvivalent (`Model::quote`), så det ikke kan injicere.

### 5.6 Feature API: ændringer i WFS-motoren

**`app\wfs\Request`**

- Nyt felt `public ?int $startIndex = null` (sidst i constructoren, default null, så `fromGet`/`fromXmlPost` er uændrede).
- `normalizeOutputFormat` accepterer `GEOJSON`.

**`app\wfs\output\FeatureWriterInterface`**

Metoderne `GetFeature` bruger: `writeXmlProlog()`, `writeFeatureCollectionOpen(Request, Context, ?int numberMatched)`, `writeFeatureCollectionClose()`, `writeFeatureMembersOpen(string version)`, `writeFeatureMembersClose(string version)`, `writeTag(...)`, `write(string)`, `writeFeature(array row, string table, Table tableObj, Request, Context)`, `flush()`, plus `wantsBoundedBy(): bool`. `GmlWriter` implementerer interfacet (`wantsBoundedBy` = true).

**`app\wfs\handlers\GetFeature`**

- `handle(Request $req, FeatureWriterInterface $writer)` (udvidet parametertype, lovligt under `HandlerInterface`). `writeXmlProlog` kaldes på writeren uanset; GeoJSON-writeren gør det til en no-op.
- `buildTableState`: ved `outputFormat === 'GEOJSON'` erstattes geometri-udtrykket med `ST_AsGeoJSON(ST_Transform(<geom>, <srs>), 9)`; ved `req->srsName === 'http://www.opengis.net/def/crs/EPSG/0/4326'` wrappes i `ST_FlipCoordinates(...)` (aksefølge lat/lon jf. Part 2). Ingen `ST_CollectionExtract` for GeoJSON (GeoJSON kan bære alle typer).
- `countFeatures`: for GeoJSON køres COUNT altid (genvejen `maxFeatures !== null` gælder kun GML), cappet af `FEATURE_LIMIT`.
- `writeBoundedBy` springes over når `!$writer->wantsBoundedBy()`.
- `streamFeatures`: `LIMIT <maxFeatures> OFFSET <startIndex>` når `startIndex` er sat.

**`app\wfs\Server`**

- `checkLayerEnabled` gøres offentlig som `assertLayersEnabled(Request $req): void`. OgcFeatures kalder den og derefter `new GetFeature($ctx)->handle($req, $writer)` direkte.

**`app\wfs\output\GeoJsonWriter`**

- Constructor: `(string $selfHref, array $links, string $crsUri, bool $single = false)`.
- Streaming som `GmlWriter`: `write()` echo'er, `flush()` flusher; ingen buffering-tilstand nødvendig.
- `writeFeatureCollectionOpen`: skriver `{"type":"FeatureCollection","numberMatched":N,"timeStamp":"…Z","features":[`. Ved `single` skrives intet (feature-objektet er selve svaret).
- `writeFeature`: bygger `{"type":"Feature","id":<fid>,"geometry":<raw json|null>,"properties":{…}}` med komma-separator; `properties` udelader geometrikolonnen og `fid`; værdier typekonverteres ud fra `tableObj->metaData[<col>]['type']`: `int*`/`numeric`/`float*`/`double` → tal, `bool` → bool, `json`/`jsonb` → `json_decode`, `bytea` udelades, øvrige som streng. Geometrien indsættes verbatim (allerede JSON fra PostGIS). `id` er numerisk hvis pkey-typen er heltal, ellers streng.
- `writeFeatureCollectionClose`: skriver `],"numberReturned":M,"links":[…]}`. `numberReturned` tælles i writeren. `next`-link medtages kun hvis `offset + numberReturned < numberMatched`; `prev` hvis `offset > 0`.
- `writeFeatureMembersOpen/Close`, `writeTag`, `writeXmlProlog`: no-ops. `wantsBoundedBy` = false.
- Ved `single`: `writeFeature` skriver feature-objektet med `links` (`self`, `collection`) tilføjet.

### 5.7 Feature API: controlleren `OgcFeatures`

Flow for `GET /collections/{id}/items`:

1. `PublicIdentity::resolve($database, $schema)`; `Collections::get($id)` → 404 hvis ukendt, 401/403 hvis kendt men ikke tilladt; 400 hvis raster (`itemType` mangler).
2. Parametervalidering:
   - `limit` int 1..10000, default 10.
   - `offset` int ≥ 0, default 0.
   - `bbox` 4 tal (6 tal accepteres, z ignoreres); `bbox-crs` CRS-URI, default CRS84.
   - `crs` CRS-URI fra collectionens `crs`-liste, default CRS84; ellers 400.
   - `datetime` ISO 8601 instant (intervaller afvises med 400 i v1); ignoreres for ikke-versionerede lag.
   - `f` = `json` eller udeladt; andet → 400. `Accept: application/geo+json` eller `application/json` eller `*/*`.
   - Ukendte parametre ignoreres ikke: Part 1 kræver 400 for ukendte query-parametre. Listen af kendte er ovenstående plus `f`.
3. `LayerGate::authorizeRead(["$schema.$table"])`.
4. Byg `app\wfs\Request(operation: 'GETFEATURE', version: '1.1.0', service: 'WFS', outputFormat: 'GEOJSON', typeNames: [$table], bbox: [minx,miny,maxx,maxy,'<bbox-crs som EPSG:n>'], srsName: <crs-URI>, srs: <epsg af crs>, maxFeatures: limit, timeSlice: datetime|null, startIndex: offset, …)`. `bbox` i CRS84 sendes som `EPSG:4326` med lon/lat-orden (`WfsFilter::getAxisOrder('EPSG:4326')` = longitude), i EPSG:4326-URI som `urn:ogc:def:crs:EPSG::4326` (latitude).
5. `Server::assertLayersEnabled($req)`; `StreamedResponse` med `Content-Type: application/geo+json`, `Content-Crs: <crs-URI>`; i callback: `set_time_limit(0)`, `Util::disableOb()`, `new GetFeature($ctx)->handle($req, $writer)`.
6. Fejl før første byte → JSON-fejl med status (se 5.9). `OwsException` mappes som i `Feature::mapOwsException`; `DENY for 'select'` fra `TableWalkerRule` → 403.

Flow for `GET /collections/{id}/items/{fid}`: som ovenfor med `featureIds: ["$table.$fid"]`, `maxFeatures: 1`, `single: true`; ingen `bbox`/`limit`/`offset`. Tom collection → 404 (writeren tæller 0 og controlleren kan ikke ændre status efter stream, så `single` kører **ikke** streamet: output fanges i en buffer som `Feature::captureDispatch` med `suppressFlush`, og 404 sendes ved 0 features). Quote-tegn i `fid` → 400 som i `Feature.php`.

`Context` bygges med `tokenAuth: !$id->anonymous`, så `Transaction`-handlerens Basic-challenge ikke er relevant (kun GetFeature bruges), og `parentUser` = `$id->parentUser`, så workflow-filtret virker for sub-users.

### 5.8 Map API: controlleren `OgcMaps` og `app\ogc\MapRenderer`

`MapRenderer::render(PublicIdentity $id, string $schema, list<string> $tables, array $query): StreamedResponse` bruges af både `OgcMaps` (én collection) og `Ogc` (`/map?collections=`).

Parametre og oversættelse til WMS 1.3.0 GetMap:

| OGC API Maps | Validering | WMS |
|---|---|---|
| `bbox` | 4 tal, default lagets extent | `BBOX` — transformeres fra `bbox-crs` til `crs` via PostGIS `ST_Transform` af envelope-hjørner, hvis de er forskellige; ved `crs` = EPSG:4326 ombyttes til lat/lon (WMS 1.3.0-aksefølge); CRS84 sendes som `CRS=CRS:84` |
| `bbox-crs` | CRS-URI, default CRS84 | — |
| `crs` | CRS-URI i collectionens liste, default CRS84 | `CRS` (`CRS:84` eller `EPSG:n`) |
| `width`, `height` | int 1..16384; hvis kun én sættes, beregnes den anden fra bbox-forholdet; default 1024 bred | `WIDTH`, `HEIGHT` |
| `f` | `png` (default) eller `jpeg` | `FORMAT=image/png` / `image/jpeg` |
| `transparent` | bool, default true for png, false for jpeg | `TRANSPARENT` |
| `bgcolor` | `0xRRGGBB` eller `RRGGBB` | `BGCOLOR` |
| `datetime` | ISO 8601 instant | time slice i `RuleFilters::forLayers` |
| `collections` (kun `/map`) | liste af `schema.table`, alle i samme skema, ellers 400 | `LAYERS` i givet rækkefølge |

`STYLES=` sendes tomt (`wms_allow_getmap_without_styles`). `LAYERS` = `schema.table`-navne som i dag.

Flow: `Collections::get` for hver → 404/401/403; `LayerGate::authorizeRead`; `RuleFilters::forLayers($layers, [], $datetime)`; `OwsRequest::parse('GET', $wmsQuery, http_build_query($wmsQuery), null)`; `Proxy::resolve` + `Proxy::run` inde i `StreamedResponse` med `Util::disableOb()`. Fejl før stream → JSON-fejl (ikke OGC ServiceException-XML). Tmp-mapfile slettes i `finally` som i `Ows::stream`.

`public/index.php`: rate-limit-bucket `ows` udvides til `Input::getPath()->part(3) === 'ogc'` når stien ender på `map`, så map-trafik ikke spiser den ordinære v4-kvote.

### 5.9 Fejlformat

JSON i v4's eksisterende fejlformat (`{"success":false,"message":…,"code":…,"errorCode":…}`, `Content-Type: application/json`), så OGC-endpoints deler fejlhåndtering med resten af v4. Fejl før stream kastes som `GC2Exception`; fejl inde i stream-callback'et skrives af `app\ogc\Problem::render()`.

Status: 400 (parameterfejl, ukendt parameter, ugyldig crs), 401 (Basic-challenge med `WWW-Authenticate: Basic`, ugyldig token), 403 (geofence deny, manglende privilegium), 404 (collection/feature), 406 (ikke-understøttet `Accept`), 500 (uventet; intern besked logges, generisk tekst returneres). Efter første streamede byte kan status ikke ændres; fejl logges.

### 5.10 OpenAPI

Alle tre controllere får `OA\Get`-attributter med parametre og svar (tag `Ogc`). `Ogc.php` dokumenterer landing, conformance, collections, collection og multi-map som separate `OA\Get`-stier på samme metode via flere attributter.

## 6. Datastrøm (items)

```
GET /api/v4/ogc/database/db/collections/s.t/items?bbox=…&limit=50&offset=100&crs=…
  → PublicIdentity::resolve         (JWT / Basic / anonym / trusted)
  → Collections::find('s.t')        (enableows + auth-synlighed → 404)
  → validate params                 (400 ved fejl)
  → LayerGate::authorizeRead        (401/403)
  → wfs\Request(GEOJSON, bbox, limit, startIndex, timeSlice, srs)
  → Server::assertLayersEnabled
  → StreamedResponse(application/geo+json, Content-Crs)
      → GetFeature::handle(req, GeoJsonWriter)
          buildTableState: ST_AsGeoJSON, versionering, workflow
          countFeatures:   COUNT med TableWalkerRule('wfst')  → numberMatched
          streamFeatures:  TableWalkerRule('wfst') + cursor + LIMIT/OFFSET
          GeoJsonWriter:   {"type":"FeatureCollection", … "features":[…], "numberReturned", "links"}
```

## 7. Ydelse

- COUNT for `numberMatched` koster en ekstra forespørgsel pr. items-kald. Den regel-omskrevne indre forespørgsel wrappes i `SELECT COUNT(*) FROM (... LIMIT 1 000 000)`, så `numberMatched` mætter ved 1 000 000 og regler stadig gælder for tællingen. Måles med `EXPLAIN` på `mydb` før/efter jf. projektets "measure first"-princip; hvis COUNT er dominerende på store tabeller, tilføjes en `?skipCount` senere (ikke i v1).
- Extent-cache som beskrevet i 5.2. `/collections` på databaser med mange hundrede lag måles.
- `OFFSET` på dybe sider er O(n); accepteres i v1 (samme som pygeoapi/ldproxy default). Keyset-paging kan tilføjes senere.

## 8. Risici

- **Aksefølge.** CRS84 vs. EPSG:4326 i både bbox-input, GeoJSON-output og WMS 1.3.0 BBOX. Dækkes af unit-tests på oversættelsen og api-tests med et punkt ved kendt koordinat.
- **Interface-udvidelse i motoren.** `GetFeature::handle` skifter parametertype; `Server::dispatch` og `HandlerInterface` beholder `GmlWriter`. Eksisterende Wfs-tests (`WfsNoTokenApiCest`, `WfsInheritanceApiCest`) skal være grønne.
- **Refaktorering af `Ows.php`/`Wfs.php`.** `OwsApiCest`, `OwsNoTokenApiCest`, `WfsNoTokenApiCest` og `BasicAuthPrimaryApiCest` er regressionsnettet.
- **Type-konvertering i `GeoJsonWriter`.** PDO leverer strenge; metadata-typenavnene fra `Table::metaData` styrer konverteringen. Ukendte typer bliver strenge (aldrig fejl).
- **Sub-user-listning.** `Authorization::check` pr. lag ved `/collections` kan være dyrt med mange lag; `limit`/`offset` og `getColumns`-cachen begrænser det.

## 9. Test

**Codeception api** (`app/tests/api/OgcApiCest.php` med token, `app/tests/api/OgcNoTokenApiCest.php` anonym/Basic). Opsætning som `FeatureV4ApiCest`: bruger, skema, tabel `poi` (Point, 4326) med pkey og `enableows`, plus en versioneret tabel og en `Read/write`-tabel.

- Landing page og conformance: links og URI'er.
- `/collections`: anonym ser `Write`/`None`, ikke `Read/write`; med token ses alle; `limit`/`offset`/`next`.
- `/collections/{id}`: `extent`, `crs`, `storageCrs`, links; 404 for ukendt.
- `/items`: default limit; `bbox` inkluderer/ekskluderer kendt punkt; `limit`+`offset` og `next`/`prev`-links; `numberMatched`/`numberReturned`; `crs`=EPSG:25832 giver transformerede koordinater og `Content-Crs`; `crs`=EPSG:4326-URI giver lat/lon; ukendt parameter → 400; ugyldig `crs` → 400.
- `/items/{fid}`: feature med `id` og `links`; 404 for ukendt; `'` i id → 400.
- Geofence: regel `deny` for `*`/service `wfst` → 403 på items; regel `limit` med filter → kun matchende features; regel `deny` for `ows` → 403 på map.
- Versionering: opret, opdater via `/features` (WFS-T), items viser nuværende; `datetime` før opdateringen viser gammel version.
- Workflow: sub-user uden rolle ser kun `gc2_status = 3`.
- `Read/write`-lag anonymt → 401 med `WWW-Authenticate`; med Basic → 200.
- Map: 200, `Content-Type: image/png`, PNG-signatur, forventet størrelse fra `width`/`height`; `f=jpeg`; `/map?collections=` med to lag; to skemaer → 400; `datetime` giver billede (ingen fejl); geofence `limit` giver billede (patch-vejen).
- Regression: Ows/Wfs/Feature-suiterne uændret grønne.

**Unit** (`app/tests/unit`): `GeoJsonWriterTest` (typekonvertering, `id`-type, `numberReturned`, links), `CrsTest` (URI ↔ EPSG, aksefølge, bbox-oversættelse til WMS), `RuleFiltersTest` (time slice-filter, deny/limit), `Route2` match-tests for de tre nye ruter.

Kørsel i `docker-gc2core-1` jf. `reference_running_tests_docker`.

## 10. Filer

| Fil | Handling |
|---|---|
| `app/inc/PublicIdentity.php` | Ny |
| `app/ows/LayerGate.php`, `app/ows/RuleFilters.php` | Ny (udtræk fra `Ows.php`) |
| `app/api/v4/controllers/Ows.php`, `Wfs.php` | Brug `PublicIdentity`, `LayerGate`, `RuleFilters` |
| `app/wfs/Request.php` | `startIndex`, `GEOJSON` |
| `app/wfs/output/FeatureWriterInterface.php`, `GeoJsonWriter.php` | Ny |
| `app/wfs/output/GmlWriter.php` | Implementerer interfacet |
| `app/wfs/handlers/GetFeature.php` | GeoJSON-geometri, COUNT, OFFSET, interface |
| `app/wfs/Server.php` | `assertLayersEnabled` offentlig |
| `app/ogc/Crs.php`, `Collections.php`, `MapRenderer.php`, `Problem.php` | Ny |
| `app/api/v4/controllers/Ogc.php`, `OgcFeatures.php`, `OgcMaps.php` | Ny |
| `public/index.php` | Rate-limit-bucket for `ogc/…/map` |
| `app/tests/api/OgcApiCest.php`, `OgcNoTokenApiCest.php` | Ny |
| `app/tests/unit/GeoJsonWriterTest.php`, `CrsTest.php`, `RuleFiltersTest.php` | Ny |
| `CHANGELOG.md` | Note |

## 11. Implementeringsrækkefølge (skitse til plan)

1. `PublicIdentity` + `LayerGate` + `RuleFilters`; omskriv `Ows.php`/`Wfs.php`; Ows/Wfs-suiter grønne.
2. Motor: `FeatureWriterInterface`, `GmlWriter` implementerer, `Request::startIndex`, `GetFeature` GeoJSON/COUNT/OFFSET, `Server::assertLayersEnabled`; Wfs-suiter grønne.
3. `GeoJsonWriter` med unit-tests.
4. `app\ogc\Crs` med unit-tests.
5. `Collections` + `Ogc.php` (landing, conformance, collections); api-tests.
6. `OgcFeatures.php`; api-tests inkl. regler, versionering, workflow, CRS.
7. `MapRenderer` + `OgcMaps.php` + `/map` i `Ogc.php`; rate-limit; api-tests.
8. OpenAPI-attributter, swagger-verifikation, `EXPLAIN`-målinger, CHANGELOG.
