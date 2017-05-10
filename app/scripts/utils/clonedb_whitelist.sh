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

##################
function if_error
##################
{
    if [[ $? -ne 0 ]]; then # check return code passed to function
        print "$1 DATE: `date +%Y-%m-%d:%H:%M:%S`"
        exit $?
    fi
}

IFS=$'\r\n' GLOBIGNORE='*' :; ARRAY=($(cat ./whitelist.txt))

export PGUSER=$sourceuser
export PGHOST=$sourcehost
export PGDATABASE=$sourcedb
export PGPASSWORD=$sourcepw

for SCHEMA in "${ARRAY[@]}"
    do
        c="$c --schema=$SCHEMA"
    done
echo $c
pg_dump --format=c --no-privileges --file dump.bak $c
if_error "Could not dump database."

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
createdb --template=template0 --locale=da_DK.UTF-8 --encoding=UTF8

psql -c "CREATE EXTENSION postgis;"
psql -c "CREATE EXTENSION pgcrypto;"
psql -c "CREATE EXTENSION \"uuid-ossp\";"
psql -c "CREATE EXTENSION dblink;"
psql -c "CREATE EXTENSION hstore;"

# pg_restore will ignore errors (some errors are harmless). In such case it will exit with status 1. Therefore can't we check.
pg_restore dump.bak --no-owner --no-privileges --no-privileges --jobs=2 --dbname=$targetdb
#if_error "Could not restore database."

# Make sure all MATERIALIZED VIEWs are refreshed
for MATVIEW in `psql -qAt -c "SELECT schemaname||'.'||matviewname AS mview FROM pg_matviews"`
        do
                psql -c "REFRESH MATERIALIZED VIEW $MATVIEW"
        done

# Check if settings schema is available in target.
psql -c "SELECT * FROM settings.geometry_columns_view" >/dev/null;
if_error "The Settings schema is missing";

# Make sure all are disconnected from the target db before rename
tmpdb="$sourcedb`date +%Y_%m_%d_%H_%M_%S`"
echo $tmpdb
psql postgres -c "
BEGIN;
SELECT pg_terminate_backend(pg_stat_activity.pid) FROM pg_stat_activity WHERE pg_stat_activity.datname = '$sourcedb' AND pid <> pg_backend_pid();
alter database $sourcedb rename to $tmpdb;
SELECT pg_terminate_backend(pg_stat_activity.pid) FROM pg_stat_activity WHERE pg_stat_activity.datname = '$targetdb' AND pid <> pg_backend_pid();
alter database $targetdb rename to $sourcedb;
COMMIT;
"
if_error "Could not rename database."

psql postgres -c "drop database $tmpdb"

#Clean up
rm dump.bak
