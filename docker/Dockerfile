ARG ECW_VERSION=3

###############################################################################
# Base image
###############################################################################
FROM debian:bookworm-slim AS base
LABEL maintainer="Martin Høgh <mh@mapcentia.com>"

# Non-interactive mode for apt
ENV DEBIAN_FRONTEND=noninteractive

# 1) Update and install wget + gnupg2 in the same step
RUN apt-get -y  update && apt-get -y install wget gnupg2 && rm -rf /var/lib/apt/lists/*

# Add PHP8 repo
RUN echo "deb https://packages.sury.org/php/ bookworm main" \
    | tee /etc/apt/sources.list.d/sury-php.list \
    && wget -qO - https://packages.sury.org/php/apt.gpg \
    | apt-key add - \
    && apt-get update && apt-get install -y \
    cron vim g++ build-essential git unzip rng-tools \
    apache2-utils postgresql-client supervisor \
    apache2 apache2-dev \
    php8.3-fpm php8.3-pgsql php8.3-redis php8.3-memcached \
    php8.3-curl php8.3-sqlite3 php8.3-mbstring php8.3-gd \
    php8.3-cli php8.3-dev php8.3-zip php8.3-simplexml php8.3-dba \
    cmake librsvg2-dev libpng++-dev libjpeg-dev libfreetype6-dev \
    libproj-dev libfribidi-dev libharfbuzz-dev libcairo2-dev libgeos++-dev \
    curl libapache2-mod-fcgid libfcgi-dev xvfb osm2pgsql postgis sudo \
    apt-transport-https ca-certificates software-properties-common \
    libprotobuf-c-dev libprotobuf-dev libprotobuf-c1 libprotobuf32 \
    protobuf-compiler protobuf-c-compiler libtool python3-dev bison \
    python3-setuptools jq libwxgtk3.2-dev libuv1-dev libpq-dev libxml2-dev libgif-dev\
    && rm -rf /var/lib/apt/lists/*

###############################################################################
# PECL modules (pq and uv)
###############################################################################
RUN pecl install raphf \
    && echo "extension=raphf.so" >> /etc/php/8.3/cli/php.ini \
    && pecl install pq \
    && pecl config-set preferred_state beta \
    && pecl install uv \
    && echo "extension=pq.so" >> /etc/php/8.3/cli/php.ini \
    && echo "extension=uv.so" >> /etc/php/8.3/cli/php.ini

###############################################################################
# SWIG > 4.0.0 to get PHP8 support
###############################################################################
RUN git clone https://github.com/swig/swig.git \
    && cd swig \
    && mkdir build && cd build \
    && cmake .. \
    && make -j"$(nproc)" \
    && make install \
    && cd ../.. \
    && rm -rf swig

###############################################################################
# Make php8.3-fpm run in the foreground
###############################################################################
RUN sed -i 's/;daemonize = yes/daemonize = no/' /etc/php/8.3/fpm/php-fpm.conf

# Hide server version and OS from Apache
RUN sed -i '/ServerTokens OS/c\ServerTokens Prod' /etc/apache2/conf-enabled/security.conf \
    && sed -i '/ServerSignature On/c\ServerSignature Off' /etc/apache2/conf-enabled/security.conf

###############################################################################
# Node.js + Grunt
###############################################################################
# We’ll still rely on nvm, but watch out for environment issues in multi-stage
# Docker builds. We’ll “export” environment variables inline as needed.
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.34.0/install.sh | bash

SHELL ["/bin/bash", "-l", "-c"]
ENV NVM_DIR="~/.nvm"
RUN source $NVM_DIR/nvm.sh \
    && nvm install 14 \
    && nvm use 14 \
    && ln -s /root/.nvm/versions/node/v14.21.3/bin/node /usr/bin/node \
    && ln -s /root/.nvm/versions/node/v14.21.3/bin/npm /usr/bin/npm \
    && npm install -g grunt-cli

###############################################################################
# Enable Apache modules
###############################################################################
RUN a2enmod rewrite headers expires include actions alias fcgid proxy proxy_http \
    proxy_ajp proxy_balancer proxy_connect proxy_html xml2enc proxy_wstunnel \
    proxy_fcgi http2 \
    && a2enconf serve-cgi-bin \
    && a2dismod deflate -f

# Start fpm (so dirs are created)
RUN service php8.3-fpm start || true

###############################################################################
# ecw 3.3 Stage
###############################################################################
FROM base AS ecw3

RUN wget https://s3-eu-west-1.amazonaws.com/mapcentia-www/software/libecwj2-3.3-2006-09-06.zip \
    && unzip libecwj2-3.3-2006-09-06.zip \
    && wget https://s3-eu-west-1.amazonaws.com/mapcentia-www/software/libecwj2-3.3-msvc90-fixes.patch \
    && patch -p0 < ../libecwj2-3.3-msvc90-fixes.patch \
    && wget https://s3-eu-west-1.amazonaws.com/mapcentia-www/software/libecwj2-3.3-wcharfix.patch \
    && wget https://s3-eu-west-1.amazonaws.com/mapcentia-www/software/libecwj2-3.3-NCSPhysicalMemorySize-Linux.patch \
    && cd libecwj2-3.3 \
    && patch -p0 < ../libecwj2-3.3-NCSPhysicalMemorySize-Linux.patch \
    && patch -p1 < ../libecwj2-3.3-wcharfix.patch \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && cd .. \
    && rm -rf libecwj2-3.3-2006-09-06.zip libecwj2-3.3

###############################################################################
# ecw 5.3 Stage
###############################################################################
FROM base AS ecw5

RUN wget https://s3-eu-west-1.amazonaws.com/mapcentia-tmp/ERDAS-ECW_JPEG_2000_SDK-5.3.0.zip &&\
    unzip ERDAS-ECW_JPEG_2000_SDK-5.3.0.zip &&\
    mkdir /usr/local/hexagon &&\
    cp -r ERDAS-ECW_JPEG_2000_SDK-5.3.0/Desktop_Read-Only/* /usr/local/hexagon &&\
    ln -s /usr/local/hexagon/lib/x64/release/libNCSEcw.so /usr/local/lib/libNCSEcw.so &&\
    ln -s /usr/local/hexagon/lib/x64/release/libNCSEcw.so.5.3.0 /usr/local/lib/libNCSEcw.so.5.3.0 &&\
    ldconfig

###############################################################################
# gdal Stage (ecw 3.3)
###############################################################################
FROM ecw3 AS gdal3

ENV GDAL_VERSION=3.9.2

RUN wget -q "http://download.osgeo.org/gdal/$GDAL_VERSION/gdal392.zip" -O gdal.zip \
    && unzip gdal.zip \
    && rm gdal.zip

WORKDIR /gdal-$GDAL_VERSION/build

# Build GDAL with ECW and LibKML
ENV GDAL_PYTHON_BINDINGS_WITHOUT_NUMPY=YES
RUN cmake -DWITH_PYTHON=YES -DWITH_LIBKML=YES -DWITH_ECW=/usr/local/ -DCMAKE_BUILD_TYPE=Release .. \
    && cmake --build . -j"$(nproc)" \
    && cmake --build . --target install \
    && ln -s /usr/local/bin/ogr2ogr /usr/bin/ogr2ogr

WORKDIR /
RUN rm -rf /gdal-$GDAL_VERSION

###############################################################################
# gdal Stage (ecw 5.3)
###############################################################################
FROM ecw5 AS gdal5

ENV GDAL_VERSION=3.5.3

RUN wget http://download.osgeo.org/gdal/${GDAL_VERSION}/gdal353.zip \
 && unzip gdal353.zip \
 && cd gdal-${GDAL_VERSION} \
 && ./configure \
    --with-python=no \
    --with-ecw=/usr/local/hexagon \
    --without-libkml \
    --without-libdwg \
    CXXFLAGS='-D_GLIBCXX_USE_CXX11_ABI=0' \
 && make -j"$(nproc)" \
 && make install \
 && ldconfig \
 && ln -s /usr/local/bin/ogr2ogr /usr/bin/ogr2ogr \
 && cd .. \
 && rm -rf gdal353.zip gdal-${GDAL_VERSION}

###############################################################################
# mapserver Stage
###############################################################################
FROM gdal${ECW_VERSION} AS mapserver

# Set php session + upload sizes
RUN sed -i "s/session.gc_maxlifetime = 1440/session.gc_maxlifetime = 86400/g" /etc/php/8.3/fpm/php.ini \
    && sed -i "s/upload_max_filesize = 2M/upload_max_filesize = 1000M/g" /etc/php/8.3/fpm/php.ini \
    && sed -i "s/post_max_size = 8M/post_max_size = 1000M/g" /etc/php/8.3/fpm/php.ini

# Build MapServer 8.0 from source
RUN git clone --branch rsvg_fix https://github.com/mapcentia/mapserver.git \
    && cd mapserver \
    && mkdir build && cd build \
    && cmake \
      -DCMAKE_INSTALL_PREFIX=/opt \
      -DCMAKE_PREFIX_PATH="/usr/local/pgsql/94:/usr/local:/opt:/usr/include" \
      -DWITH_CLIENT_WFS=ON \
      -DWITH_CLIENT_WMS=ON \
      -DWITH_CURL=ON \
      -DWITH_SOS=ON \
      -DWITH_PHPNG=ON \
      -DWITH_ORACLESPATIAL=OFF \
      -DWITH_RSVG=ON \
      -DWITH_POINT_Z_M=ON \
      -DWITH_KML=ON \
      -DWITH_LIBKML=ON \
      -DWITH_KMZ=ON \
      -DWITH_SVGCAIRO=OFF \
      .. \
    && make -j"$(nproc)" \
    && make install \
    && cp mapserv /usr/lib/cgi-bin/mapserv.fcgi

# Enable PHP-MapScript
RUN echo "extension=php_mapscriptng.so" >> /etc/php/8.3/fpm/php.ini

# Build MapCache from source
RUN apt-get update && apt-get install -y --no-install-recommends libapr-memcache-dev \
    && rm -rf /var/lib/apt/lists/*
RUN git clone --branch branch-1-12 http://github.com/mapserver/mapcache.git \
    && cd mapcache \
    && mkdir build && cd build \
    && cmake .. -DWITH_MEMCACHE=1 \
    && make -j"$(nproc)" \
    && make install \
    && cd ../.. \
    && rm -rf mapcache

# Install QGIS Server
RUN wget -qO - https://qgis.org/downloads/qgis-2022.gpg.key \
    | gpg --no-default-keyring --keyring gnupg-ring:/etc/apt/trusted.gpg.d/qgis-archive.gpg --import \
    && chmod a+r /etc/apt/trusted.gpg.d/qgis-archive.gpg \
    && add-apt-repository "deb https://qgis.org/debian-ltr $(lsb_release -c -s) main" \
    && apt-get update && apt-get install -y --no-install-recommends qgis-server \
    && rm -rf /var/lib/apt/lists/*

# Symlink fonts for QGIS server (slightly corrected syntax)
RUN ln -s /usr/share/fonts /usr/lib/x86_64-linux-gnu/fonts

# Add watch_mapcache_changes.sh and reload.js
COPY watch_mapcache_changes.sh /watch_mapcache_changes.sh
RUN chmod +x /watch_mapcache_changes.sh
COPY reload.js /reload.js

RUN mkdir /mapcache \
    && cp /root/mapcache/mapcache.xml /mapcache/ 2>/dev/null || true

# Add Apache, PHP-FPM, Supervisor conf from local context
ADD conf/apache/000-default.conf /etc/apache2/sites-enabled/000-default.conf
ADD conf/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
ADD conf/fpm/www.conf /etc/php/8.3/fpm/pool.d/www.conf
ADD conf/apache/run-apache.sh /
RUN chmod +x /run-apache.sh
ADD conf/fpm/run-fpm.sh /
RUN chmod +x /run-fpm.sh

RUN a2disconf other-vhosts-access-log

# Opcache config
COPY conf/fpm/opcache.ini /etc/php/8.3/mods-available/

###############################################################################
# Cron jobs
###############################################################################
# Clean up job for app/tmp
RUN crontab -l 2>/dev/null | { cat; echo "0 0 * * * php -f /var/www/geocloud2/app/scripts/clean_tmp_dir.php 1>> /dev/null 2>&1"; } | crontab
# Clean up job for tmp tables
RUN crontab -l 2>/dev/null | { cat; echo "0 0 * * * php -f /var/www/geocloud2/app/scripts/clean_scheduler_tables.php 1>> /dev/null 2>&1"; } | crontab
# Purge locks
RUN crontab -l 2>/dev/null | { cat; echo "* * * * * php -f /var/www/geocloud2/app/scripts/purge_locks.php > /var/www/geocloud2/public/logs/purge_locks.log"; } | crontab
# Create scheduler report once a day
RUN crontab -l 2>/dev/null | { cat; echo "0 6 * * * php -f /var/www/geocloud2/app/scripts/job_report.php 1>> /dev/null 2>&1"; } | crontab
# Run scheduler
RUN crontab -l 2>/dev/null | { cat; echo "* * * * * sudo -u www-data php -f /var/www/geocloud2/app/scripts/scheduler.php 1>> /dev/null 2>&1"; } | crontab

# (Optional) finalize crontab
RUN crontab -l 2>/dev/null | { cat; echo ""; } | crontab

# ogr2postgis
RUN git clone --branch gui https://github.com/mapcentia/ogr2postgis.git \
    && cd ogr2postgis \
    && mkdir build && cd build \
    && cmake .. \
    && make -j"$(nproc)" \
    && make install \
    && cd ../.. \
    && rm -rf ogr2postgis

# gc2-cli
RUN wget https://gc2-cli.s3.eu-west-1.amazonaws.com/versions/2025.4.0/d3a2e2c/apt/gc2_2025.4.0.d3a2e2c-1_amd64.deb -O gc2.deb \
    && dpkg -i gc2.deb \
    && rm gc2.deb

EXPOSE 80 443

# Healthcheck
HEALTHCHECK --interval=5s --timeout=10s --start-period=5s --retries=3 \
    CMD curl --fail http://127.0.0.1/fpm-ping || exit 1

# Clone GC2 from GitHub
RUN mkdir -p /var/www \
    && cd /var/www \
    && git clone https://github.com/mapcentia/geocloud2.git

# Set workdir
WORKDIR /var/www/geocloud2

# Install dashboard
RUN cd dashboard \
    && npm install \
    && cp ./app/config.js.sample ./app/config.js \
    && cp ./.env.production ./.env \
    && npm run build \
    && ln -s /var/www/geocloud2/dashboard/build/ /var/www/geocloud2/public/dashboard

ADD entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]

###############################################################################
# Dev Stage (final)
###############################################################################
FROM mapserver AS dev

# Get latest source
RUN git pull

# Build with Node + Grunt
RUN npm install \
    && grunt production

# Copy in GC2 custom config
COPY conf/gc2/App.php /var/www/geocloud2/app/conf/
COPY conf/gc2/Connection.php /var/www/geocloud2/app/conf/

# VOLUME for geocloud2 if you want persistence
VOLUME /var/www/geocloud2

# Xdebug (if needed for dev)
RUN apt-get update && apt-get install -y --no-install-recommends php8.3-xdebug \
    && rm -rf /var/lib/apt/lists/*
COPY conf/fpm/xdebug.ini /etc/php/8.3/mods-available/
RUN phpenmod xdebug

# Final CMD
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
