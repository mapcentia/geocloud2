FROM mapcentia/gc2core:ecw5
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

# Install GDAL 2.4.4 from source
RUN wget http://download.osgeo.org/gdal/2.4.4/gdal244.zip &&\
    unzip gdal244.zip

RUN cd gdal-2.4.4 &&\
    ./configure --with-python=yes --with-ecw=/usr/local/hexagon --without-libkml CXXFLAGS='-D_GLIBCXX_USE_CXX11_ABI=0'

RUN cd gdal-2.4.4 &&\
    make

RUN cd gdal-2.4.4 &&\
    make install &&\
    ldconfig &&\
    ln -s /usr/local/bin/ogr2ogr /usr/bin/ogr2ogr