FROM mapcentia/gc2core:ecw
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

# Install GDAL 2.4.4 from source
RUN wget http://download.osgeo.org/gdal/2.4.4/gdal244.zip && \
    unzip gdal244.zip &&\
    cd gdal-2.4.4 &&\
    ./configure \
    --with-python=yes \
    --with-ecw=/usr/local \
    --with-java=/usr/lib/jvm/adoptopenjdk-8-hotspot-amd64 \
    --with-jvm=/usr/lib/jvm/adoptopenjdk-8-hotspot-amd64/lib/amd64/server/ \
    --with-mdb=yes \
    --with-jvm-lib-add-rpath=yes

RUN cd gdal-2.4.4 &&\
    make

RUN cd gdal-2.4.4 &&\
    make install &&\
    ldconfig &&\
    ln -s /usr/local/bin/ogr2ogr /usr/bin/ogr2ogr