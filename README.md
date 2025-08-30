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

## How to install GC2 (and Vidi)?
GC2 uses [Docker](https://docs.docker.com/) to orchestra all the software needed. You can run Docker at Windows, MacOS or Linux. You can get the full stack up and running by using a [docker-compose](https://docs.docker.com/compose/install/) file.

First get the docker-compose file:

```bash
git clone https://github.com/mapcentia/gc2-vidi-docker-compose.git
cd gc2-vidi-docker-compose
```  

Deploy both GC2 and Vidi:

```bash
docker-compose up
```

When open GC2 Admin at http://localhost:8080 and create a database by clicking Create New Account.

After you've created a database you can request Vidi at http://localhost:3000/app/[database]/public. Just make sure, there are some layers in `public` schema and they're in a Group.

It's recommended to go through [this workshop](https://mapcentia.github.io/GC2-Vidi-workshop/), which will teach you the basics of GC2/Vidi. 

> The Docker-compose file is for deploying GC2 and Vidi in a test environment.
> It will expose GC2 on port 8080 and Vidi on 3000
> PostgreSQL password is set to 1234

## Dev Container (PhpStorm and VS Code)
A ready-to-use Dev Container is provided in the .devcontainer folder. It uses the docker/docker-compose.yml to start the main gc2core service along with Redis and PostGIS.

Quick start with PhpStorm (JetBrains):
- Install PhpStorm 2023.3+ and the Dev Containers plugin (Settings > Plugins > Marketplace: search for "Dev Containers").
- Open this repository in PhpStorm.
- From the Welcome screen or IDE main menu, choose: File > Open in Dev Container (or use the Dev Containers tool window) and select this project. PhpStorm will build/start the container and attach automatically.
- PhpStorm will use the container as the execution environment (PHP CLI/interpreter and Composer inside container). The workspace inside the container is /var/www/geocloud2.

Quick start with VS Code:
- Install VS Code and the Dev Containers extension
- Open this repository in VS Code
- Run: Dev Containers: Reopen in Container

When opened in a Dev Container (PhpStorm or VS Code), the supporting services are started automatically:
- postgis (PostgreSQL + PostGIS) on port 5432
- redis on port 6379

The web app ports are forwarded to your host:
- 8080 (GC2 Admin)
- 8081
- 8088 (event service)

## Who is MapCentia?
MapCentia believes getting easy access to standard based open source software matters. As the company behind the open source project GC2 — a complete platform for managing geospatial data, making map visualisations and creating applications, MapCentia is helping teams to get the most out of their data. From local governments to world leading consulting firms, our product is extending what's possible with open source software and data.

[MapCentia.com](http://mapcentia.com)
