#!/bin/bash

set -e
export PGUSER=$GC2_USER

# The uid/gid the postgres account gets depends on the Debian release, the
# postgresql-common version and the package set, so they differ between image
# builds. An existing cluster on a volume keeps the ids of the image that created
# it, and pg_ctlcluster then refuses to start ("The cluster is owned by group id
# N which does not exist"). Give the postgres account the ids that own the
# cluster instead of chown'ing the data. /etc/passwd and /etc/group are edited
# directly, because usermod would walk the whole data directory.
adopt_cluster_owner() {
  local datadir=/var/lib/postgresql/$POSTGRES_VERSION/main
  [ -f "$datadir/PG_VERSION" ] || return 0
  local new_uid new_gid old_uid old_gid
  new_uid=$(stat -c %u "$datadir")
  new_gid=$(stat -c %g "$datadir")
  old_uid=$(id -u postgres)
  old_gid=$(id -g postgres)
  if [ "$new_uid" = "$old_uid" ] && [ "$new_gid" = "$old_gid" ]; then
    return 0
  fi
  if [ "$new_uid" = "0" ]; then
    echo "WARNING: $datadir is owned by root. Leaving the postgres account as it is."
    return 0
  fi
  echo "Cluster is owned by $new_uid:$new_gid, postgres is $old_uid:$old_gid. Adopting the ids of the cluster."
  # Swap ids with whatever account already holds them in this image (messagebus
  # has uid 101 on bookworm, for instance). Otherwise the uid resolves to that
  # account, and PostgreSQL starts without the ssl-cert group membership.
  awk -F: -v OFS=: -v nu="$new_uid" -v ng="$new_gid" -v ou="$old_uid" -v og="$old_gid" '
    $1 == "postgres" { $3 = nu; $4 = ng; print; next }
    { if ($3 == nu) $3 = ou; if ($4 == ng) $4 = og; print }' /etc/passwd >/etc/passwd.adopt
  awk -F: -v OFS=: -v ng="$new_gid" -v og="$old_gid" '
    $1 == "postgres" { $3 = ng; print; next }
    { if ($3 == ng) $3 = og; print }' /etc/group >/etc/group.adopt
  cat /etc/passwd.adopt >/etc/passwd && rm /etc/passwd.adopt
  cat /etc/group.adopt >/etc/group && rm /etc/group.adopt
  # Files in the image itself (not on the volumes) still carry the old ids
  local f u g mode
  find / -xdev \( -uid "$old_uid" -o -uid "$new_uid" -o -gid "$old_gid" -o -gid "$new_gid" \) -print0 2>/dev/null |
    while IFS= read -r -d '' f; do
      u=$(stat -c %u "$f")
      g=$(stat -c %g "$f")
      mode=$(stat -c %a "$f")
      if [ "$old_uid" != "$new_uid" ]; then
        if [ "$u" = "$old_uid" ]; then u=$new_uid; elif [ "$u" = "$new_uid" ]; then u=$old_uid; fi
      fi
      if [ "$old_gid" != "$new_gid" ]; then
        if [ "$g" = "$old_gid" ]; then g=$new_gid; elif [ "$g" = "$new_gid" ]; then g=$old_gid; fi
      fi
      chown -h "$u:$g" "$f"
      # chown drops setuid/setgid bits
      if [ ! -L "$f" ]; then chmod "$mode" "$f"; fi
    done
  return 0
}

if [ $1 == "/usr/bin/supervisord" ]; then
  adopt_cluster_owner

  # Start service so we can create GC2 system tables and users.
  pg_ctlcluster "$POSTGRES_VERSION" main start

  # But first we check if they are created. I.e. if the container is restarted
  if echo 'SELECT 1' | psql postgres >/dev/null 2>&1; then
    echo "GC2 system already initiated"
    echo "
****************************************************
INFO:   GC2 system already initiated.
        Doing nothing else than start the service.
****************************************************"
    # Stop the service, so it can be started in foreground.
    pg_ctlcluster "$POSTGRES_VERSION" main stop
  else
    # First run

    # Set time zone if passed
    if [ -n "$TIMEZONE" ]; then

      # OS
      ln -snf /usr/share/zoneinfo/$TIMEZONE /etc/localtime
      echo $TIMEZONE >/etc/timezone

      # PGSQL
      echo "timezone = '$TIMEZONE'" >>/etc/postgresql/$POSTGRES_VERSION/main/postgresql.conf
    fi

    if [ -n "$GC2_USER" ]; then
      echo "User set"
    else
      echo "
****************************************************
ERROR:   No user name has been set for the GC2 user.
         Use "-e GC2_USER=name" to set it.
****************************************************"
      exit 1
    fi

    if [ -n "$GC2_PASSWORD" ]; then
      echo "Password set"
    else
      echo "
****************************************************
ERROR:   No password has been set for the GC2 user.
         Use "-e GC2_PASSWORD=password" to set it.
****************************************************"
      exit 1
    fi

    if [ -n "$GC2_LOCALE" ]; then
      locale=$GC2_LOCALE
    else
      locale=en_US.UTF-8
      echo "
****************************************************
WARNING: No locale has been set for the GC2
         template db. Setting it to en_US.UTF-8.
         Use "-e locale=your_locale" to set it.
****************************************************"
    fi

    # Create the template database. GC2 migrations are not run here; the GC2
    # application applies them (app/migration/run.php).
    echo "Creating GC2 template database and user $GC2_USER"
    psql postgres -U postgres -c "CREATE USER $GC2_USER WITH SUPERUSER CREATEROLE CREATEDB PASSWORD '$GC2_PASSWORD'" &&
      createdb template_geocloud -T template0 --encoding UTF-8 --locale $locale &&
      psql template_geocloud -c "create extension postgis" &&
      # PostGIS 2.5 has raster inside the postgis extension
      { [ "$POSTGIS_VERSION" = "2" ] || psql template_geocloud -c "create extension postgis_raster"; } &&
      psql template_geocloud -c "create extension pgcrypto" &&
      psql template_geocloud -c "create extension pgrouting" &&
      psql template_geocloud -f /var/www/geocloud2/public/install/geometry_columns_join.sql &&

      # Create the user database
      createdb mapcentia &&
      psql mapcentia -c "CREATE TABLE users\
                (screenname character varying(255),\
                pw character varying(255),\
                email character varying(255),\
                zone character varying,\
                parentdb varchar(255),\
                created timestamp with time zone DEFAULT ('now'::text)::timestamp(0) with time zone)" &&

      # Create the gc2scheduler database
      createdb gc2scheduler -T template0 --encoding UTF-8 --locale $locale &&
      psql gc2scheduler -c "CREATE TABLE jobs (
                id serial PRIMARY KEY,
                name character varying(255),
                url character varying(255),
                cron character varying(255),
                schema character varying(255),
                epsg character varying(255),
                type character varying(255),
                min character varying,
                hour character varying,
                dayofmonth character varying,
                month character varying,
                dayofweek character varying,
                encoding character varying(255),
                lastcheck boolean,
                lasttimestamp timestamp with time zone,
                db character varying(255),
                extra character varying(255)
            );" &&

      pg_ctlcluster "$POSTGRES_VERSION" main stop ||
      {
        # Stop the init server, or supervisord's postgres fails on postmaster.pid
        echo "
****************************************************
ERROR:   GC2 initialisation failed. See the output above.
****************************************************"
        pg_ctlcluster "$POSTGRES_VERSION" main stop || true
        exit 1
      }

    #Add gc2 user to pgbouncer user list
    echo "\"$GC2_USER\" \"$GC2_PASSWORD\"" >>/etc/pgbouncer/userlist.txt
  fi
fi
exec "$@"