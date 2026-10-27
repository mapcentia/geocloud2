#!/bin/bash

set -e
export PGUSER=$GC2_USER

if [ $1 == "/usr/bin/supervisord" ]; then
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

    # Create template database and run latest migrations
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

      # Pull from GitHub and run database migration
      # The migration connects to this container's own database, so default the
      # POSTGRES_* variables GC2 reads to the local cluster and the GC2 user.
      cd /var/www/geocloud2 && git config pull.rebase false && git pull &&
      POSTGRES_HOST=${POSTGRES_HOST:-127.0.0.1} \
      POSTGRES_PORT=${POSTGRES_PORT:-5432} \
      POSTGRES_USER=${POSTGRES_USER:-$GC2_USER} \
      POSTGRES_PASSWORD=${POSTGRES_PASSWORD:-$GC2_PASSWORD} \
      POSTGRES_DB=${POSTGRES_DB:-postgres} \
      php /var/www/geocloud2/app/migration/run.php &&
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