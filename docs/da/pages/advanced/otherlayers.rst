.. _otherlayers:

#################################################################
Tilføj andre lagtyper
#################################################################



.. topic:: Overview

    :Date: |today|
    :GC2-version: 2020.12.0
    :Forfatter: `mapcentia <https://github.com/mapcentia>`_, `GEOsmeden <https://github.com/geosmeden>`_

.. contents:: 
    :depth: 3


.. include:: ../../_subs/NOTE_GETTINGSTARTED.rst

*****************************************************************
Tilføj andre lagtyper
*****************************************************************

.. include:: ../../_subs/WARNING_OLD_DOC.rst

Tilføj WMS kilde
=================================================================

Tilføj lag fra en Web Map Service (WMS)

.. include:: ../../_subs/WARNING_OLD_DOC.rst

Opret nyt lag
-----------------------------------------------------------------

.. figure:: ../../../_media/otherlayers-new-layer.png
    :width: 600px
    :align: center
    :name: otherlayers-new-layer
    :figclass: align-center

    Nyt lag

1. Start med at oprette et nyt lag.

Nyt blankt lag
-----------------------------------------------------------------

.. figure:: ../../../_media/otherlayers-empty-layer.png
    :width: 400px
    :align: center
    :name: otherlayers-empty-layer
    :figclass: align-center

    Nyt tomt lag
	
1. Vælg at oprette et Tomt lag.
#. Giv det et navn.
#. Vælg den EPSG kode som WMS'en skal forespørges med. Dvs. at WMS'en skal acceptere den valgte kode.
#. Geometri typen er underordnet, da laget ikke skal indeholde geometri
#. Opret laget.

Åben avancerede indstillinger for laget
-----------------------------------------------------------------

.. figure:: ../../../_media/otherlayers-advanced-layer-settings.png
    :width: 600px
    :align: center
    :name: otherlayers-advanced-layer-settings
    :figclass: align-center

    Nyt lag
	
1. Klik på det nye lags linje, så den bliver sort.
#. Klik på 'Avanceret'.
	
Skriv WMS url'en ind
-----------------------------------------------------------------

.. figure:: ../../../_media/otherlayers-input-wms-url.png
    :width: 400px
    :align: center
    :name: otherlayers-input-wms-url
    :figclass: align-center

    Nyt lag
	
1. Skriv en valid WMS GetMap url ind. Laget kan nu anvendes som et normalt lag.

Parameterne WIDTH, HEIGHT og BBOX er ikke nødvendige og vil blive ignoreret.

LAYERS kan indeholde flere lag. I så fald vil de blive merged sammen i GC2 laget.


Brug af database view
=================================================================

Du kan skabe et view ovenpå en SELECT forespørgelse, der giver forespørgelsen et navn som du kan referere som en normal tabel. Views er meget anvendelige til fx at filtrere og sortere data, uden at skulle oprette en ny tabel.

Views kan bruges i næsten alle sammenhænge som en rigtig tabel.

Opret view
-----------------------------------------------------------------

.. figure:: ../../../_media/otherlayers-create-database-view.png
    :width: 600px
    :align: center
    :name: otherlayers-create-database-view
    :figclass: align-center

    Nyt lag dialogboks
	
1. Klik på "Nyt lag".
#. Klik på "Database view".
#. Giv view'et et navn.
#. Skriv SELECT SQL, som skal definere view'et.
#. MATERIALIZE
#. Klik "Skab".

En tabel og view skal have en primær-nøgle. GC2 detekterer primær-nøgler på tabeller, men views har ikke primær-nøgler, så derfor falder GC2 tilbage på feltet "gid". Dvs. at et view skal have et felt "gid" med unikke værdier. Det skal også have et geometri-felt, så det dukker op i listen over lag (der er ingen krav til navngivningen af geometri-felter). Hvis en tabel er oprettet gennem GC2, vil tabellen have gid som primær-nøgle. Så en SELECT som denne vil virke:

SELECT * FROM foo WHERE bar=1

Udvælges der ikke med * skal gid og geometri-felt vælges:

SELECT gid,the_geom FROM foo WHERE bar=1

Der kan også skabes et "gid" felt med "As" syntax. Her bliver der skabt et view med ét punkt:

SELECT 1 As gid, ST_SetSRID (ST_Point(-123.365556, 48.428611),4326)::geometry(Point,4326) AS the_geom
	
	
Håndtering af views
-----------------------------------------------------------------

.. figure:: ../../../_media/otherlayers-administrate-database-view.png
    :width: 600px
    :align: center
    :name: otherlayers-administrate-database-view
    :figclass: align-center

    Database fanen
	
Views opfører sig ligesom tabeller i næsten alle sammenhænge. For at kunne kende views fra rigtige tabeller, har prikken i venstre side af lag-listen en blå farve.

En forskel på views og tabeller er, at views der kombinerer data fra forskellige tabeller ikke kan redigeres.
	
	
Se et eksisterende views definition
-----------------------------------------------------------------

.. figure:: ../../../_media/otherlayers-see-view-definition.png
    :width: 600px
    :align: center
    :name: otherlayers-see-view-definition
    :figclass: align-center

    Se view definintion
	
1. Vælg et view-lag i listen, så baggrunden bliver grå.
#. Klik på Advanced.
#. SELECT SQL'en, som definerer view'et, kan aflæses i View definition.