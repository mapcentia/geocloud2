#!/bin/sh
# Usage: sh cloneschema.sh -f FromSchema -t ToSchema -d Db -U User

# Reset in case getopts has been used previously in the shell.
OPTIND=1

# Initialize our own variables:
DUMPPATH="clone_schema_tmp.sql"
TMPDB="cloneschema_tmp"
TMPCOPY="geometry_columns_join_tmp.sql"
from=""
to=""
db=""
user=""
usage="Usage: sh cloneschema.sh -f FromSchema -t ToSchema -d Db"

while getopts "h?:f:t:d:" opt; do
    case "$opt" in
    h|\?)
        echo $usage
        exit 0
        ;;
    f)  from=$OPTARG
        ;;
    t)  to=$OPTARG
        ;;
    d)  db=$OPTARG
        ;;
    :)  echo "Option -$OPTARG requires an argument." >&2
        exit 1
        ;;
    esac
done

shift $((OPTIND-1))

if [ -z "$from" ] || [ -z "$to" ] || [ -z "$db" ]
then
    echo $usage
    exit 1
fi

echo "from=$from, to=$to, db=$db"

# Dump the original DB
pg_dump --format=c ${db} > ${DUMPPATH}

# Create the tmp DB and restore the dump in it
createdb ${TMPDB} --template=template0 --locale=da_DK.UTF-8 --encoding=UTF8
#psql ${TMPDB} -c "CREATE EXTENSION postgis"
pg_restore --dbname ${TMPDB} ${DUMPPATH}

# Rename the schema in the tmp DB
psql --dbname ${TMPDB} -c "ALTER SCHEMA ${from} RENAME TO ${to}"

# Dump the tmp DB and restore the renamed schema in the original
pg_dump --format=c  ${TMPDB} > ${DUMPPATH}
psql --dbname ${db} -c "CREATE SCHEMA ${to}"
pg_restore --dbname ${db} --schema ${to} ${DUMPPATH}

# Update the settings.geometry_columns_join table in the tmp DB
psql --dbname ${TMPDB} -c "UPDATE  settings.geometry_columns_join set _key_ = '${to}'||'.'||split_part(_key_, '.', 2)||'.'||split_part(_key_, '.', 3) WHERE split_part(_key_, '.', 1) = '${from}'"

# Copy the updated rows from settings.geometry_columns_join and restore them in the original DB
psql ${TMPDB} -c "\COPY (SELECT * FROM settings.geometry_columns_join WHERE split_part(_key_, '.', 1) = '${to}') TO STDOUT" > ${TMPCOPY}
psql ${db} -c "\COPY settings.geometry_columns_join FROM 'geometry_columns_join_tmp.sql'"

#Clean up
dropdb cloneschema_tmp
rm *.sql