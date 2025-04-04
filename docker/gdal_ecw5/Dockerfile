FROM mapcentia/gc2core8:ecw5
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

# Locked at this version. Newer version of GDAL requires ECW 5.5.0
ENV GDAL_VERSION 3.5.3

# Install GDAL 2.4.4 from source
RUN wget http://download.osgeo.org/gdal/$GDAL_VERSION/gdal353.zip &&\
    unzip gdal353.zip

RUN cd gdal-$GDAL_VERSION &&\
    ./configure --with-python=no --with-ecw=/usr/local/hexagon --without-libkml  --without-libdwg CXXFLAGS='-D_GLIBCXX_USE_CXX11_ABI=0'

RUN cd gdal-$GDAL_VERSION &&\
    make

RUN cd gdal-$GDAL_VERSION &&\
    make install &&\
    ldconfig &&\
    ln -s /usr/local/bin/ogr2ogr /usr/bin/ogr2ogr