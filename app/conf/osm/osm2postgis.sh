#!/bin/bash

wget http://download.geofabrik.de/europe/denmark-latest.osm.pbf
osm2pgsql -d osm -U postgres --hstore-all denmark-latest.osm.pbf

psql osm -U postgres -c "DROP TABLE dk.planet_osm_roads"
psql osm -U postgres -c "DROP TABLE dk.planet_osm_point"
psql osm -U postgres -c "DROP TABLE dk.planet_osm_line"
psql osm -U postgres -c "DROP TABLE dk.planet_osm_polygon"
psql osm -U postgres -c "CREATE SCHEMA dk"

#planet_osm_roads
psql osm -U postgres -c "ALTER TABLE planet_osm_roads ADD COLUMN gid SERIAL"
psql osm -U postgres -c "ALTER TABLE planet_osm_roads ADD PRIMARY KEY (gid)"
psql osm -U postgres -c "ALTER TABLE planet_osm_roads SET SCHEMA dk"

#planet_osm_point
psql osm -U postgres -c "ALTER TABLE planet_osm_point ADD COLUMN gid SERIAL"
psql osm -U postgres -c "ALTER TABLE planet_osm_point ADD PRIMARY KEY (gid)"
psql osm -U postgres -c "ALTER TABLE  planet_osm_point ADD tracktype text"
psql osm -U postgres -c "ALTER TABLE  planet_osm_point ADD way_area text"
psql osm -U postgres -c "ALTER TABLE planet_osm_point SET SCHEMA dk"

#planet_osm_line
psql osm -U postgres -c "ALTER TABLE planet_osm_line ADD COLUMN gid SERIAL"
psql osm -U postgres -c "ALTER TABLE planet_osm_line ADD PRIMARY KEY (gid)"
psql osm -U postgres -c "ALTER TABLE planet_osm_line SET SCHEMA dk"


#planet_osm_polygon
psql osm -U postgres -c "ALTER TABLE planet_osm_polygon ADD COLUMN gid SERIAL"
psql osm -U postgres -c "ALTER TABLE planet_osm_polygon ADD PRIMARY KEY (gid)"
psql osm -U postgres -c "UPDATE planet_osm_polygon SET way = ST_Multi(way)"
psql osm -U postgres -c "ALTER TABLE planet_osm_polygon ALTER COLUMN way TYPE geometry(MultiPolygon,900913)"
psql osm -U postgres -c "ALTER TABLE planet_osm_polygon SET SCHEMA dk"

#Clean up
rm denmark-latest.osm.pbf