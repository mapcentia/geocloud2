FROM mapcentia/gc2core:base
MAINTAINER Martin Høgh<mh@mapcentia.com>

RUN export DEBIAN_FRONTEND=noninteractive
ENV DEBIAN_FRONTEND noninteractive

# Install ECW 3.3
RUN wget https://s3-eu-west-1.amazonaws.com/mapcentia-www/software/libecwj2-3.3-2006-09-06.zip &&\
	unzip libecwj2-3.3-2006-09-06.zip &&\
	wget https://s3-eu-west-1.amazonaws.com/mapcentia-www/software/libecwj2-3.3-msvc90-fixes.patch &&\
	patch -p0< libecwj2-3.3-msvc90-fixes.patch &&\
	wget https://s3-eu-west-1.amazonaws.com/mapcentia-www/software/libecwj2-3.3-wcharfix.patch &&\
	wget https://s3-eu-west-1.amazonaws.com/mapcentia-www/software/libecwj2-3.3-NCSPhysicalMemorySize-Linux.patch &&\
	cd libecwj2-3.3/ &&\
	patch -p0< ../libecwj2-3.3-NCSPhysicalMemorySize-Linux.patch &&\
	patch -p1< ../libecwj2-3.3-wcharfix.patch &&\
	./configure &&\
	make &&\
	make install
