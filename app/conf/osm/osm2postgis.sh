#!/bin/bash

curl $1 -o $2.pbf
osm2pgsql -d osm --hstore-all --slim $2.pbf

psql osm -c "DROP TABLE $2.planet_osm_roads"
psql osm -c "DROP TABLE $2.planet_osm_point"
psql osm -c "DROP TABLE $2.planet_osm_line"
psql osm -c "DROP TABLE $2.planet_osm_polygon"
psql osm -c "CREATE SCHEMA $2"

#planet_osm_roads
psql osm -c "ALTER TABLE planet_osm_roads ADD COLUMN gid SERIAL"
psql osm -c "ALTER TABLE planet_osm_roads ADD PRIMARY KEY (gid)"
psql osm -c "ALTER TABLE planet_osm_roads SET SCHEMA $2"

#planet_osm_point
psql osm -c "ALTER TABLE planet_osm_point ADD COLUMN gid SERIAL"
psql osm -c "ALTER TABLE planet_osm_point ADD PRIMARY KEY (gid)"
psql osm -c "ALTER TABLE planet_osm_point ADD tracktype text"
psql osm -c "ALTER TABLE planet_osm_point ADD way_area text"
psql osm -c "ALTER TABLE planet_osm_point SET SCHEMA $2"

#planet_osm_line
psql osm -c "ALTER TABLE planet_osm_line ADD COLUMN gid SERIAL"
psql osm -c "ALTER TABLE planet_osm_line ADD PRIMARY KEY (gid)"
psql osm -c "ALTER TABLE planet_osm_line SET SCHEMA $2"

#planet_osm_polygon
psql osm -c "ALTER TABLE planet_osm_polygon ADD COLUMN gid SERIAL"
psql osm -c "ALTER TABLE planet_osm_polygon ADD PRIMARY KEY (gid)"
psql osm -c "UPDATE planet_osm_polygon SET way = ST_Multi(way)"
psql osm -c "ALTER TABLE planet_osm_polygon ALTER COLUMN way TYPE geometry(MultiPolygon,900913)"
psql osm -c "ALTER TABLE planet_osm_polygon SET SCHEMA $2"

#Clean up
rm $2.pbf