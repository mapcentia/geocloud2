ARG PHP_VERSION=8.4
ARG POSTGRES_VERSION=17
# 2 => PostGIS 2.5, 3 => PostGIS 3.x
ARG POSTGIS_VERSION=3
# Supported combinations are checked below; see PG_VERSIONS.md. In short: PostGIS 2.5
# needs PG11-13 on bullseye, PG11 with PostGIS 3 needs bullseye/bookworm, and PG18
# needs bookworm/trixie. buster has no PHP 8.4.
ARG DEBIAN_CODENAME=trixie

FROM debian:${DEBIAN_CODENAME}-slim
LABEL maintainer="Martin Høgh <mh@mapcentia.com>"

ENV DEBIAN_FRONTEND=noninteractive
ARG DEBIAN_CODENAME
ENV DEBIAN_CODENAME=${DEBIAN_CODENAME}
ARG PHP_VERSION
ENV PHP_VERSION=${PHP_VERSION}
ARG POSTGRES_VERSION
ENV POSTGRES_VERSION=${POSTGRES_VERSION}
ARG POSTGIS_VERSION
ENV POSTGIS_VERSION=${POSTGIS_VERSION}

# Add PGDG (PostgreSQL) and Sury (PHP) repos. Releases dropped from apt.postgresql.org
# live on apt-archive.postgresql.org, and old packages like PostGIS 2.5 in <codename>-pgdg-archive.
RUN set -eux; \
    case "${POSTGIS_VERSION}:${POSTGRES_VERSION}:${DEBIAN_CODENAME}" in \
        2:1[123]:bullseye) ;; \
        3:11:bullseye|3:11:bookworm) ;; \
        3:1[2-7]:bullseye|3:1[2-7]:bookworm|3:1[2-7]:trixie) ;; \
        3:18:bookworm|3:18:trixie) ;; \
        *) echo "PostgreSQL ${POSTGRES_VERSION} with POSTGIS_VERSION=${POSTGIS_VERSION} is not supported on ${DEBIAN_CODENAME}. See PG_VERSIONS.md"; exit 1 ;; \
    esac; \
    # bullseye is past LTS; its packages have moved to archive.debian.org
    if [ "${DEBIAN_CODENAME}" = "bullseye" ]; then \
        sed -i 's#http://deb.debian.org#http://archive.debian.org#g' /etc/apt/sources.list; \
        echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99archive; \
    fi; \
    apt-get update; \
    apt-get install -y --no-install-recommends wget gnupg ca-certificates; \
    wget -qO- https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /usr/share/keyrings/pgdg.gpg; \
    case "${DEBIAN_CODENAME}" in \
        bookworm|trixie) PGDG_URL=https://apt.postgresql.org/pub/repos/apt ;; \
        *) PGDG_URL=https://apt-archive.postgresql.org/pub/repos/apt ;; \
    esac; \
    echo "deb [signed-by=/usr/share/keyrings/pgdg.gpg] ${PGDG_URL} ${DEBIAN_CODENAME}-pgdg main" > /etc/apt/sources.list.d/pgdg.list; \
    if [ "${POSTGIS_VERSION}" = "2" ]; then \
        echo "deb [signed-by=/usr/share/keyrings/pgdg.gpg] https://apt-archive.postgresql.org/pub/repos/apt ${DEBIAN_CODENAME}-pgdg-archive main" >> /etc/apt/sources.list.d/pgdg.list; \
    fi; \
    wget -qO /usr/share/keyrings/sury-php.gpg https://packages.sury.org/php/apt.gpg; \
    echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${DEBIAN_CODENAME} main" > /etc/apt/sources.list.d/sury-php.list; \
    apt-get update

# Install packages. Disable the cluster auto-creation of postgresql-common first,
# so only the ${POSTGRES_VERSION} cluster is created.
RUN set -eux; \
    if [ "${POSTGIS_VERSION}" = "2" ]; then POSTGIS_PKG=2.5; else POSTGIS_PKG=3; fi; \
    apt-get -y install postgresql-common \
    && sed -i 's/^#\?create_main_cluster.*/create_main_cluster = false/' /etc/postgresql-common/createcluster.conf \
    && apt-get -y install vim git php${PHP_VERSION}-pgsql php${PHP_VERSION}-cli curl \
    postgresql-${POSTGRES_VERSION} postgresql-client-${POSTGRES_VERSION} \
    postgis postgresql-${POSTGRES_VERSION}-postgis-${POSTGIS_PKG} postgresql-${POSTGRES_VERSION}-postgis-${POSTGIS_PKG}-scripts \
    postgresql-${POSTGRES_VERSION}-pgrouting postgresql-${POSTGRES_VERSION}-ogr-fdw pgagent tdsodbc postgresql-server-dev-${POSTGRES_VERSION} libsybdb5 freetds-dev freetds-common gcc make \
    pgbouncer locales-all osm2pgsql supervisor unixodbc \
    && pg_createcluster ${POSTGRES_VERSION} main \
    && (odbcinst -q -d -n FreeTDS >/dev/null 2>&1 || odbcinst -i -d -f /usr/share/tdsodbc/odbcinst.ini) \
    && rm -rf /var/lib/apt/lists/*


# Install msodbcsql17 and msodbcsql18
RUN curl -sSL -O https://packages.microsoft.com/config/debian/$(. /etc/os-release && echo $VERSION_ID)/packages-microsoft-prod.deb \
    && dpkg -i packages-microsoft-prod.deb \
    && rm packages-microsoft-prod.deb \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql17 msodbcsql18 \
    && rm -rf /var/lib/apt/lists/*

# Set MinProtocol to TLSv1.0 because some SQL Server doesn't support 1.2
RUN sed -i "s#MinProtocol = TLSv1.2#MinProtocol = TLSv1.0#g" /etc/ssl/openssl.cnf

# Install tds_fdw
#RUN export TDS_FDW_VERSION="2.0.3" &&\
#    wget https://github.com/tds-fdw/tds_fdw/archive/v${TDS_FDW_VERSION}.tar.gz &&\
#    tar -xvzf v${TDS_FDW_VERSION}.tar.gz &&\
#    cd tds_fdw-${TDS_FDW_VERSION}/ &&\
#    make USE_PGXS=1 &&\
#    make USE_PGXS=1 install

# Clone GC2 from GitHub
RUN mkdir /var/www &&\
	cd /var/www/ &&\
	git clone https://github.com/mapcentia/geocloud2.git

# Add config files from Docker repo
COPY conf/postgresql/pg_hba.conf /etc/postgresql/${POSTGRES_VERSION}/main/
COPY conf/gc2/geometry_columns_join.sql /var/www/geocloud2/public/install/
COPY conf/tds_fdw/locales.conf /etc/freetds/locales.conf

# Copy GC2 config files from GIT repo, so we can create the template database and run migrations
COPY conf/gc2/App.php /var/www/geocloud2/app/conf/App.php
COPY conf/gc2/Connection.php /var/www/geocloud2/app/conf/Connection.php

# Make config in PostGreSQL
RUN echo "listen_addresses='*'" >> /etc/postgresql/${POSTGRES_VERSION}/main/postgresql.conf

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

# Add Supervisor config and run the deamon
ADD conf/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]