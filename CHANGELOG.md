# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [CalVer](https://calver.org/).

## [Unreleased]
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
- `memory_limit` can now be set in app/conf/App.php
- Handling of reach-of-memory-limit by reserving on 1MB, which is release, so a exception response can be send.
- Referencing tables is now exposed in Meta for layers.
- Foreign key constrains are now exposed in as `restriction` in `fields` properties of Meta. 

### Changed
- Change how cached/not-cached layer work in the Map tab:
    - Default is now to display layers as not-cached.
    - Button in layer tree to switch between cached and not-cached display.
    - Both cached and not-cached layers are displayed as "single tiled". Cached version is using MapCache ability to assemble tiles server side. 
   
### Fixed
- Limit of 100 classes in sorting algorithms is increased.
- Style issue in Firefox for color picker in class property grid.
- Bug in Data tab, which caused an error when first sorting one grid and when tried to edit cells in another.
- CDATA is handled in Feature API, so something like HTML included in GeoJSON won't crash the API.
- Serious security bug in Meta API.
- Avoid double group names when using name with different upper/lower case.

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

