# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]
- Create "retina" tiles option in tile cache settings.
- Dedicated MapFile for WFS, so it works for QGIS-Server based layers.


## [2018.1] - 2017-06-20
### Added
- New version 2 of the Elasticsearch API, which acts like the native Elasticsearch search API. Including GET with body and the query string "mini-language".  
- New version 2 of the SQL API, which enables POST and GET of a JSON wrapped query. Supports GET with body.
- New REST "Feature Edit" API, which wraps the WFS-T API in a simple GeoJSON based REST service. Made for JavaScript clients.
- New modern dark theme for Admin.
- This change log.


### Changed
- Back-end rewritten for PHP7. MapScript is no longer used.
- Better handling of exceptions in REST API.
- Updated routing framework. Routes are now added with full URI paths.
- Better Meta API. Can now be filtered using tags, multiple relation names, schemas and combos. Takes a "suborder" param to order by more properties. Defaults to f_table_name.
- Extension mechanism for the REST API. Just drop in code for creating custom APIs.
- Admin URI is changed from /store to /admin.
- Upgraded QGIS-server to 2.18 LTR. Upload of 2.14 QGS files will still work.
- The advanced layer panel is moved from right side to the left.
- Some rearrangement of buttons.
- "Private" tags with prefix "_". These will not be added to CKAN.
- HTML title changed to "GC2 Admin"
- MapCache auto expire is now defaulted to 3600 secs., except if cache lock is enabled.
- SQLite3 and AWS S3 cache back-ends are added.

### Deprecated
- Version 1 of the Elasticsearch API
- Version 1 of the SQL API

### Removed

### Fixed

### Security

