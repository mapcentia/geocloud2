FROM mapcentia/gc2core8:ecw
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

ENV GDAL_VERSION 3.9.2

RUN wget http://download.osgeo.org/gdal/$GDAL_VERSION/gdal392.zip -O gdal.zip && \
    unzip gdal.zip

RUN cd gdal-$GDAL_VERSION &&\
    mkdir build

WORKDIR gdal-$GDAL_VERSION/build

RUN cmake -DWITH_PYTHON=YES -DWITH_LIBKML=yes -DWITH_ECW=/usr/local/ ..

ENV GDAL_PYTHON_BINDINGS_WITHOUT_NUMPY YES

RUN cmake --build . && cmake --build . --target install

RUN ln -s /usr/local/bin/ogr2ogr /usr/bin/ogr2ogr

WORKDIR /