FROM mapcentia/gc2core:base8
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

# Install ECW 5.3.0
RUN wget https://s3-eu-west-1.amazonaws.com/mapcentia-tmp/ERDAS-ECW_JPEG_2000_SDK-5.3.0.zip &&\
    unzip ERDAS-ECW_JPEG_2000_SDK-5.3.0.zip &&\
    mkdir /usr/local/hexagon &&\
    cp -r ERDAS-ECW_JPEG_2000_SDK-5.3.0/Desktop_Read-Only/* /usr/local/hexagon &&\
    ln -s /usr/local/hexagon/lib/x64/release/libNCSEcw.so /usr/local/lib/libNCSEcw.so &&\
    ln -s /usr/local/hexagon/lib/x64/release/libNCSEcw.so.5.3.0 /usr/local/lib/libNCSEcw.so.5.3.0 &&\
    ldconfig