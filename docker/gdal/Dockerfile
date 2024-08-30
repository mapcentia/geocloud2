FROM mapcentia/gc2core8:ecw
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

# Install GDAL 3.8.3 from source
RUN wget http://download.osgeo.org/gdal/3.9.2/gdal392.zip && \
    unzip gdal392.zip

RUN cd gdal-3.9.2 &&\
    mkdir build

WORKDIR gdal-3.9.2/build

RUN cmake -DWITH_PYTHON=YES -DWITH_LIBKML=yes -DWITH_ECW=/usr/local/ ..

ENV GDAL_PYTHON_BINDINGS_WITHOUT_NUMPY YES

RUN cmake --build . && cmake --build . --target install

RUN ln -s /usr/local/bin/ogr2ogr /usr/bin/ogr2ogr

WORKDIR /