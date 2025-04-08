#!/bin/bash

set -e

# If container is run without commando, then check if pgsql pw for gc2 is passed.
if [ $1 == "/usr/bin/supervisord" ]; then
    # Host
    if [ -n "$POSTGIS_HOST" ]; then
      echo "
*************************************************************
Info:    PostgreSQL host set in
         geocloud2/app/conf/Connection.php
*************************************************************"
    else
      echo "
*************************************************************
WARNING: No PostgreSQL host has been set for the GC2 user.
         You set this in geocloud2/app/conf/Connection.php
*************************************************************"
    fi

    # Database
    if [ -n "$POSTGIS_DB" ]; then
      echo "
*************************************************************
Info:    PostgreSQL database set in
         geocloud2/app/conf/Connection.php
*************************************************************"
    else
      echo "
*************************************************************
WARNING: No PostgreSQL database has been set for the GC2 user.
         You set this in geocloud2/app/conf/Connection.php
*************************************************************"
    fi

    # User
    if [ -n "$POSTGIS_USER" ]; then
      echo "
*************************************************************
Info:    PostgreSQL user set in
         geocloud2/app/conf/Connection.php
*************************************************************"
    else
      echo "
*************************************************************
WARNING: No PostgreSQL user has been set for the GC2 user.
         You set this in geocloud2/app/conf/Connection.php
*************************************************************"
    fi

    # Port
    if [ -n "$POSTGIS_PORT" ]; then
      echo "
*************************************************************
Info:    PostgreSQL port set in
         geocloud2/app/conf/Connection.php
*************************************************************"
    else
      echo "
*************************************************************
WARNING: No PostgreSQL port has been set for the GC2 user.
         You set this in geocloud2/app/conf/Connection.php
*************************************************************"
    fi

    # Password
    if [ -n "$POSTGIS_PW" ]; then
      echo "
*************************************************************
Info:    PostgreSQL password set in
         geocloud2/app/conf/Connection.php
*************************************************************"
    else
      echo "
*************************************************************
WARNING: No PostgreSQL password has been set for the GC2 user.
         You set this in geocloud2/app/conf/Connection.php
*************************************************************"
    fi

    # Password
    if [ -n "$POSTGIS_PGBOUNCER" ]; then
      echo "
*************************************************************
Info:    PostgreSQL pgbouncer set in
         geocloud2/app/conf/Connection.php
*************************************************************"
    else
      echo "
*************************************************************
WARNING: No PostgreSQL pgbouncer has been set for the GC2 user.
         You set this in geocloud2/app/conf/Connection.php
*************************************************************"
    fi
fi

chown www-data:www-data /var/www/geocloud2/app/tmp/ &&\
chown www-data:www-data /var/www/geocloud2/app/wms/mapfiles/ &&\
chown www-data:www-data /var/www/geocloud2/app/wms/mapcache/ &&\
chown www-data:www-data /var/www/geocloud2/app/wms/files/ &&\
chown www-data:www-data /var/www/geocloud2/app/wms/qgsfiles/ &&\
chown www-data:www-data /var/www/geocloud2/public/logs/ &&\
chmod 737 /var/lib/php/sessions
chmod +t /var/lib/php/sessions # Sticky bit
touch /var/www/geocloud2/app/wms/mapcache/mapcache.conf

# Set time zone if passed
if [ -n "$TIMEZONE" ]; then
    echo $TIMEZONE | tee /etc/timezone
    dpkg-reconfigure -f noninteractive tzdata
fi

export CONTAINER_ID=$(cat /proc/1/cpuset | cut -c9-)

exec "$@"