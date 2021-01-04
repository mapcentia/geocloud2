# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [CalVer](https://calver.org/).

## [UNRELEASED]
### Added
- PHPStan added to project for static code analysis. A lot of type issues fixed.
  
### Changed
- Migration code moved to `app\migration`.


## [2020.12.0] - 2020-21-12
### Added
- A custom PNG can now be used as legend for a layer. The URL pointing to the PNG is set in the Legend tab. Must run database migrations.
- PostgreSQL connection parameters can now be set using environment variables. If the parameters are set in `app/conf/Connection.php` they will have precedence:
  - POSTGIS_HOST=127.0.0.1
  - POSTGIS_DB=postgres
  - POSTGIS_USER=gc2
  - POSTGIS_PORT=5432
  - POSTGIS_PW=1234
  - POSTGIS_PGBOUNCER=false
    
### Changed
- Intercom.io widget is removed.
- Some unused files are removed.
- The primary key will now be exposed as an ordinary element in the WFS-t service. Before it was only exposed as the GML FID. It can not be updated and an exception will be thrown if tried.

### Fixed
- Using `PROCESSING 'NATIVE_FILTER=id=234'` instead of `FILTER` in MapFile.

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
- New `Autocomplete` boolean property in Structure tab. This will instruct Vidi in activating autocomplete in filtering for the specific field.
- The User API has now full support (create, read and update) of the `properties` field in the user object. This field can be used to added custom properties to a (sub)user.
- New table in the `mapcentia` database called `logins`. Each login wil be stamped in this table with database, user and timestamp.
- Email notification for new users and for a list of predefined emails. The email body for others contains username and email of the new user. "Others" can be omitted. Add this to `\app\conf\App.php`:
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
- The `Enable filtering` property of the Structure tab is now called `Disable filtering` and will if check omit the field in Vidi filtering. The property was not used before.
- WFS-T now uses a database cursor and flushes GML, so huge datasets can be opened in e.g. QGIS without draining the memory.
- Format `ndjson` (http://ndjson.org/) is added as a `format` option in the SQL API. This will stream NDJSON (Newline delimited JSON).
- It's now possible to create a subuser with an unauthenticated client. The property `allowUnauthenticatedClientsToCreateSubUsers` must be set to `true` in `\app\conf\App.php`
- Legend API is now using MapScript, which is much faster for creation of legend icons. PHP MapScript is needed.
- In MapFiles the `wfs_extent` and `wms_extent` will always be set from the GC2 schema extent instead of leaving these properties out and let MapServer calculate the extent. The latter can be very inefficient.
- A WMS request can now have a parameter called `labels`, which can be either `true` or `false`. If `false` the labels will be switched off Mapserver and QGIS backed layers.
- A WMS request can now have a `qgs` parameter for QGIS backed layers. The value is the path to the qgs file for the layer (base64 encoded). The path will be used to send the request directly to qgis_serv instead of cascading it through MapServer. Used by Vidi.
- The Keyvalue API will output the newest keys. The field `id` is ordered DESC.

### Fixed
- Bug in the scheduler get.php script regarding gridded download.
- app/phpfastcache dir added with .gitignore file.
- SQL Bulk API now works with sub-user.
- Legend API will no longer fail if the client requests a vector representation of a layer (prefixed `v:`). The MapServer/QGIS Server legend will be returned.

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
- New API for starting, stopping and monitoring mapcache_seed processes. This API is located at `/api/v3/tileseeder` and is the first v3 API which is JWT token based. Take a look above.
- The Swagger API file is split in two for v2 and v3: `public/swagger/v2/api.json` and `public/swagger/v3/api.json`

### Changed
- It's now possible to set the Redis database for appCache and session. Use `"db" => 1` in the `sessionHandler` and `appCache` settings.

### Fixed
- In `/api/v2/keyvalue` filters with and/or will now work. Like `filter='{userId}'='137180100000543' or '{browserId}'='d5d8c138-99dc-4254-850a-8a6d548cb6ce'`
- Timeouts in both Scheduler client and server are removed.
- Bugs regarding http basic auth of sub-users in WMS.

## [2020.2.0]
### Added
- Tentative Disk API. Can return free disk space and delete temporary files. For use in a cluster or serverless environment.
- Tentative AppCache API. For getting stats and clear cache.
- User table now has a JSONB field called `properties`. The content in this field will be added to the returned object when starting a session (or checking status). This field can be used to added custom properties to a (sub)user.
- In Table Structure tab, its now possible to set a link suffix in addition to link prefix. The suffix will be added to the end of the link. E.g ".pdf".

### Changed
- CalVer is now used with month identifier like this: YYYY.MM.Minor.Modifier.
- The default primary key can now be set with `defaultPrimaryKey` in `\app\conf\App.php`. Before this was hardcoded to `gid` which still is the default if `defaultPrimaryKey` is empty.
- Memcached added as an option for session handling and AppCache. The setup in `\app\conf\App.php` is changed too, so session handling and AppCache be set up independently:
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
- More fine grained caching in Layer, Setting and Model, so look-ups of primary keys, table schemata and more are cached. This is called AppCache
- Redis added as backend for Phpfastcache and session control:
    - Just set `redisHost` in `\app\conf\App.php` to enable Redis for sessions.
    - And add this `"appCache" => ["type" => "redis", "ttl" => "3600"]` to enable Redis for Phpfastcache.
- `"wfs_geomtype" "[type]"25d` is added in MapFiles for layers with three dimensions, so WFS returns 3D data. MapServer needs to be build with `DWITH_POINT_Z_M=ON`, which is done in [3134fc9](https://github.com/mapcentia/dockerfiles/commit/610382d42bfdb6a5ee74244cc3f30b8c9b73419a)
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
- MapServer/MapCache now exposes layers as Mapbox Vector tiles. In MapServer just use `format=mvt` and in MapCache MVT layers are prefixed with `.mvt` like public.foo.mvt.
- With the new config `advertisedSrs` the advertised srs can be set for OWS in MapServer. This will override the default ones.
- Added `checkboxgroup` widget to Meta form.
- Filtering in WMS for both QGIS and MapServer. Use something like this: filters={"public.my_layer":["anvgen=11", "anvgen=21"]}. The operator is fixed to "OR".
- New key/value API, which can be used by a client to store JSON objects. Vidi is using the API for storing snapshots.
- The config `googleApiKey` for setting a Google API key for use in Vidi.
- It is now possible to set a custom path for S3 caches. This makes it possible to point a layer into an existing cache.
- Microsoft's Core Fonts are added, so QGIS Server can use them.
- `memory_limit` can now be set in app/conf/App.php.
- Handling of reach-of-memory-limit by reserving on 1MB, which is release, so a exception response can be send.
- Referencing tables is now exposed in Meta for layers.
- Foreign key constrains are now exposed in Meta as `restriction` in `fields` properties of Meta.
- Admin API for administration tasks like restoring MapFiles, QGS Files and MapCache files. Can also re-process QGS if e.g. the database connection changes.
- New Dashboard module: https://github.com/mapcentia/dashboard.
- New Stream mode in SQL API, which can stream huge amount of data, by sending one-line JSON.
- New content property in table-structure, which replaces image property. Three options are available: Plain, image and video, which Vidi will react upon.
- Support of time, date and timestamp fields. In Admin editor and data grid, all types are handled as strings, so different PG formats doesn't need to be handled.

### Changed
- Change how cached/not-cached layers works in the Map tab:
    - Default is now to display layers as not-cached.
    - Button in layer tree to switch between cached and not-cached display.
    - Both cached and not-cached layers are displayed as "single tiled". Cached version is using MapCache ability to assemble tiles server side. 
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
- Reload API in MapCache container is removed and legacy container linking will not longer work. MapCache now detects changes in configs and reloads by it self. This makes it more suitable for scaling in a server cluster (like Docker Swarm).  
This means, that the gc2core and mapcache containers must be able to discover each other on an user-defined network.   
So both containers must connect to the same user-defined network. Notice that Docker service discovery does not work with default bridge network.
- V2 of session API with both POST and GET methods for starting sessions. POST uses a JSON body and GET form data.
- Subuser class added to controllers with methods for creating, updating and deleting sub-users.
- Increase max number of colors in Class Wizard -> Intervals to 100.
- Filtering in WMS. Use something like this: filters={"public.my_layer":["anvgen=11", "anvgen=21"]} Work for now only in QGIS layers and the operator is fixed to "OR".

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
- New version 2 of the Elasticsearch API, which acts like the native Elasticsearch search API. Including GET with body and the query string "mini-language".  
- New version 2 of the SQL API, which enables POST and GET of a JSON wrapped query. Supports GET with body.
- New REST "Feature Edit" API, which wraps the WFS-T API in a simple GeoJSON based REST service. Made for JavaScript clients.
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
- Better Meta API. Can now be filtered using tags, multiple relation names, schemas and combos. Orders now by sort_id,f_table_name.
- Admin URI is changed from /store to /admin.
- Upgraded QGIS-server to 2.18 LTR. Upload of 2.14 QGS files will still work.
- The advanced layer panel is moved from right side to the left.
- Some rearrangement of buttons.
- Optimized queries for geometry_columns in models.
- Reduced number of API calls that GC2 Admin makes on startup.
- HTML title changed to "GC2 Admin"
- geocloud.js can now base64 encode the SQL string to avoid filtering in "threat filters". Use base64:true|false in request (true is default).
- Support for Elasticsearch 6.x.
- PG statement timeout in SQL API. Prevent long running statements.
- QGIS project files are stored in database for backup and easy re-creation using a new API.
- Better detection of WFS connection string style in QGIS project files.
- QGIS project files names are now randomized, so they don't get overwritten by accident.
- Tags can now be added to existing ones when adding to multiple layers.
- MapCache file is written only when necessary. Making databases with a lot of layers more snappy.
- The MapFile is separated into two MapFiles: One for WMS and one WFS. This way all WFS request uses MapServer and only WMS requests will use QGIS Server.

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

