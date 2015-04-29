#!/bin/bash
export PGUSER=postgres
sourcehost="127.0.0.1"
targethost="127.0.0.1"
sourcedb="mydb"
targetdb="test4"
file="/var/www/geocloud2/public/backups/${sourcehost}/latest/${sourcedb}.bak"
ARRAY=("tomtomclone" "envimatix")

for SCHEMA in `psql ${sourcedb} -h ${sourcehost} -c "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name<>'information_schema'" | grep -v "schema_name\|(\|---"`
    do
        for i in "${ARRAY[@]}"
            do
                if [ "$i" == "${SCHEMA}" ] ; then
                    flag="1"
                    break
                else
                    flag="0"
                fi
            done
        if [ "$flag" == "0" ] ; then
            c="$c --schema=$SCHEMA"
        fi
    done
echo $c
pg_dump $sourcedb -h $sourcehost --format=c --file dump.bak $c

# Load in target

# Disconnect all from the target db
psql -h $targethost postgres -U postgres -c "SELECT pg_terminate_backend(pg_stat_activity.pid)
FROM pg_stat_activity
WHERE pg_stat_activity.datname = '$targetdb'
  AND pid <> pg_backend_pid();"

dropdb $targetdb -h $targethost
createdb $targetdb -T template0 -l da_DK.UTF-8

psql $targetdb -c "CREATE EXTENSION postgis;"
psql $targetdb -c "CREATE EXTENSION pgcrypto;"
psql $targetdb -c "CREATE EXTENSION \"uuid-ossp\";"
psql $targetdb -c "CREATE EXTENSION dblink;"
psql $targetdb -c "CREATE EXTENSION hstore;"

pg_restore dump.bak -h $targethost --dbname=$targetdb

rm dump.bak

