#!/bin/bash
# Usage: sh updatedb_whitelist.sh -h SourceHost -d SourceDb -u SourceUser -p SourcePw -H TargetHost -D TargetDb -U TargetUser -P TargetPw

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
usage="Usage: sh updatedb_whitelist.sh -h SourceHost -d SourceDb -u SourceUser -p SourcePw -H TargetHost -D TargetDb -U TargetUser -P TargetPw"

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
    if [ $? -ne 0 ]; then # check return code passed to function
        echo "$1 DATE: `date +%Y-%m-%d:%H:%M:%S`"
        #Clean up
        rm dump.bak -R
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
pg_dump --format=d --jobs=4 --file dump.bak $c
if_error "Could not dump database"

# Restore in target
export PGUSER=$targetuser
export PGHOST=$targethost
export PGDATABASE=$targetdb
export PGPASSWORD=$targetpw

# First drop all schemas with cascade
for SCHEMA in "${ARRAY[@]}"
    do
        psql -c "DROP SCHEMA IF EXISTS $SCHEMA CASCADE"
        if_error "Could not drop schema '$SCHEMA' in '$targetdb'"
    done

pg_restore dump.bak --jobs=4 --dbname=$targetdb
if_error "Could not restore database."

# Make sure all MATERIALIZED VIEWs are refreshed
for MATVIEW in `psql -qAt -c "SELECT schemaname||'.'||matviewname AS mview FROM pg_matviews"`
        do
                psql -c "REFRESH MATERIALIZED VIEW $MATVIEW"
        done

#Clean up
rm dump.bak -R

