FROM debian:bookworm-slim
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN  export DEBIAN_FRONTEND=noninteractive
ENV  DEBIAN_FRONTEND noninteractive

RUN apt-get -y update
RUN apt-get -y install wget gnupg2

# Add PHP8 repo
RUN echo "deb https://packages.sury.org/php/ bookworm main" |  tee /etc/apt/sources.list.d/sury-php.list
RUN wget -qO - https://packages.sury.org/php/apt.gpg | apt-key add -
RUN apt-get -y update

# Install packages
RUN apt-get -y install vim git php8.3-pgsql php8.3-cli curl \
    postgis postgresql-15-postgis-3 postgresql-15-pgrouting postgresql-15-ogr-fdw pgagent tdsodbc postgresql-server-dev-15 libsybdb5 freetds-dev freetds-common gnupg gcc make \
    pgbouncer locales-all osm2pgsql supervisor \
    osm2pgsql unixodbc

# Install msodbcsql17
RUN wget https://packages.microsoft.com/debian/10/prod/pool/main/m/msodbcsql17/msodbcsql17_17.4.2.1-1_amd64.deb && \
    export ACCEPT_EULA=Y && \
    dpkg -i msodbcsql17_17.4.2.1-1_amd64.deb

# Set MinProtocol to TLSv1.0 because some SQL Server doesn't support 1.2
RUN sed -i "s#MinProtocol = TLSv1.2#MinProtocol = TLSv1.0#g" /etc/ssl/openssl.cnf

# Install tds_fdw
RUN export TDS_FDW_VERSION="2.0.3" &&\
    wget https://github.com/tds-fdw/tds_fdw/archive/v${TDS_FDW_VERSION}.tar.gz &&\
    tar -xvzf v${TDS_FDW_VERSION}.tar.gz &&\
    cd tds_fdw-${TDS_FDW_VERSION}/ &&\
    make USE_PGXS=1 &&\
    make USE_PGXS=1 install

# Clone GC2 from GitHub
RUN mkdir /var/www &&\
	cd /var/www/ &&\
	git clone https://github.com/mapcentia/geocloud2.git

# Add config files from Docker repo
COPY conf/postgresql/pg_hba.conf /etc/postgresql/15/main/
COPY conf/gc2/geometry_columns_join.sql /var/www/geocloud2/public/install/
COPY conf/tds_fdw/locales.conf /etc/freetds/locales.conf

# Copy GC2 config files from GIT repo, so we can create the template database and run migrations
COPY conf/gc2/App.php /var/www/geocloud2/app/conf/App.php
COPY conf/gc2/Connection.php /var/www/geocloud2/app/conf/Connection.php

# Make config in PostGreSQL
RUN echo "listen_addresses='*'" >> /etc/postgresql/15/main/postgresql.conf

# Expose standard for PostGreSQL and pgboucer
EXPOSE 5432 6432

# Share volumes
VOLUME  ["/var/www/geocloud2", "/etc/postgresql", "/var/log", "/var/lib/postgresql", "/etc/pgbouncer"]

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

COPY conf/pgbouncer/pgbouncer.ini /etc/pgbouncer/pgbouncer.ini
COPY conf/pgbouncer/userlist.txt /etc/pgbouncer/userlist.txt

RUN chown postgres:postgres /etc/pgbouncer/pgbouncer.ini
RUN chown postgres:postgres /etc/pgbouncer/userlist.txt

HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=2 \
    CMD psql -U postgres -t -c select || exit 1

# Install gc2-cli
RUN wget https://gc2-cli.s3.eu-west-1.amazonaws.com/versions/2025.4.0/d3a2e2c/apt/gc2_2025.4.0.d3a2e2c-1_amd64.deb -O gc2.deb \
    && dpkg -i gc2.deb \
    && rm gc2.deb

# Add Supervisor config and run the deamon
ADD conf/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]