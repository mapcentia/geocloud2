#!/bin/bash

curl $1 -o $2.pbf
osm2pgsql -d $PGDATABASE --hstore-all --slim $2.pbf

psql -c "DROP TABLE $2.planet_osm_roads"
psql -c "DROP TABLE $2.planet_osm_point"
psql -c "DROP TABLE $2.planet_osm_line"
psql -c "DROP TABLE $2.planet_osm_polygon"
psql -c "CREATE SCHEMA $2"

#planet_osm_roads
psql -c "ALTER TABLE planet_osm_roads ADD COLUMN gid SERIAL"
psql -c "ALTER TABLE planet_osm_roads ADD PRIMARY KEY (gid)"
psql -c "ALTER TABLE planet_osm_roads SET SCHEMA $2"

#planet_osm_point
psql -c "ALTER TABLE planet_osm_point ADD COLUMN gid SERIAL"
psql -c "ALTER TABLE planet_osm_point ADD PRIMARY KEY (gid)"
psql -c "ALTER TABLE planet_osm_point ADD tracktype text"
psql -c "ALTER TABLE planet_osm_point ADD way_area text"
psql -c "ALTER TABLE planet_osm_point SET SCHEMA $2"

#planet_osm_line
psql -c "ALTER TABLE planet_osm_line ADD COLUMN gid SERIAL"
psql -c "ALTER TABLE planet_osm_line ADD PRIMARY KEY (gid)"
psql -c "ALTER TABLE planet_osm_line SET SCHEMA $2"

#planet_osm_polygon
psql -c "ALTER TABLE planet_osm_polygon ADD COLUMN gid SERIAL"
psql -c "ALTER TABLE planet_osm_polygon ADD PRIMARY KEY (gid)"
psql -c "UPDATE planet_osm_polygon SET way = ST_Multi(way)"
psql -c "ALTER TABLE planet_osm_polygon ALTER COLUMN way TYPE geometry(MultiPolygon,900913)"
psql -c "ALTER TABLE planet_osm_polygon SET SCHEMA $2"

#Clean up
rm $2.pbf
