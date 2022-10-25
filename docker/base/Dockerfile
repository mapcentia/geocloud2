FROM debian:bullseye-slim
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

RUN sed -i "s#deb http://deb.debian.org/debian bullseye main#deb http://deb.debian.org/debian bullseye main contrib non-free#g" /etc/apt/sources.list &&\
    sed -i "s#deb http://security.debian.org/debian-security bullseye/updates main#deb http://security.debian.org/debian-security bullseye/updates main contrib non-free#g" /etc/apt/sources.list &&\
    sed -i "s#deb http://deb.debian.org/debian bullseye-updates main#deb http://deb.debian.org/debian bullseye-updates main contrib non-free#g" /etc/apt/sources.list

RUN apt-get -y update
RUN apt-get -y install wget gnupg2

# Add PHP8 repo
RUN echo "deb https://packages.sury.org/php/ bullseye main" |  tee /etc/apt/sources.list.d/sury-php.list
RUN wget -qO - https://packages.sury.org/php/apt.gpg | apt-key add -
RUN apt-get -y update

# Install packages
RUN apt-get -y install cron vim wget g++ build-essential git unzip rng-tools apache2-utils postgresql-client supervisor netcat \
    apache2  apache2-dev php8.1-fpm php8.1-pgsql php8.1-redis php8.1-memcached php8.1-curl php8.1-sqlite3 php8.1-mbstring php8.1-gd php8.1-cli php8.1-mbstring php8.1-dev php8.1-zip \
    cmake libgdal-dev librsvg2-dev libpng++-dev libjpeg-dev libfreetype6-dev libproj-dev libfribidi-dev libharfbuzz-dev libcairo2-dev \
    libgeos++-dev libpython-all-dev curl libapache2-mod-fcgid libfcgi-dev xvfb osm2pgsql postgis  sudo \
    apt-transport-https ca-certificates software-properties-common \
    libprotobuf-c-dev libprotobuf-dev libprotobuf-c1 libprotobuf23 protobuf-compiler protobuf-c-compiler libtool \
    python3-dev bison python3-setuptools
# Compile SWIG > 4.0.0 to get PHP8 support
RUN git clone https://github.com/swig/swig.git &&\
    cd swig &&\
    mkdir build &&\
    cd build &&\
    cmake .. &&\
    make && make install

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

# Make php8.1-fpm run in the foreground
RUN sed 's/;daemonize = yes/daemonize = no/' -i /etc/php/8.1/fpm/php-fpm.conf

# Hide server version and os from header and internal error pages
RUN sed -i '/ServerTokens OS/c\ServerTokens Prod' /etc/apache2/conf-enabled/security.conf
RUN sed -i '/ServerSignature On/c\ServerSignature Off' /etc/apache2/conf-enabled/security.conf

# Install Node.js and Grunt
RUN curl -sL https://deb.nodesource.com/setup_14.x -o nodesource_setup.sh &&\
    bash nodesource_setup.sh &&\
    apt-get install -y nodejs


RUN npm install -g grunt-cli

ENV LD_LIBRARY_PATH /usr/lib/jvm/adoptopenjdk-8-hotspot-amd64/jre/lib/amd64/server

# Enable Apache2 modules
RUN a2enmod rewrite headers expires include actions alias fcgid proxy proxy_http proxy_ajp proxy_balancer proxy_connect proxy_html xml2enc proxy_wstunnel proxy_fcgi http2
RUN a2enconf serve-cgi-bin

# Disable gzip and let PHP control this
RUN a2dismod deflate -f

# Start fpm, so dirs are created
RUN service php8.1-fpm start
