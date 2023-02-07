FROM mapcentia/gc2core:mapserver

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

# Add your fork here! - clone GC2 from your GitHub fork
RUN cd /var/www/ &&\
  git clone https://github.com/[ADD_GITHUB_USERNAME]/geocloud2.git --branch master

# Fetch tags from the fork and checkout latest release version
RUN cd /var/www/geocloud2 &&\
  git fetch --tags &&\
  git checkout tags/2023.1.2

# Install npm packages run Grunt
RUN	cd /var/www/geocloud2 &&\
  npm ci &&\
  grunt default

# Install dashboard
RUN mkdir -p /var/www/geocloud2/public/dashboard && mkdir /dashboardtmp && cd /dashboardtmp &&\
    git clone https://github.com/mapcentia/dashboard.git && cd /dashboardtmp/dashboard &&\
    npm install && cp ./app/config.js.sample ./app/config.js && cp ./.env.production ./.env &&\
    npm run build && cp -R ./build/* /var/www/geocloud2/public/dashboard/ &&\
    rm -R /dashboardtmp

# Install extensions and add your own fork if you are going to develope on an extension
# RUN cd /var/www/geocloud2/app/extensions && git clone https://github.com/[ADD_GITHUB_USERNAME]/vidi_cookie_getter.git

# Add the custom config files from the Docker repo.
COPY ./conf/App.php /var/www/geocloud2/app/conf/
COPY ./conf/Connection.php /var/www/geocloud2/app/conf/

# Add Supervisor config and run the deamon
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
