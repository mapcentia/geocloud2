#!/bin/bash

# Before running the script!: Checkout your development branch below.
# Description: This script creates build files, install the dashboard and extensions for the GC2 backend.
#              You have to run the script inside the gc2core image.
# Extensions: Add your fork of the extension below.
# Run script: sh createBuildFilesForGc2.sh.

# Fetch tags from the fork and checkout latest release version or
# checkout your development branch
cd ../.. &&\
  pwd #&&\
  #git fetch --tags #&&\
#  git checkout tags/2022.11.0

# Install npm packages run Grunt
npm ci &&\
  grunt default # kør shell composer

# Install dashboard
# mkdir -p ./public/dashboard && mkdir /dashboardtmp && cd /dashboardtmp &&\
#     git clone https://github.com/mapcentia/dashboard.git && cd /dashboardtmp/dashboard &&\
#     npm install && cp ./app/config.js.sample ./app/config.js && cp ./.env.production ./.env &&\
#      ls &&\
#     npm run build && cp -R ./build/* ./public/dashboard/ &&\
#     rm -R /dashboardtmp

# Install extensions and add your own fork if you are going to develope on an extension
# cd ./app/extensions && git clone https://github.com/[add your github user name]/vidi_cookie_getter.git &&\
# git clone https://github.com/[add your github user name]/traccar.git &&\
# cd ../..

# Add the custom config files from the Docker repo.
# cp -fR ./docker/development/conf/App.php ./app/conf/
# cp -fR ./docker/development/conf/Connection.php ./app/conf/