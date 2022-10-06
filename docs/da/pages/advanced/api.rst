.. _api:

#################################################################
API
#################################################################



.. topic:: Overview

    :Date: |today|
    :GC2-version: 2020.12.0
    :Forfatter: `mapcentia <https://github.com/mapcentia>`_, `GEOsmeden <https://github.com/geosmeden>`_

.. contents:: 
    :depth: 3


.. include:: ../../_subs/NOTE_GETTINGSTARTED.rst

*****************************************************************
API
*****************************************************************



Session
=================================================================

Session er et API til at starte en GC2 session, hente API key og skabe en JWT token.::

	curl -XPOST "https://swarm.gc2.io/api/v2/session/start" -d '
	{
	  "user": "_plandata",
	  "password": "xxxx",
	  "schema": null
	}
	'

Session returnerer et JSON objekt::
	
	{
	  "success": true,
	  "message": "Session started",
	  "screen_name": "horsens",
	  "session_id": "vclj8pl13flage3qt3cei48i21",
	  "subuser": "_plandata",
	  "api_key": "cf24de11c018af060fa410b115c41ac1",
	  "token": "eyJ0eXAiOiJKV1QiLCJhbGci6iJIUzI1NiJ9.eyJpc3MiOiJokHRwczpcL1wvc3dhcm0uZ2MyLmlvOjgwIiwidWlkIjoiX3BsYW5kYXRhIiwiZXhwIjoxNTQ4NjEzMjE3LCJpYXQiOjE1NDg2MDk2MTcsImRhdGFiYXNlIjoiaG9yc2VucyIsImlzU3ViVXNlciI6dHJ1ZX0.tUUNYDM81iz8tC5fYh_8fKsDwmqIeI-uHMYFzAd_9CE",
	  "_execution_time": 0.121
	}

``screen_name``= databasen
``session_id``= Session id til cookie baserede API'er
``subuser``= Hvis user er Sub User, angives navnet her. Ellers ``false``
``api_key``= User API nøgle til public API'er
``token``= JSON Web Token til  til public API'er (er ikke fuldt ud implementeret)



SQL
=================================================================

Til at begynde med, en hurtig beskrivelse af hvad SQL API er: Postgresql med PostGIS udvidelsen (som er kernen i GC2) er nogle af de mest kraftfulde stykker software inden for kortlægning og GIS. Det er svært at forestille sig en rumlig vektoranalyse, der ikke kan lade sig gøre i en PostGIS-database. Men PostGIS er et stykke serversoftware, der kræver nogle tekniske færdigheder til at installere og bruge. Det handler ikke kun om at køre SQL'er, men du skal også vide, hvordan du formaterer og viser resultatet. SQL API giver dig mulighed for at forespørge GC2's PostGIS database ved at sende SQL-strengen via HTTP/HTTPS og modtage resultatet formateret som GeoJSON klar til visning på et webkort eller som Excel/CSV

Signaturen for SQL API er som følger:::

	https://example.com/api/v2/sql/[database]
	
Eller hvis der benyttes en sub-user:::

	https://example.com/api/v2/sql/[subuser@database]
	
SQL-strengen og yderligere parametere kan enten sendes som URL parametere eller i en JSON body. Følgende eksempler bruger programmet cURL, men enhver HTTP klient kan bruges.

URL parameter. Bemærk at SQL-stregen er URL encoded:::

	curl -i --header "Content-Type: application/x-www-form-urlencoded" -XGET \
	https://gc2.io/api/v2/sql/dk\
	?q=SELECT%201

JSON body. Body'en kan sendes som både GET og POST. Det sidste kan bruges i klienter, som ikke kan GET med body. Fx webbrowsere:::

	curl -i --header "Content-Type: application/json" -X GET \
	https://gc2.io/api/v2/sql/dk --data \
	'{"q":"SELECT 1"}'
	
Som standard returneres resultatet som GeoJSON. Men MS Excel og CSV er også en mulighed. Hvis Excel eller CSV vælges, kan man få geometrierne med ud som enten GeoJSON eller WKT strenge i en kolonne. Hvis "geoformat" ikke sættes, returneres der ikke geometrier.

Resultatets geometrier returneres som standard i EPSG:3857 (Web mercator), selvom kilden har en anden projektion. Man kan vælge resultatets projektion med "srs":

	curl -i --header "Content-Type: application/json" -X GET \
	https://gc2.io/api/v2/sql/dk --data \
	'{
	  "q":"SELECT 1 as id,ST_setsrid(ST_MakePoint(10,56),4326) as geom",
	  "srs":"25832",
	  "format":"csv",
	  "geoformat":"wkt",
	  "allstr": "1",
	  "lifetime": 0,
	  "base64": 0 
	}'
	
Følgende parametre kan bruges:

q: SQL streng (obligatorisk)
srs: EPSG koden som resultatet skal være i (Standard: 3857)
format: geojson, excel eller csv (standard geojson)
geoformat: geojson eller wkt. Vedr. kun Excel og CSV (Standard: ikke sat)
allstr: Alle kolonner sættes som tekst-type (Standard: ikke sat)
lifetime: Cache resultatet i dette antal sekunder på serveren (Standard: 0)
base64: Markerer at SQL strengen er base64 kodet. Kan bruges til at "snyde" firewalls med "threat detection" (Standard: ikke sat)
key: Brugerens API nøgle. Skal bruges hvis læsning af en eller flere relationer er sikret.






INSERT, UPDATE og DELETE
-----------------------------------------------------------------



Feature
=================================================================

Tilføj lag fra en Web Map Service (WMS)

.. include:: ../../_subs/WARNING_OLD_DOC.rst

Opret nyt lag
-----------------------------------------------------------------




Elasticsearch
=================================================================

Tilføj lag fra en Web Map Service (WMS)

.. include:: ../../_subs/WARNING_OLD_DOC.rst

Opret nyt lag
-----------------------------------------------------------------


