FROM debian:buster
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

RUN sed -i "s#deb http://deb.debian.org/debian buster main#deb http://deb.debian.org/debian buster main contrib non-free#g" /etc/apt/sources.list &&\
    sed -i "s#deb http://security.debian.org/debian-security buster/updates main#deb http://security.debian.org/debian-security buster/updates main contrib non-free#g" /etc/apt/sources.list &&\
    sed -i "s#deb http://deb.debian.org/debian buster-updates main#deb http://deb.debian.org/debian buster-updates main contrib non-free#g" /etc/apt/sources.list

# Install packages
RUN apt-get -y update  --fix-missing
RUN apt-get -y install cron vim wget g++ build-essential git unzip rng-tools apache2-utils postgresql-client supervisor netcat \
    apache2  apache2-dev php-fpm php-pgsql php-redis php-memcached php-curl php-sqlite3 php-mbstring php-gd php-cli php-mbstring php-pear php-dev php-zip \
    cmake libgdal-dev librsvg2-dev libpng++-dev libjpeg-dev libfreetype6-dev libproj-dev libfribidi-dev libharfbuzz-dev libcairo2-dev \
    libgeos++-dev libpython-all-dev curl libapache2-mod-fcgid libfcgi-dev xvfb nodejs osm2pgsql postgis swig sudo \
    apt-transport-https ca-certificates software-properties-common \
    libprotobuf-c-dev libprotobuf-dev libprotobuf-c1 libprotobuf17 protobuf-compiler protobuf-c-compiler libtool

# Install Java 8 for MSACCESSS support in GDAL
RUN wget -O - https://adoptopenjdk.jfrog.io/adoptopenjdk/api/gpg/key/public | apt-key add - &&\
    add-apt-repository -y https://adoptopenjdk.jfrog.io/adoptopenjdk/deb/ &&\
    apt-get -y update &&\
    apt-get -y install adoptopenjdk-8-hotspot adoptopenjdk-8-hotspot-jre

# Get libs for MS Access support in GDAL
RUN cd ~ &&\
    wget https://storage.googleapis.com/google-code-archive-downloads/v2/code.google.com/mdb-sqlite/mdb-sqlite-1.0.2.tar.bz2 &&\
    tar -vxjf mdb-sqlite-1.0.2.tar.bz2 &&\
    cp mdb-sqlite-1.0.2/lib/jackcess-1.1.14.jar /usr/lib/jvm/adoptopenjdk-8-hotspot-amd64/jre/lib/ext/ &&\
    cp mdb-sqlite-1.0.2/lib/commons-logging-1.1.1.jar /usr/lib/jvm/adoptopenjdk-8-hotspot-amd64/jre/lib/ext/ &&\
    cp mdb-sqlite-1.0.2/lib/commons-lang-2.4.jar /usr/lib/jvm/adoptopenjdk-8-hotspot-amd64/jre/lib/ext/

# Install rar
#RUN pecl install rar &&\
#	echo "extension=rar.so" >> /etc/php/7.3/fpm/php.ini

# Make php-fpm run in the foreground
RUN sed 's/;daemonize = yes/daemonize = no/' -i /etc/php/7.3/fpm/php-fpm.conf

# Install Node.js, Grunt and Forever
RUN curl -sL https://deb.nodesource.com/setup_10.x -o nodesource_setup.sh &&\
    bash nodesource_setup.sh &&\
    apt-get install -y nodejs

RUN npm install -g grunt-cli

ENV LD_LIBRARY_PATH /usr/lib/jvm/adoptopenjdk-8-hotspot-amd64/jre/lib/amd64/server

# Enable Apache2 modules
RUN a2enmod rewrite headers expires include actions alias cgid fcgid proxy proxy_http proxy_ajp proxy_balancer proxy_connect proxy_html xml2enc proxy_wstunnel proxy_fcgi http2
RUN a2enconf serve-cgi-bin

# Disable gzip and let PHP control this
RUN a2dismod deflate -f


# Start fpm, so dirs are created
RUN service php7.3-fpm start
