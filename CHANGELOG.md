# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [CalVer](https://calver.org/).

## [2025.10.4] - 2025-23-10
### Fixed
- Add missing import for `DefaultTypeConverterFactory` in `app/models/Sql.php`.
- Update composer dependencies: upgrade `sad_spirit/pg_builder` to `^3.2` and `sad_spirit/pg_wrapper` to `^3.3`.

## [2025.10.3] - 2025-22-10
### Fixed
- Improved `Route.php` to handle empty responses gracefully and remove unused memory usage calculation in `Sql.php`. This fixes JSON artifacts in the non-JSON output formats.

## [2025.10.2] - 2025-21-10
### Added
- Added `convertDataUrlsToHttp` to `App.php` to convert data urls to http urls in SQL API. If set to true, bytea fields will be converted to http urls in SQL API in the form: 
`/api/v1/decodeimg/[database]/[relation]/[primary-field]/[id]`. Some caveats:
  - Relation is inferred from the SQL string and the first found relation is used for [relation]
  - To infer the mimetype, the first bytes from the bytea field are extracted with `substring`, 
    which still has a big IO impact on TOASTed tables.
  - The `convertDataUrlsToHttp` option is set to false by default.

## [2025.10.1] - 2025-14-10
### Changed
- By setting `convertDataUrlsToHttp` to true in `App.php`, bytea fields will be converted to http urls in SQL API in the form: 
`/api/v1/decodeimg/[database]/[relation]/[primary-field]/[id]`. Some caveats:
  - Relation is inferred from the SQL string and the first found relation is used for [relation]
  - To infer the mimetype, the first bytes from the bytea field are extracted with `substring`, 
    which still has a big IO impact on TOASTed tables.

## [2025.10.0] - 2025-14-10
### Security
- Enhance filter handling in `Wms.php` and `TableWalkerRule.php` to use parentheses for getting operator precedence right.

### Fixed
- Relation names used in Redis keys are now base64 encoded. This fixes a bug where relations with special characters could not be used.

## [2025.9.1] - 2025-16-9
### Fixed
- Rename `redirect_url` to `redirect_uri` in Signup/Signout API for consistency.

## [2025.9.0] - 2025-5-9
### Fixed
- Added checks to reset invalid angles (out of -360 to 360 range) to `0` for various angle fields in MapServer.
- Updated queries to use double-quoted qualified table names for improved SQL safety in MapServer

## [2025.8.0] - 2025-6-8
### Added
- `method` API for management of JSON-RPC methods, which can wraps SQL statement with optional instructions on how to interpret and format the data types.
- `call` API for executing JSON-RPC methods.

### Changed
- Docker images updated to PHP 8.4 (from 8.3). PHP version is now an argument in the Dockerfile. **All locked Composer packages can run on PHP 8.4. This is not the case for earlier versions of the code base. If using an earlier version, then you must build your Docker image with PHP 8.3**
- Throughout the code base, implicit marking parameters as null are changed to explicit (implicit is deprecated in PHP 8.4)
- All Symfony validators are updated, so no deprecated notices are thrown.
- composer.phar is no longer tracked in Git, but is added in the Docker images.

### Fixed
- Before a "shutdown" exception could be outputted after the actual payload from the APIs (e.g., a deprecated notice). The shutdown will only be output fatal errors, whereas warnings and notices will be outputted to the log. 

## [2025.7.1] - 2025-11-7
### Fixed
- Bug in Sql API.

## [2025.7.0] - 2025-8-7
### Added
- New v4 API `api/v4/sql/database/[database]` which can be used without a token.
  Database name must be provided in the URI.
  One sub-user can be set as `default` (using `api/v4/users`), which will be used in the new token-less SQL api.
  This enables unauthorized access to public datasets.
  With the default sub-user rules can be applied along with privileges.

### Changed
- In SQL and RPC APIs, `params` can now be a single object and not only a list of objects.
  Before a single object should be wrapped in an array: `[{...}]`.
  This applied only for insert, update and delete, while for select it is always a single object.

## [2025.6.3] - 2025-30-6
### Added
- Import API has a new `p_multi` property that allows promoting single geometries to multipart within the import process. Before single geometries were always promoted. 

## [2025.6.2] - 2025-16-6
### Added
- In Scheduler, it is now possible to prefix the url with `json:` to force JSON to CSV conversion.

### Changed
- Dashboard build is move from the MapServer Docker stage to the next.
 
## [2025.6.1] - 2025-11-6
### Added
- Caching to optimize the fetching of settings viewer data and reduce database queries.

### CHANGED
- Dashboard source code is now included and built in the 'MapServer' stage. So don't clone and build it manuel anymore.
- GC2 source code is now included in the 'MapServer' stage. So don't clone and it manuel anymore.

### Fixed
- Geometry decoding for empty values in SQL to ES mapping.
- Fixed Class wizard rendering issues when rendered before the Class wizard is visible. 

## [2025.6.0] - 2025-2-6
### Added
- Added logic to set the session.cookie_domain based on the `sessionDomain` parameter in the configuration. This ensures sharing of sessions in multi-domain setups.

### Changed
- CLass wizard is improved:
  - The class wizard will not overwrite external set symbol and label properties. This makes it easier to update classes with the wizard after manual adjustments in the class dialog.
  - All symbol and label settings are now available in the class wizard.
  - The class wizard is moved to a panel to the right instead of the modal. This makes it easier to use together with the class dialog. 
  - Replaced Ext.form.NumberField instances with Ext.ux.form.SpinnerField to enhance user experience through consistent increment/decrement functionality and better control over numeric inputs.
  - Added the MapServer `gap` option.
 
### Fixed
- Regression: 'Vitual' fields in layer Settings → SQL like `SELECT *, 'hello world' as virtual_field FROM test.test` now is written to MapFiles, so then can be used in classes and UTF-grids.

## [2025.5.0] - 2025-20-5
### Changed
- Change from separate Dockerfiles to a single with stages. Use the arg ECW_VERSION to control which ECW SDK version GDAL is compiled with (default to 3):
  - docker build --target=dev -t gc2_dev_ecw3 . --build-arg ECW_VERSION=3
  - docker build --target=dev -t gc2_dev_ecw5 . --build-arg ECW_VERSION=5
  - To build the mapserver base image:
  - docker build --target=mapserver -t mapserver .
- Update PostGIS healthcheck to use a basic SQL query. Replaced `pg_isready` with a simple `psql` query to improve healthcheck reliability. This ensures the database is responsive to actual queries, not just connection availability.

## [2025.4.0] - 2025-3-4
### Added
- MapServer GAP parameter is added to GC2 Admin GUI. GAP specifies the distance between SYMBOLs (center to center) for decorated lines and polygon fills in layer SIZEUNITS.
- New properties for api/v4/import payload to support more ogr2postgis features (see Swagger docs for more):
  - append
  - truncate
  - timestamp
  - x_possible_names
  - y_possible_names

## [2025.3.4] - 2025-28-3
### Changed
- It's now possible in GC2 Admin to copy properties from one layer to _multiple_ layers and select what properties to copy.
- It's now possible to set privileges on multiple layers at once.

## Fixed
- Better handling of user creation. Check the existence of both database and pg user when creating a GC2 superuser. So new it's possible to create a GC2 user for an already existing database and PG user. 

## [2025.3.3] - 2025-18-3
### Fixed
- Change from using `information_schema` to `pg_catalog` for querying of foreign constraints, which speeds up several APIs.
- Implemented caching for both table comments and column comments retrieval.
- Smaller performance fixes.
- Bug: If database table comment was null, no fallback to `settings` was implemented. It is now.

## [2025.3.2] - 2025-11-3
### Fixed
- Type bug: Array $reference could be converted to string and after used as an array. Now it's never converted, and the final output is also kept as an array or null.

## [2025.3.1] - 2025-6-3
### Fixed
- The Scheduler API was not starting jobs async, but hang until job was finished.
- Bug regarding check of a relation type in SQL API. If an alias was used like in a WITH statement, an error was thrown.

## [2025.3.0] - 2025-3-3
### Added
- Commit API will now write out templates for each Meta object. The template should be defined in 'app/conf/template.markdown'. The template could be a Jekyll template for Github pages.
- Layer Meta is now included in Meta v3 API.

### Changed
- Improvements in scheduler. Scheduler started jobs will now be logged and appear in Scheduler Status API. They will be tagged with 'Started by Scheduler'. When started from Admin they are tagged with 'Started from web-ui'.
- Description on column will now be set as SQL comments also. For backward compatibility descriptions, which are not set as database comments will still be shown in GC2 Admin. But SQL comments will have precedence in GC2 Admin.
- Description on layer will now be set as SQL comments also. For backward compatibility descriptions, which are not set as database comments will still be shown in GC2 Admin. But SQL comments will have precedence in GC2 Admin.
- Make sure that the new tables created by API v4 are registered in settings.geometry_columns_join.

### Fixed
- mapFieldType (ogr2ogr flag) for Binary fields are set as 'String' in SQL API.

## [2025.2.1] - 2025-12-2
### Added
- A new `v4/api/commit` API creates table JSON documents from a specific schema
  and commit/push them to a remote Git repo. The Git repo must exist beforehand
  A Meta query string can be submitted, and meta JSON documents will be committed also.

```http
POST http://127.0.0.1:8080/api/v4/commit HTTP/1.1
Content-Type: application/json
Accept: application/json
Authorization: Bearer abc123

{
  "schema": "my-schema",
  "message": "Update schema",
  "repo": "https://user:password@github.com/path/repo.git",
  "meta_query": "tag:my-tag"
}
```
  Git folder structure:
```text
  repo/   
  ├── my-schema/   
  │   └── tables/   
  │       ├── my-table1.json   
  │       ├── my-table2.json   
  │       └── my-table3.json   
  └── meta/   
      ├── schema.my-meta1.json    
      └── schema.my-meta2.json  
``` 

## [2025.2.0] - 2025-5-2
### Fixed
- Regression regarding using `full_type` instead `udt_name` as type property in Model::getMetaData. Now rolled back to `udt_name`.

## [2025.1.3] - 2025-30-1

## Security
- Fix authentication flow and improve password validation in Basic Auth

## [2025.1.2] - 2025-17-1

## Fixed
- Bug in WFS-t filters, caused by the refactoring of the filter parser.
- Refined regex in app\Model::getStarViewsFromStore for better matching of SQL fragments.

## [2025.1.1] - 2025-16-1

## Fixed
- The GC2 Admin Data grid could not display data if a field name had a period. This is due to ExtJS handling of periods in JSON as paths. A fix is implemented, so data can be viewed, buy fields with periods can't still be updated.
- Create views in GC2 Admin now works again. The SQL API was used, but it doesn't support CREATE VIEW any longer. A dedicated controller is created for creating (mat) views.

## [2025.1.0] - 2025-10-1

### Security
- Security bug in WFS-t/Geofence fixed. WFS username from URL was sent to the Geofence API even if the user was not authenticated (For queries, not transactions). Now username '*' is sent for non-authenticated users.

## [2024.12.3] - 2024-20-12

## Changed
- `format` can now again be used instead of the newer `output_format` in SQL API v4 (see 2024.12.1). This rollback is done to support clients who still are using `format`.

## [2024.12.2] - 2024-17-12

## Changed
- `FORCE_GPX_ROUTE=YES` is removed from `app/models/Sql.php`. Multilinestring geometry would cause an error in ogr2ogr. Now route or track is controlled by whether linestring is single or multi.

## [2024.12.1] - 2024-11-12

## Changed
- The http basic auth script is rewritten to a class. This makes it align with overall object-oriented approach.
- It's no longer required to add username in the ows/wfs URL and database name in the basic auth user. 
  - Before: `/ows|wfs/username@database/` and `username@database` in the basic auth prompt. 
  - Now: `/ows|wfs/database/` and just `username` in the basic auth prompt. 
  - For backward compatibility, the old method is still supported. The unnecessary parts will just be ignored. 
- The OWS exceptions are now handled by custom exception classes: ServiceException and OWSException.
- In SQL API requests the properties `format` and `geoformat` can now be changed to `output_format` and `geo_format`. The latter must be used in v4/sql.

## Fixed
- Refactor processArray in `app/models/Sql` to accept mixed type parameter. This was an issue with JSON fields with content with boolean properties.
- The parameter `postgisport` was not used in some instances. This is now fixed.

## [2024.12.0] - 2024-2-12

## Added
- It's now possible to upload files in GeoPackages format (.gpkg). The Admin still doesn't use org2postgis, so gpkg files with multiple layers will not get all layers imported.

## Fixed
- 'Workflow' now works. Please notice: Workflow and Rules don't work together. This will be fixed.

## [2024.11.2] - 2024-28-11

## Fixed
- OPTIONS request to API v4 now works without auth headers. Browsers don't send auth headers with preflight requests.
- In rule API iprange can now be either '*' or a cdri block. Before only the latter or empty was allowed. 

## [2024.11.1] - 2024-26-11

## Fixed
- Proper handling of JSON content in Feature API.

## [2024.11.0] - 2024-11-11

## Fixed

- Redis Cluster support for session was broken.
- Support for both WMS 1.1 and 1.3 in WMS layers.
- Disable transfer of metadata in newer versions of GDAL.
- Columns with some special chars were filtered out. It doesn't seem that this is necessary anymore and the code is removed.

## [2024.10.1] - 2024-21-10

## Fixed

- `app/controllers/upload/Processvector` class had a syntax error in the TRUNCATE statement.

## [2024.10.0] - 2024-7-10

## Changed

- SQL API will now return a file, when using `format=csv` (like earlier). To stream chunked csv data, use `format=ccsv`
- Increased the scan count from 10 to 50,000 for both Redis and RedisCluster drivers to improve performance.

## Fixed

- Missing geometry in SQL API `format=ndjson`.

## [2024.9.2] - 2024-19-9

### Fixed
- 
- Added `-mapFieldType Time=String` option to ogr2ogr command when exporting. ESRI Shape (maybe other formats?) doesn't have a time type, so Postgres time type is exported as string.
- In v2/feature API backslash, tabs and new lines are handled in JSON decode/encode and will no longer cause an error.

## [2024.9.1] - 2024-16-9

### Added

- Redis Cluster as session and cache backend. This requires that you specify at least one seed node. TLS is supported:
```php
[
    "sessionHandler" => [
        "type" => "redisCluster",
        "seeds" => ["redis-node-1:6379", "redis-node-2:6379", "redis-node-3:6379"],
        "tls" => true,
    ],
    "appCache" => [
        "type" => "redisCluster",
        "seeds" => ["redis-node-1:6379", "redis-node-2:6379", "redis-node-3:6379"],
        "ttl" => 3600,
        "tls" => true,
    ],
]
```

### Changed

- When using Redis (not cluster) as session and cache backend, you can now specify scheme in host, and `tls` is a valid scheme. You can also leave out the port number, and it will default to 6379: 
```php
[
    "sessionHandler" => [
        "type" => "redis",
        "host" => "tls://redis-node-1:6379",
    ],
    "appCache" => [
        "type" => "redisCluster",
        "host" => "tls://redis-node-1:6379",
        "ttl" => 3600,
    ],
]
```

### Fixed

- ST_CollectionExtract is now used in WFS-t to filter out EMPTY geometry parts. ST_AsGml for GML version 3, will crash the query, if geometry has EMPTY parts.

### Security

- Privileges will now be set on all layers belonging to af table/view. This will increase security.
- A Security weakness in Controller.php is fixed. If a table/view was not registered in settings.geometry_columns_view, it would be public open to queries. 
  This could be the case, if a table/view was created outside GC2 and afterward not being set up inside GC2.
  Now queries on all not-registered table/views will throw an exception.

## 2024.9.0 - 2024-4-9

### Fixed

- Reverted back to the old method of getting column metadata in the SQL API. The new one ran a SELECT statement twice,
  which could cause side effects.

## 2024.8.1 - 2024-30-8

### Fixed

- Bug in GPX export, which caused some JSON in the end of the GPX document.

## 2024.8.0 - 2024-28-8

### Changed

- Add JSON support and improve CSV handling in Scheduler.

## 2024.7.0 - 2024-31-7

### Changed

- Scheduler will now get HTTP Headers and detect if Content-type is application/zip and set the zip-file handler. This
  means that services returning zip-files and without .zip in the end of their URLs will be processed correct.

## 2024.6.2 - 2024-26-6

### Fixed

- Added `getAllKeys` to replace `getAllItems` in Phpfastcache. The latter will also retrieve items, but in the delete
  process where is no need for items, only keys. Items can fill the memory. Fallback is implemented in `inc/Cache.php`.
    - Phpfastcache is installed using Composer, so files overrides are done with Grunt task: `shell:hacks`

## 2024.6.1 - 2024-19-6

### Added

- IP range is now implemented in Geofence rules.

### Fixed

- WMS filtering now works for UTFgrids, meaning that layer filters in Vidi now works for underlying mouseover grid.

## 2024.6.0 - 2024-10-6

### Fixed

- Some bugs in the caching system.

## 2024.5.4 - 2024-29-5

### Fixed

- Minor bugs related to PHP 8.3.

## 2024.5.3 - 2024-21-5

### Fixed

- Boolean `False` is now written out as `0` in WFS-t. Before the value was set to NULL.
- Scheduler can now import CSV data. It'll run own tests for CSV and hint ogr2ogr because ogr2ogr can't detect CSV.
- Scheduler will now clear app cache for layer, so check for existence of extra fields works.

## 2024.5.2 - 2024-17-5

### Added

- WMS controller will now obtain the WMS source from layer and request the source directly instead of using MapServer.
  This speeds things up and WMS requests becomes easier to debug. If a request has multiple layers, MapServer will still
  be used, so merging from different WMS sources still works.

## 2024.5.1 - 2024-17-5

### Added

- A new script called `clean_scheduler_tables.php` has been added which removes old temporary tables created by the
  scheduler, it's intended to run as a cron job and are included in the Docker file.

## 2024.5.0 - 2024-2-5

### Fixed

- MapCache reload didn't work because `node` was not in the path.
- Better handling of user creation and databases. Exceptions from the database should no longer bubble up to the UI.
- Install script in `public/install` now works.
- `app/migration/run.php` now works.

### Changed

- `mapcache.conf` is moved from `/etc/apache2/sites-enabled` to `/app/wms/mapcache`.

### Added

- Health checks to gc2core and postgis docker images (the php-fpm watch script is removed)

## 2024.4.0 - 2024-30-4

### Fixed

- Opacity at layer level in MapFiles.
- `mapserver.conf` is moved from `/app/wms` to `/app/`.
- Caching of Settings.

## [2024.3.0] - 2024-24-3

### Changed

- Upgraded base image to Bookworm and PHP to 8.3
- A lot of new stuff

## [2023.10.1] - 2023-12-10

### Fixed

- Bugs regarding Feature API and the shift from WFS 1.0.0 to 1.1.0

## [2023.10.0] - 2023-10-10

### Fixed

- Upgrade to phpoffice/phpspreadsheet instead of the unmaintained phpoffice/phpexcel.

## [2023.9.2] - 2023-22-9

### Fixed

- api/v1/sql now points to v2, so it works.

## [2023.9.1] - 2023-21-9

### Fixed

- Table::getDependTree now works with PostgreSQL version > 12

## [2023.9.0] - 2023-12-9

### Added

- A new GC2 Meta option `line_highlight_style` is added. If set with a Leaflet style object an extra line is drawn below
  the ordinary vector line, which gives a border or highlight effect.

## [2023.8.1] - 2023-31-8

### Changed

- Removed EPSG:900913 and replaced with EPSG:3857

## [2023.8.0] - 2023-8-8

### Changed

- Multiple WMS filters now use AND instead of OR.
- JSON/JSONB fields are now embed in CDATA block in WFS-t, because values are treated as strings.

## [2023.7.0] - 2023-13-7

### Fixed

- Security bug in OWS.

## [2023.6.0] - 2023-2-6

### Fixed

- It's now possible to use single quotes in field properties JSON. The single quotes will be replaced with double quotes
  in the code but only after trying to parse the original JSON.

## [2023.5.0] - 2023-11-5

### Fixed

- Old bug in GeometryFactory::createGeometry method for MultiPoint. The MultiPoint::toGML method only returned the first
  geometri part.

## [2023.4.0] - 2023-24-4

### Fixed

- It's noew possible to select "double" type in combo-boxes for numberic selection.

## [2023.3.1] - 2023-22-3

### Added

- ESRI XML Workspace export added to v3 API. Check swagger docs.

## [2023.3.0] - 2023-20-3

### Changed

- New field `properties` in `setting.symbols`, which Vidi Symbols extension sets. Remember to run migrations in
  databases.

## [2023.2.0] - 2023-28-2

### Fixed

- WFS-t didn't parse EPSG format http://www.opengis.net/gml/srs/epsg.xml#xxxx the right way, which resulted in a SQL
  error.

## [2023.1.2] - 2023-31-1

### Added

- Added `returning` property in return object from the SQL API, when doing transactions. So now a RETURNING statement
  will return key/values from the statement.

### Fixed

- Re-creating Mapfiles with the v3 Admin API resulted in default extents being set in OWS. Now the schema extent is set
  in each MapFile.

## [2023.1.1] - 2023-10-1

### Changed

- Do not `updateLastmodified` in when running scheduler jobs because it will bust app cache.

## [2023.1.0] - 2023-10-1

### Changed

- Don't zip GPX files in the SQL API, because they are often opened by a handheld device witout the means to unzip.
- Force GPX tracks instead of route, so two or more segments in multi lines don't render an error.

## [2022.12.0] - 2022-7-12

### Added

- The `Baselayerjs` API will now return two new properties for use in Dashbaord: disableDatabaseCreation and loginLogo

## [2022.11.0] - 2022-17-11

### Added

- Added the following to documentation: description on how to enable extensions and a description on the traccar_api
  extension.
- Documentation on layers and authentication.

### Changed

- Upgraded Grunt and packages.
- Detection of QGIS backed layers in WMS requests. The client doesn't have to know about this when making the request.
- WMS filters don't work with multiple layers, where one or more is QGIS backed and a WMS exception is now thrown in
  this case.

### Fixed

- Quote of fields when new version of record is inserted (Track changes).

## [2022.10.0] - 2022-5-10

### Changed

- It's now possible to set `Expires` attribute for the session cookie. Defaults to 86400 seconds.

```php
{
  "sessionMaxAge" => 86400
}
```

### Fixed

- Restriction values from JSON was always cast to string, which meant the editor tried to submit strings to numeric
  fields.

## [2022.9.0] - 2022-30-9

### Added

- Two new v3 API's: `api/v3/schema` and `api/v3/meta`, which correspond to the v2 ones (`api/v2/database/schemas` and
  `api/v2/meta`). The new `meta` does format the data different and let out some legacy properties. Check out swagger
  page.

### Changed

- Hide server version and OS from header and internal error pages. For this change to take effect you have to create a
  new base image.
- Updated the QGIS gpg.key to 2022 version.
- Deny all access to the .git folder in the apache server configuration.
- Layers are not longer groupped in WMS capabilities because it's not possible to request a group. This would result in
  an error when GC2 tries to authorize access to the group instead of a single layer.

## [2022.8.1] - 2022-11-8

### Changed

- The snapshot list fetch now only include metadata - not the snapshot data itself. When activating a snapshot in Vidi
  the data is fetched. This way a long snapshot list will not hog Vidi down.

## [2022.8.0] - 2022-2-8

### Added

- New meta settings for controlling zoom level visibility for vector layers in Vidi: `vector_min_zoom` and
  `vector_max_zoom`.
- New meta setting for binding a tooltip to vector layers in Vidi: `tooltip_template`. This is a mustace/handlebars
  template where feature properties can be uses:

```handlebars
This is a label for feature <b>{{gid}}</b>
```

### Fixed

- Optimized `Database::listAllSchemas` method using pg_catalog instead of information_schema.

## [2022.6.1] - 2022-27-6

### Added

- New `Template` property in Structure tab. Input is a mustache template, which will in Vidi replace the actual value.
  All values of the feature can be used in template. Ideal for e.g. custom links/images with alt text. Will overrule
  `Content` and `Link` properties.

### Fixed

- Doubled download in scheduler is fixed.
- Creating and updating of a layers properties (also in structure tab) now uses prepared statements, so specific
  characters will not trip updates/inserts in database.

## [2022.6.0] - 2022-20-6

### Added

- `Model::doesColumnExist` now uses `pg_attribute` instead of `information_schema` which speeds up the query many times.

## [2022.5.1] - 2022-23-5

### Added

- Memcached can now be used for MapCache backend. For now the host and port is hardcoded to `memcached` and `11211`, so
  only a local dockerized Memcached server can be used. `docker/docker-compose.yml` is updated with Memcached.

### Fixed

- In WFS-t 1.0.0 it's now possible to provide primary key as an ordinary element, because 1.0.0 doesn't support `idgen`.
- In MapFiles files default geometry type is set to point, because both line and polygon can be drawn as points. The
  default will be used when a layer has GEOMETRY as type.

## [2022.5.0] - 2022-12-5

### Fixed

- All cache tags are now md5 encoded because they can contain illegal characters (tags are formed from relation names).
- Create blank table function used 'WITH OIDS', which doesn't work in PostgreSQL > 14. It's removed from the CREATE
  statemant.

## [2022.4.1] - 2022-7-4

### Fixed

- Inhertance of privileges in key Auth API's didn't work. This is a regression bug from 2022.3.2.

## [2022.4.0] - 2022-1-4

### Fixed

- The stripping of attributes from incoming WFS requets works from an include-list instead of an exclude-list. This was
  changes in 2022.1.0. But the `gml:id` attribute on insert requests was not included and insert with explicit fid
  failed.

## [2022.3.2] - 2022-31-3

### Changed

- Usergroups are now set in Session and returned with `/controllers/layer/privileges/`, so GC2 Admin doesn't need to be
  refreshed when changing group on a sub-user.
- `Setting::get` will now get the `userGroup` property from the mapcentia database instead of the `settings.viewer`
  table. This way the data doesn't need to be replicated from mapcentia db to the user db.
- If GC2 Admin is started for more than one schema, an alert will dispatched telling the user to either close the
  tab/browser with the stall Admin or refresh it. If the latter the current Admin will then go stall and alert.
- If the session ends/timeouts GC2 Admin will dispatch an alert telling the user that no active session is running.

### Fixed

- When creating a sub-user the group was not set in the Setting model. This was only done when updating the sub-user.
- Tags can now be appended again.
- Tags presentation in footer is now nicer and `no tags` is writen out instead of showing `null` or `[]`.

## [2022.3.1] - 2022-15-3

### Added

- Added GC2 Meta option for tiled raster layer: `tiled`. If set to `true` the layer will be fetched by Vidi in tiles
  instead of one big single tile, which is default.
- In GC2 MapServer symbols you can now use [attribut] in `Classes > Symbol > Style:symbol` Allows individual rendering
  of features by using an attribute in the dataset that specifies the symbol name or an SVG url. The hard brackets []
  are required.

## [2022.3.0] - 2022-3-1

### Added

- V3 SQL API added. This is the OAuth version of the SQL API. Checkout the Swagger API docs (See below)

### Changed

- Update of the Swagger UI page. A definition select-box is added, so just go to `/swagger-ui/` and choose v2 or v3. The
  latter is default.

### Fixed

- Scheduler will now follow redirects.

## [2022.2.0] - 2022-2-1

### Fixed

- When POSTing a BBOX filter the filter coords was reversed in WFS-t.
- WFS-t now exposes BBOX as a spatial operator.
- MapInfo v15 uses `pointMembers` instead of `pointMember` in GML, so this is added to toWkt function in WFS-t.

## [2022.1.1] - 2022-1-13

### Fixed

- The stripping of attributes from incoming WFS requets works from an include-list instead of an exclude-list. This was
  changes in 2022.1.0. But the `fid` attribute on update requests was not included and updates failed.

## [2022.1.0] - 2022-1-10

### Added

- New 'Ignore' checkbox in the Structure tab. If checked the field will be ignored when using the feature info tool in
  Vidi. Useful if a field contains large data structures.

### Fixed

- Bug regarding changing password with `"` is fixed.
- Bug in parseing WFS filters with `gml` name space is fixed.
- The SQL API now uses the query string as cache key instead of the random temp view name, so the cache does not get
  flooded with keys on heavy use.

## [2021.12.0] - 2021-12-9

### Changed

- Scheduler can now be activated for all database with this setting in `app/conf/App.php`:

```php
    "gc2scheduler" => [
        "*" => true,
    ],
```

### Fixed

- Sign-in form now reacts on database exceptions letting the user know something is wrong.
- Handling of database exceptions in signup proccess, e.g. so the role is dropped again if the database creation went
  wrong. This means no manual clean up is needed.

## [2021.11.3] - 2021-11-19

### Fixed

- `maxFeature` in wfs-t rendered an error, because the db was never connected.

## [2021.11.2] - 2021-11-2

### Fixed

- Tests are fixed

## [2021.11.1] - 2021-11-1

### Changed

- `clonedb_whitelist.sh` will not try to write out mapfiles, mapcachefile and QGIS files. Use gc2-cli for this.
- API tests now uses $BUILD_ID env var if set for usernames, so they can be predicted in Vidi tests.

## [2021.11.0] - 2021-3-11

### Fixed

- Improvments in filtering for WFT-t. `Not` operator now works.

## [2021.10.2] - 2021-28-10

### Fixed

- Model `app\models\Tile::update` has been called with a string as argument from
  `app\controllers\upload\Classification::setLayerDef` and `app\controllers\uploadProcessvector::get_index`. Added type
  annotation caught this and this is now fixed.

## [2021.10.1] - 2021-25-10

### Changed

- MapCache SQLite files are now completly deleted on cache busting instead of running a DELETE FROM sql in the file.
  This solves the issue with huge SQLite files and busting.
- The `Clear tile cache` button in the `Database` tab in Admin will now only bust the merged schema cache and not every
  single layer cache.

### Fixed

- The merged schema cache in MapCache files are now agian sorted by `sort_id`.

## [2021.10.0] - 2021-13-10

### Added

- Legend data is now being cached in AppCache.

### Fixed

- Prevent default placeholder being inserted into config editor when it's empty.

## [2021.9.0] - 2021-6-9

### Changed

- Increase the `upload_max_filesize` and `post_max_size` in PHP.

### Fixed

- Regression bug in WFS-t 1.1.0, which could result in an internal error when doing transactions on polygons and
  linestrings.
- Unset Upgrade header in Apache2, which can cause an error in Safari browsers.

## [2021.8.1] - 2021-1-9

### Fixed

- Type error fixed in Keyvalue API.

## [2021.8.0] - 2021-30-8

### Fixed

- Unset unnecessary meta in Vidi snapshots. Vidi has been storing this meta data, but doesn't do it anymore. So this fix
  will reduce the size of older snap shots.

## [2021.7.0] - 2021-9-7

### Added

- The SQL API now support ogr format with `&format=ogr/[format]`. E.g. `&format=ogr/ESRI Shapefile` or
  `&format=ogr/GPKG`. The response is a zip file with the file(s) or folder. Any vector format, which ogr2ogr is able to
  write can be used.

## [2021.6.0] - 2021-2-7

### Added

- Added UTFGrid support. The Mouse-over checkbox in the Structur tab will expose the field in UTFGrid.

### Changed

- The timestamp fields `created` and `updated` are added to `settings.key_value`. The latter will be updated when the
  value is updated. The Keyvalue GET API will now sort by the `update` field in descending order.
- Upgraded QGIS Server to 3.16.8 in Dockerfile.

### Fixed

- Big `GetFeature` optimizition of WFS-t.
- If the variables in Connection.php are set as environment variables in the container and therefore Connection.php is
  empty. It is now possible to run the migrations in `app/migration/run.php`.
- Filter for WFS-t doesn't need to use name space prefixes.

## [2021.5.2]

### Changed

- outputFormat in WFS-t is set to GML2, if a not recognized format is requested instead if throwing an exception.
- `peppeocchi/php-cron-scheduler` lock files are now created in `/var/www/geocloud2/app/tmp/scheduler_locks`, so they
  get purged with other lock files after an hour.
- Keyvalue API now can be requested with headers: `Content-Type: text/plain` and `Accept: text/plain`. In this case the
  body on POST and PUT must be a base64url encoded string. And for GET the response will be a base64url encoded string.
  This makes it easier to get user generated JSON unaltered through the network. E.g. a JSON value like `ILIKE '%12'`
  will not mess things up.

### Fixed

- Then no sort_ids is set on layers or sort_ids are the same, then sort by layer title or name if the former is not set.
  The sorting is done in the application and not in the database.
- WFS-T filter parser will now drop namespaces in property names. If not this is done, the resulting SQL will be
  invalid.
- Bug regarding no-strip of gml namespace on Envelope BBOX filter is fixed.
- Fixed bug in Feature API regarding namespace changes in WFS-t.
- Trim double qoutes from ogc:PropertyName in WFS-t. Openlayers adds them in WFS requets.
- Always set ns uri to http:// in WFS-t or else editing won't work in Admin.
- Bugs related to the new `peppeocchi/php-cron-scheduler` system.
- The manual Run function in scheduler GUI will not time out after 30 secs.
- A POST WFS DescribeFeatureType to OWS request would always end in an exception. Not many clients do this, but MapInfo
  15.0 does.

## [2021.5.1]

### Added

- New v3 API for creating fishnet grids. Useful for WFS scheduler jobs.

## [2021.5.0]

### Added

- PHPStan added to project for static code analysis. A lot of issues fixed.
- WFS-T now supports version 1.1.0.
- KML/KMZ output added to WFS MapFiles. The OGR/KML og OGR/LIBKML drivers are used. Example URL:
  `/ows/mydb/test/?service=wfs&version=1.0.0&request=getfeature&typename=test.train_station&OUTPUTFORMAT=KML`

### Changed

- Migration code moved to `app\migration`.
- Namespace URI in WFS-T is now set like this xmlns:[schema]="http://[host]/[database]/[schema]". If the port differs
  from 80 or 443 it will be added. This is mostly for testing purposes.
- Scheduler now uses `peppeocchi/php-cron-scheduler` for scheduling jobs. This replaces the error-prone method by
  writing the single jobs to the crontab. Docker image must be rebuild.
- WFS-T will log all requests with POST bodies to an Apache combined style log.
- Meta dialog no longer closes after save, so it's possible to tweak settings without opening the dailog every time.
- WFS processors moved from `app/conf/wfsprocessors` to `app/wfs/processors`.
- The `metaConfig` option in `app/conf/App` will now be merged with standard options set in
  `app\inc\Globals::metaConfig`. This means it's much easier to keep this option updated. Duplicates will be filered
  out - custom will have precedence.
- rar compression format is not supported anymore.
- Bump Node.js to v14.

### Fixed

- The correct online resources are now set in OWS GetCapabilities when using sub-users. This will fix authication issues
  in QGIS, which always uses online resource URLs from GetCapabilities in WFS.
- XML reversed chars in QGS files filters are now converted to HTML entities. So a filters with < > / & ' " will not
  render the QGS file invalid.
- Newly created sub-users will be added to the session, so they can be granted privileges rigth away without siging out
  and in again.
- Bug regarding not being able to remove inheritance from sub-user is fixed.
- cacheId strings are now md5 hashed, because not all characters are allowed.
- `settings.geometry_columns_join.meta` is set to an empty JSON object, because it can cause problems in Vidi.
- Added QGIS_AUTH_DB_DIR_PATH to Apache2 conf, so HTTPS requests in QGIS Server doesn't fail.
- Exception format for MapServer WMS client is now set to application/vnd.ogc.se_xml instead of
  application/vnd.ogc.se_inimage. The latter can result in a blank image for WMS servers, which don't support
  application/vnd.ogc.se_inimage.
- Feature API: Remove line breaks in JSON and replace with \n, so breaks doesn't throw an exception when decoding.
- The Dashboard will not reset the PHPSESSION cookie, so now will GC2 set `sameSite=None` and `secure` on the cookie
  when using HTTPS.

## [2020.12.0] - 2020-21-12

### Added

- A custom PNG can now be used as legend for a layer. The URL pointing to the PNG is set in the Legend tab. Must run
  database migrations.
- PostgreSQL connection parameters can now be set using environment variables. If the parameters are set in
  `app/conf/Connection.php` they will have precedence:
    - POSTGIS_HOST=127.0.0.1
    - POSTGIS_DB=postgres
    - POSTGIS_USER=gc2
    - POSTGIS_PORT=5432
    - POSTGIS_PW=1234
    - POSTGIS_PGBOUNCER=false

### Changed

- Intercom.io widget is removed.
- Some unused files are removed.
- The primary key will now be exposed as an ordinary element in the WFS-t service. Before it was only exposed as the GML
  FID. It can not be updated and an exception will be thrown if tried.

### Fixed

- Using `PROCESSING 'NATIVE_FILTER=id=234'` instead of `FILTER` in MapFile.
- Workflow is fixed.

## [2020.11.0] - 2020-18-11

### Added

- OAuth API added with password grant: api/v3/oauth/token. Token is not longer returned using `/api/v2/session/start`.
- Settings in `\app\conf\App.php` for PostgreSQL settings:

```php
[
    "SqlApiSettings" => [
        "work_mem" => "2000 MB",
        "statement_timeout" => 60000,
    ]
];
```

- New `Autocomplete` boolean property in Structure tab. This will instruct Vidi in activating autocomplete in filtering
  for the specific field.
- The User API has now full support (create, read and update) of the `properties` field in the user object. This field
  can be used to added custom properties to a (sub)user.
- New table in the `mapcentia` database called `logins`. Each login wil be stamped in this table with database, user and
  timestamp.
- Email notification for new users and for a list of predefined emails. The email body for others contains username and
  email of the new user. "Others" can be omitted. Add this to `\app\conf\App.php`:

```php
[
    "signupNotification" => [
                "key" => "xxxxxx-xxxx-xxxx-xxxx-xxxxxxxxx", // Postmark key. Only email service supported for now
                "user" => [
                    "from" => "info@mapcentia.com",
                    "subject" => "Welcome to GC2!",
                    "htmlBody" => "<body>Welcome to GC2!</body>",
                ],
                "others" => [
                    "bcc" => [
                        "mh@example.com",
                        "info@mapcentia.com",
                    ],
                    "from" => "info@mapcentia.com",
                    "subject" => "New user has signed up",
                ]
            ]
];
```

- The color palettes in the GUI can now be customized in `app/conf/App.php`. Add something like this:

```php
[
    "colorPalette" => [
        "fab400", "999a9a", "2a6d42", "b2d235", "1a506d", "0072bb", "67B15B", "0276ba"
    ]
];
``` 

### Changed

- Updated PhpFastCache to V8, so PHP 7.3+ is required.
- JWT token removed from api/v2/session/start response. Moved to new OAuth API.
- Admin and Scheduler API moved to api/v3 and now requires OAuth.
- The `Enable filtering` property of the Structure tab is now called `Disable filtering` and will if check omit the
  field in Vidi filtering. The property was not used before.
- WFS-T now uses a database cursor and flushes GML, so huge datasets can be opened in e.g. QGIS without draining the
  memory.
- Format `ndjson` (http://ndjson.org/) is added as a `format` option in the SQL API. This will stream NDJSON (Newline
  delimited JSON).
- It's now possible to create a subuser with an unauthenticated client. The property
  `allowUnauthenticatedClientsToCreateSubUsers` must be set to `true` in `\app\conf\App.php`
- Legend API is now using MapScript, which is much faster for creation of legend icons. PHP MapScript is needed.
- In MapFiles the `wfs_extent` and `wms_extent` will always be set from the GC2 schema extent instead of leaving these
  properties out and let MapServer calculate the extent. The latter can be very inefficient.
- A WMS request can now have a parameter called `labels`, which can be either `true` or `false`. If `false` the labels
  will be switched off Mapserver and QGIS backed layers.
- A WMS request can now have a `qgs` parameter for QGIS backed layers. The value is the path to the qgs file for the
  layer (base64 encoded). The path will be used to send the request directly to qgis_serv instead of cascading it
  through MapServer. Used by Vidi.
- The Keyvalue API will output the newest keys. The field `id` is ordered DESC.

### Fixed

- Bug in the scheduler get.php script regarding gridded download.
- app/phpfastcache dir added with .gitignore file.
- SQL Bulk API now works with sub-user.
- Legend API will no longer fail if the client requests a vector representation of a layer (prefixed `v:`). The
  MapServer/QGIS Server legend will be returned.

## [2020.5.0]

### Added

- Limits for SQL API can now be set in `\app\conf\App.php` like this: (`sqlJson` will also set limit for CSV)

```php
[
    "limits" => [
        "sqlExcel" => 1000,
        "sqlJson" => 10000,
    ]
];
```

- JWT support.
    - A JWT bearer token is now return when using `/api/v2/session/start`
    - A token can be set in the header like: `Authorization: Bearer eyJ0eXAiOi....`
    - A token can be validated in the front controller `index.php` and the database can be set from it like this:

```php
Route::add("api/v3/tileseeder/{action}/[uuid]", function () {
    $jwt = Jwt::validate();
    if ($jwt["success"]){
        Database::setDb($jwt["data"]["database"]);
    } else {
        echo Response::toJson($jwt);
        exit();
    }
});
```

- New API for starting, stopping and monitoring mapcache_seed processes. This API is located at `/api/v3/tileseeder` and
  is the first v3 API which is JWT token based. Take a look above.
- The Swagger API file is split in two for v2 and v3: `public/swagger/v2/api.json` and `public/swagger/v3/api.json`

### Changed

- It's now possible to set the Redis database for appCache and session. Use `"db" => 1` in the `sessionHandler` and
  `appCache` settings.

### Fixed

- In `/api/v2/keyvalue` filters with and/or will now work. Like
  `filter='{userId}'='137180100000543' or '{browserId}'='d5d8c138-99dc-4254-850a-8a6d548cb6ce'`
- Timeouts in both Scheduler client and server are removed.
- Bugs regarding http basic auth of sub-users in WMS.

## [2020.2.0]

### Added

- Tentative Disk API. Can return free disk space and delete temporary files. For use in a cluster or serverless
  environment.
- Tentative AppCache API. For getting stats and clear cache.
- User table now has a JSONB field called `properties`. The content in this field will be added to the returned object
  when starting a session (or checking status). This field can be used to added custom properties to a (sub)user.
- In Table Structure tab, its now possible to set a link suffix in addition to link prefix. The suffix will be added to
  the end of the link. E.g ".pdf".

### Changed

- CalVer is now used with month identifier like this: YYYY.MM.Minor.Modifier.
- The default primary key can now be set with `defaultPrimaryKey` in `\app\conf\App.php`. Before this was hardcoded to
  `gid` which still is the default if `defaultPrimaryKey` is empty.
- Memcached added as an option for session handling and AppCache. The setup in `\app\conf\App.php` is changed too, so
  session handling and AppCache is set up independently:

```php
[        
    "sessionHandler" => [
     "type" => "memcached", // or redis
     "host" => "localhost:11211", // without tcp:
    ],
    "appCache" => [
     "type" => "memcached", // or redis
     "host" => "localhost:11211", // without tcp:
     "ttl" => "100",
    ]
];
```

- MapServer max map size set to 16384px, so its possible to create A0 single tile print.

## [2019.1.0] - 2019-20-12

### Added

- LABEL_NO_CLIP and POLYLINE_NO_CLIP processing directives added to GUI.
- Allowed minimum size of scaled symbols setting added to GUI.
- More fine grained caching in Layer, Setting and Model, so look-ups of primary keys, table schemata and more are
  cached. This is called AppCache
- Redis added as backend for Phpfastcache and session control:
    - Just set `redisHost` in `\app\conf\App.php` to enable Redis for sessions.
    - And add this `"appCache" => ["type" => "redis", "ttl" => "3600"]` to enable Redis for Phpfastcache.
- `"wfs_geomtype" "[type]"25d` is added in MapFiles for layers with three dimensions, so WFS returns 3D data. MapServer
  needs to be build with `DWITH_POINT_Z_M=ON`, which is done
  in [3134fc9](https://github.com/mapcentia/dockerfiles/commit/610382d42bfdb6a5ee74244cc3f30b8c9b73419a)
- Dimensions of a layer are now displayed in the Database tab footer in Admin.

### Changed

- The schema select widget in Admin is now ordered by schema name.
- Boolean fields in Settings property grids are now rendered as check icons and the widget is a standard checkbox.
- General optimization by reducing SELECTs in Layer.php.

### Fixed

- Bugs regarding sub-users

## [2019.1.0.rc1] - 2019-06-10

### Added

- Phpfastcache added to speed up Meta API.
- MapServer/MapCache now exposes layers as Mapbox Vector tiles. In MapServer just use `format=mvt` and in MapCache MVT
  layers are prefixed with `.mvt` like public.foo.mvt.
- With the new config `advertisedSrs` the advertised srs can be set for OWS in MapServer. This will override the default
  ones.
- Added `checkboxgroup` widget to Meta form.
- Filtering in WMS for both QGIS and MapServer. Use something like this: filters={"
  public.my_layer":["anvgen=11", "anvgen=21"]}. The operator is fixed to "OR".
- New key/value API, which can be used by a client to store JSON objects. Vidi is using the API for storing snapshots.
- The config `googleApiKey` for setting a Google API key for use in Vidi.
- It is now possible to set a custom path for S3 caches. This makes it possible to point a layer into an existing cache.
- Microsoft's Core Fonts are added, so QGIS Server can use them.
- `memory_limit` can now be set in app/conf/App.php.
- Handling of reach-of-memory-limit by reserving on 1MB, which is release, so a exception response can be send.
- Referencing tables is now exposed in Meta for layers.
- Foreign key constrains are now exposed in Meta as `restriction` in `fields` properties of Meta.
- Admin API for administration tasks like restoring MapFiles, QGS Files and MapCache files. Can also re-process QGS if
  e.g. the database connection changes.
- New Dashboard module: https://github.com/mapcentia/dashboard.
- New Stream mode in SQL API, which can stream huge amount of data, by sending one-line JSON.
- New content property in table-structure, which replaces image property. Three options are available: Plain, image and
  video, which Vidi will react upon.
- Support of time, date and timestamp fields. In Admin editor and data grid, all types are handled as strings, so
  different PG formats doesn't need to be handled.

### Changed

- Change how cached/not-cached layers works in the Map tab:
    - Default is now to display layers as not-cached.
    - Button in layer tree to switch between cached and not-cached display.
    - Both cached and not-cached layers are displayed as "single tiled". Cached version is using MapCache ability to
      assemble tiles server side.
- Can now set port on Elasticsearch end-point. If non specified, it will default to 9200.
- Optimized non-geometry meta VIEWs in database.
- Support of Elasticsearch 7.x

### Fixed

- Limit of 100 classes in sorting algorithms is increased.
- Style issue in Firefox for color picker in class property grid.
- Bug in Data tab, which caused an error when first sorting one grid and when tried to edit cells in another.
- CDATA is handled in Feature API, so something like HTML included in GeoJSON won't crash the API.
- Serious security bug in Meta API.
- Avoid double group names when using name with different upper/lower case.
- A lot of PHP notices are gone from log.

## [2018.2.0.rc1] - 2018-19-12

- Reload API in MapCache container is removed and legacy container linking will not longer work. MapCache now detects
  changes in configs and reloads by it self. This makes it more suitable for scaling in a server cluster (like Docker
  Swarm).  
  This means, that the gc2core and mapcache containers must be able to discover each other on an user-defined
  network.   
  So both containers must connect to the same user-defined network. Notice that Docker service discovery does not work
  with default bridge network.
- V2 of session API with both POST and GET methods for starting sessions. POST uses a JSON body and GET form data.
- Subuser class added to controllers with methods for creating, updating and deleting sub-users.
- Increase max number of colors in Class Wizard -> Intervals to 100.
- Filtering in WMS. Use something like this: filters={"public.my_layer":["anvgen=11", "anvgen=21"]} Work for now only in
  QGIS layers and the operator is fixed to "OR".

## [2018.1.1] - 2018-06-11

### Added

- MapServer Style Offset and Polar Offset added with combo field (takes numeric attribute or number).
- Added 'Note' field to main layer properties.

### Changed

- Not using meta tiles is now default. Before default was 3x3.
- Scheduler cron jobs now timeout after 4h.
- Review of file headers.

### Fixed

- Fix of MAT VIEW meta tables, so layers don't show as both geometry and non-geometry MAT VIEW.
- Versioned layers are filtered in QGIS project file, so only the current version is rendered.
- Longer retry wait for meta tile in MapCache to counter strange bug.
- Critical security bug. One layer could get privileges from another.

## [2018.1] - 2018-07-05

### Added

- New version 2 of the Elasticsearch API, which acts like the native Elasticsearch search API. Including GET with body
  and the query string "mini-language".
- New version 2 of the SQL API, which enables POST and GET of a JSON wrapped query. Supports GET with body.
- New REST "Feature Edit" API, which wraps the WFS-T API in a simple GeoJSON based REST service. Made for JavaScript
  clients.
- New modern dark theme for Admin.
- Extension mechanism for the REST API. Just drop in code for creating custom APIs.
- SQLite3 and AWS S3 MapCache back-ends are added.
- "Private" tags with prefix "_". These will not be added to CKAN.
- Scheduler has added support for Shape and TAB file sets and zip/rar/gz files over HTTP or FTP.
- Scheduler can now run SQLs before and after a job.
- Scheduler has new option "Download Schema" for GML, so inaccessible schemas can be ignorred.
- Scheduler has a daily email rapport generator. Need a Postmark account.
- Scheduler has a report website at /scheduler/report.php
- Gridded WFS download uses the GMLAS ogr2ogr driver, so schemas are used.
- Gridded WFS download now checks max feature count in single cell and count of duplicates.
- Experimental upload of MS Access databases.
- New APIs for writing out MapFiles, MapCache files and QGIS project files.
- This change log.

### Changed

- Back-end rewritten for PHP7.
- MapScript is no longer used.
- Updated routing framework. Routes are now added with full URI paths.
- Better Meta API. Can now be filtered using tags, multiple relation names, schemas and combos. Orders now by
  sort_id,f_table_name.
- Admin URI is changed from /store to /admin.
- Upgraded QGIS-server to 2.18 LTR. Upload of 2.14 QGS files will still work.
- The advanced layer panel is moved from right side to the left.
- Some rearrangement of buttons.
- Optimized queries for geometry_columns in models.
- Reduced number of API calls that GC2 Admin makes on startup.
- HTML title changed to "GC2 Admin"
- geocloud.js can now base64 encode the SQL string to avoid filtering in "threat filters". Use base64:true|false in
  request (true is default).
- Support for Elasticsearch 6.x.
- PG statement timeout in SQL API. Prevent long running statements.
- QGIS project files are stored in database for backup and easy re-creation using a new API.
- Better detection of WFS connection string style in QGIS project files.
- QGIS project files names are now randomized, so they don't get overwritten by accident.
- Tags can now be added to existing ones when adding to multiple layers.
- MapCache file is written only when necessary. Making databases with a lot of layers more snappy.
- The MapFile is separated into two MapFiles: One for WMS and one WFS. This way all WFS request uses MapServer and only
  WMS requests will use QGIS Server.

### Deprecated

- Version 1 of the Elasticsearch API
- Version 1 of the SQL API

### Removed

### Fixed

- Data Tab in Admin filters fields with illegal characters.
- Better handling of exceptions in REST API.
- Better handling of strange chars in database relation names.
- A lot of smaller fixes.

### Security

- Better checking of privileges on layers when POSTing WFS GetFeature requests.
