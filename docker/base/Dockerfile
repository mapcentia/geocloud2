FROM debian:bookworm-slim
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

RUN apt-get -y update
RUN apt-get -y install wget gnupg2

# Add PHP8 repo
RUN echo "deb https://packages.sury.org/php/ bookworm main" |  tee /etc/apt/sources.list.d/sury-php.list
RUN wget -qO - https://packages.sury.org/php/apt.gpg | apt-key add -
RUN apt-get -y update

# Install packages
RUN apt-get -y install cron vim wget g++ build-essential git unzip rng-tools apache2-utils postgresql-client supervisor \
    apache2  apache2-dev php8.3-fpm php8.3-pgsql php8.3-redis php8.3-memcached php8.3-curl php8.3-sqlite3 \
    php8.3-mbstring php8.3-gd php8.3-cli php8.3-mbstring php8.3-dev php8.3-zip php8.3-simplexml php8.3-dba \
    cmake libgdal-dev librsvg2-dev libpng++-dev libjpeg-dev libfreetype6-dev libproj-dev libfribidi-dev libharfbuzz-dev libcairo2-dev \
    libgeos++-dev curl libapache2-mod-fcgid libfcgi-dev xvfb osm2pgsql postgis  sudo \
    apt-transport-https ca-certificates software-properties-common \
    libprotobuf-c-dev libprotobuf-dev libprotobuf-c1 libprotobuf32 protobuf-compiler protobuf-c-compiler libtool \
    python3-dev bison python3-setuptools

# Compile SWIG > 4.0.0 to get PHP8 support
RUN git clone https://github.com/swig/swig.git &&\
    cd swig &&\
    mkdir build &&\
    cd build &&\
    cmake .. &&\
    make && make install

# Make php8.3-fpm run in the foreground
RUN sed 's/;daemonize = yes/daemonize = no/' -i /etc/php/8.3/fpm/php-fpm.conf

# Hide server version and os from header and internal error pages
RUN sed -i '/ServerTokens OS/c\ServerTokens Prod' /etc/apache2/conf-enabled/security.conf
RUN sed -i '/ServerSignature On/c\ServerSignature Off' /etc/apache2/conf-enabled/security.conf

# Install Node.js and Grunt
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.34.0/install.sh | bash
SHELL [ "/bin/bash", "-l", "-c" ]
ENV NVM_DIR "$HOME/.nvm" && \
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh" && \
    [ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"

RUN nvm install 14 && nvm use 14
RUN ln -s /root/.nvm/versions/node/v14.21.3/bin/node /usr/bin/node

RUN npm install -g grunt-cli

# Enable Apache2 modules
RUN a2enmod rewrite headers expires include actions alias fcgid proxy proxy_http proxy_ajp proxy_balancer proxy_connect proxy_html xml2enc proxy_wstunnel proxy_fcgi http2
RUN a2enconf serve-cgi-bin

# Disable gzip and let PHP control this
RUN a2dismod deflate -f

# Start fpm, so dirs are created
RUN service php8.3-fpm start
