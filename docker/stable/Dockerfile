FROM mapcentia/gc2core:mapserver12
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

# Clone GC2 from GitHub
RUN cd /var/www/ &&\
	git clone https://github.com/mapcentia/geocloud2.git --branch=bullseye

# Install ogr2postgis
RUN cd ~ &&\
    git clone https://github.com/mapcentia/ogr2postgis.git &&\
    cd ogr2postgis &&\
    mkdir build &&\
    cd build &&\
    cmake .. && make && make install

# Install npm packages run Grunt
#RUN	cd /var/www/geocloud2 &&\
#	npm install &&\
#	grunt production

# Install dashboard
RUN	mkdir -p /var/www/geocloud2/public/dashboard && mkdir /dashboardtmp && cd /dashboardtmp &&\
    git clone https://github.com/mapcentia/dashboard.git && cd /dashboardtmp/dashboard &&\
    npm install && cp ./app/config.js.sample ./app/config.js && cp ./.env.production ./.env &&\
    npm run build && cp -R ./build/* /var/www/geocloud2/public/dashboard/ &&\
    rm -R /dashboardtmp

# Add the custom config files from the Docker repo.
COPY conf/gc2/App.php /var/www/geocloud2/app/conf/
COPY conf/gc2/Connection.php /var/www/geocloud2/app/conf/

RUN apt-get -y install php8.3-xdebug
COPY conf/fpm/xdebug.ini  /etc/php/8.3/mods-available/
COPY conf/fpm/opcache.ini  /etc/php/8.3/mods-available/
RUN phpenmod xdebug

# Add Supervisor config and run the deamon
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
