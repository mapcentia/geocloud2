FROM mapcentia/gc2core:ecw8
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

# Install GDAL 3.3.3 from source
RUN wget http://download.osgeo.org/gdal/3.4.3/gdal343.zip && \
    unzip gdal343.zip &&\
    cd gdal-3.4.3 &&\
    ./configure \
    --with-python=yes \
    --with-libkml=yes \
    --with-ecw=/usr/local \
    --with-java=/usr/lib/jvm/adoptopenjdk-8-hotspot-amd64 \
    --with-jvm=/usr/lib/jvm/adoptopenjdk-8-hotspot-amd64/lib/amd64 \
    --with-mdb=yes \
    --with-jvm-lib-add-rpath=yes

RUN cd gdal-3.4.3 &&\
    make

RUN cd gdal-3.4.3 &&\
    make install &&\
    ldconfig &&\
    ln -s /usr/local/bin/ogr2ogr /usr/bin/ogr2ogr