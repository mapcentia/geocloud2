#!/bin/bash
# Usage: sh clonedb_whitelist.sh -h SourceHost -d SourceDb -H TargetHost -D TargetDb -u SourceUser -U TargetUser -p SourcePw -P TargetPw

# Reset in case getopts has been used previously in the shell.
OPTIND=1

# Initialize our own variables:
sourcehost=""
targethost=""
sourcedb=""
targetdb=""
sourceuser=""
targetuser=""
sourcepw=""
targetpw=""
usage="Usage: sh clonedb_whitelist.sh -h SourceHost -d SourceDb -H TargetHost -D TargetDb -u SourceUser -U TargetUser p- SourcePw -P TargetPw"

while getopts "k?:h:d:H:D:u:U:p:P:" opt; do
    case "$opt" in
    k|\?)
        echo $usage
        exit 0
        ;;
    h)  sourcehost=$OPTARG
        ;;
    d)  sourcedb=$OPTARG
        ;;
    H)  targethost=$OPTARG
        ;;
    D)  targetdb=$OPTARG
        ;;
    u)  sourceuser=$OPTARG
        ;;
    U)  targetuser=$OPTARG
        ;;
    p)  sourcepw=$OPTARG
        ;;
    P)  targetpw=$OPTARG
        ;;
    :)  echo "Option -$OPTARG requires an argument." >&2
        exit 1
        ;;
    esac
done

shift $((OPTIND-1))

if [ -z "$sourcehost" ] || [ -z "$sourcedb" ] || [ -z "$targethost" ] || [ -z "$targetdb" ] || [ -z "$sourceuser" ] || [ -z "$targetuser" ]
then
    echo $usage
    exit 1
fi


IFS=$'\r\n' GLOBIGNORE='*' :; ARRAY=($(cat ./whitelist.txt))

export PGUSER=$sourceuser
export PGHOST=$sourcehost
export PGDATABASE=$sourcedb
export PGPASSWORD=$sourcepw

file="/var/www/geocloud2/public/backups/${sourcehost}/latest/${sourcedb}.bak"

for SCHEMA in "${ARRAY[@]}"
    do
        c="$c --schema=$SCHEMA"
    done
echo $c
pg_dump --format=c --file dump.bak $c

# Load in target
export PGUSER=$targetuser
export PGHOST=$targethost
export PGDATABASE=$targetdb
export PGPASSWORD=$targetpw

# Disconnect all from the target db
psql postgres -c "SELECT pg_terminate_backend(pg_stat_activity.pid)
FROM pg_stat_activity
WHERE pg_stat_activity.datname = '$targetdb'
  AND pid <> pg_backend_pid();"

dropdb $targetdb
createdb -T template0 -l da_DK.UTF-8

psql -c "CREATE EXTENSION postgis;"
psql -c "CREATE EXTENSION pgcrypto;"
psql -c "CREATE EXTENSION \"uuid-ossp\";"
psql -c "CREATE EXTENSION dblink;"
psql -c "CREATE EXTENSION hstore;"

pg_restore dump.bak --no-owner --dbname=$targetdb

# Disconnect all from the old db
psql postgres -c "SELECT pg_terminate_backend(pg_stat_activity.pid)
FROM pg_stat_activity
WHERE pg_stat_activity.datname = 'ballerup'
  AND pid <> pg_backend_pid();"

# Rename target to old
psql postgres -c "drop database ballerup"
psql postgres -c "alter database $targetdb rename to ballerup"

#Clean up
rm dump.bak
