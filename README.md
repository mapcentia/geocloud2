# What is GC2?   
GC2 – an enterprise platform for managing geospatial data, making map visualisations and creating applications. Built on the best open source and standard based software.   

GC2 is part of the [OSGeo Community Project GC2/Vidi](https://www.osgeo.org/projects/gc2-vidi/)

<img title="GC2 is a OSGeo Community Project" src="https://github.com/OSGeo/osgeo/blob/master/incubation/community/OSGeo_community.png" alt="drawing" width="200"/>

## What does GC2?
Make it easy to deploy PostGIS, MapServer, QGIS Server, MapCache, Elasticsearch, GDAL/Ogr2ogr. And offers an easy-to-use web application to configure the software stack.

## What is the goal for GC2?
The GC2 project aims to make it easy for organizations to use open source software for building geo-spatial infrastructure.

## Key features of GC2
- Deploy the whole software stack by one Docker command. 
- Configure everything using a slick web based application. No editing of configuration files!
- Upload and import into PostGIS from different spatial vector and raster formats like ESRI Shape, MapInfo tab/mif, GeoJSON, GML, KML, ESRI fileGDB, GeoTIFF and ACS. And non-spatial formats like CSV and MS Access.
- Automatically configuration of MapServer, QGIS Server and MapCache.
- Build-in WFS-T for feature editing with QGIS.
- Make feature edits directly in the web administration interface.
- Manage user privileges on layer level.
- Use Workflow to control the editing of a layer in a typical author-reviewer-publisher chain.
- Manage PostGIS database. Create, alter and drop relations.
- Use PostGIS SQL language as a web service and get the result as GeoJSON, CSV or Excel.
- Get data from PostGIS indexed in Elasticsearch by clicking an button!

![GC2 Admin](https://i.imgur.com/9FoOzId.png "GC2 Admin")

![Upload files](https://i.imgur.com/OjzY7ql.png "Manage the PostGIS database")

## How to use GC2?
Online manual [here](http://mapcentia.screenstepslive.com/s/en)

## How to try GC2
Head over to [gc2.mapcentia.com](https://gc2.mapcentia.com/user/login), create a PostGIS database and start uploading data.

## How to install GC2?
GC2 uses [Docker](https://docs.docker.com/) to orchestra all the software needed. You can run Docker at Windows, MacOS or Linux. You can get the full stack up and running by using a [docker-compose](https://docs.docker.com/compose/install/) file.

First get the docker-compose file:

```bash
git clone https://github.com/mapcentia/dockerfiles.git
cd dockerfiles/docker-compose/standalone/gc2
```  

Second you have to set some environment variables. Rename the `gc2.env.dist` file to `gc2.env`:    

```bash
mv gc2.env.dist gc2.env
```  

Open the gc2.env file with your preferred text editor and set the variables. The content should be like this:

```bash
# Password for the gc2 Postgresql user
GC2_PASSWORD=12345

# Wanted timezone in database
TIMEZONE=CET

# Wanted locale in database
LOCALE=en_US.UTF-8

# NGINX proxy and Lets Encrypt vars
VIRTUAL_HOST=gc2.com
LETSENCRYPT_HOST=gc2.com
LETSENCRYPT_EMAIL=your@email.com
HTTPS_METHOD=noredirectgc2
```

Finally deploy the containers:

```bash
docker-compose up
```

Then open GC2 Admin at http://localhost:8080 and create a database by clicking Create New Account.

## Who is MapCentia?
MapCentia believes getting easy access to standards based open source software matters. As the company behind the open source project GC2 — a complete platform for managing geospatial data, making map visualisations and creating applications, MapCentia is helping teams to get the most out of their data. From local governments to world leading consulting firms, our product is extending what's possible with open source software and data.

[MapCentia.com](http://mapcentia.com)
