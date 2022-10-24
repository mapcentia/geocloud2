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

Session er et API til at starte en GC2 session, hente API key og skabe en JWT token.:

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

``screen_name`` = databasen

``session_id`` = Session id til cookie baserede API'er

``subuser`` = Hvis user er Sub User, angives navnet her. Ellers ``false``

``api_key`` = User API nøgle til public API'er

``token`` = JSON Web Token til  til public API'er (er ikke fuldt ud implementeret)



SQL
=================================================================

Til at begynde med, en hurtig beskrivelse af hvad SQL API er: Postgresql med PostGIS udvidelsen (som er kernen i GC2) er nogle af de mest kraftfulde stykker software inden for kortlægning og GIS. Det er svært at forestille sig en rumlig vektoranalyse, der ikke kan lade sig gøre i en PostGIS-database. Men PostGIS er et stykke serversoftware, der kræver nogle tekniske færdigheder til at installere og bruge. Det handler ikke kun om at køre SQL'er, men du skal også vide, hvordan du formaterer og viser resultatet. SQL API giver dig mulighed for at forespørge GC2's PostGIS database ved at sende SQL-strengen via HTTP/HTTPS og modtage resultatet formateret som GeoJSON klar til visning på et webkort eller som Excel/CSV

Signaturen for SQL API er som følger::

	https://example.com/api/v2/sql/[database]
	
Eller hvis der benyttes en sub-user::

	https://example.com/api/v2/sql/[subuser@database]
	
SQL-strengen og yderligere parametere kan enten sendes som URL parametere eller i en JSON body. Følgende eksempler bruger programmet cURL, men enhver HTTP klient kan bruges.

URL parameter. Bemærk at SQL-stregen er URL encoded::

	curl -i --header "Content-Type: application/x-www-form-urlencoded" -XGET \
	https://gc2.io/api/v2/sql/dk\
	?q=SELECT%201

JSON body. Body'en kan sendes som både GET og POST. Det sidste kan bruges i klienter, som ikke kan GET med body. Fx webbrowsere::

	curl -i --header "Content-Type: application/json" -X GET \
	https://gc2.io/api/v2/sql/dk --data \
	'{"q":"SELECT 1"}'
	
Som standard returneres resultatet som GeoJSON. Men MS Excel og CSV er også en mulighed. Hvis Excel eller CSV vælges, kan man få geometrierne med ud som enten GeoJSON eller WKT strenge i en kolonne. Hvis "geoformat" ikke sættes, returneres der ikke geometrier.

Resultatets geometrier returneres som standard i EPSG:3857 (Web mercator), selvom kilden har en anden projektion. Man kan vælge resultatets projektion med "srs"::

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

**q**: SQL streng (obligatorisk)

**srs**: EPSG koden som resultatet skal være i (Standard: 3857)

**format**: geojson, excel eller csv (standard geojson)

**geoformat**: geojson eller wkt. Vedr. kun Excel og CSV (Standard: ikke sat)

**allstr**: Alle kolonner sættes som tekst-type (Standard: ikke sat)

**lifetime**: Cache resultatet i dette antal sekunder på serveren (Standard: 0)

**base64**: Markerer at SQL strengen er base64 kodet. Kan bruges til at "snyde" firewalls med "threat detection" (Standard: ikke sat)

**key**: Brugerens API nøgle. Skal bruges hvis læsning af en eller flere relationer er sikret.


INSERT, UPDATE og DELETE
-----------------------------------------------------------------

Man kan ændre data ved INSERT, UPDATE og DELETE.  SQL API vil returnere antal af række, som er blevet påvirket af transaktionen::

	--data '{
	  "q":"DELETE FROM foo.bar",
	  "key":"1fa446367cb64e14318427f5f17a0ecf"
	}'

Bulk API
-----------------------------------------------------------------

Bulk API'et giver dig mulighed for at POST flere transaktioner, som vil blive kørt i samme transaktionsblok. Bulk API'et kræver GC2 API-nøglen i header, og indholdstypen skal indstilles til text/plain. SQLer skal være nye linjeseparerede::

	curl -i \
	  --header "Content-Type: text/plain" \
	  --header "GC2-API-KEY: abc123" \
	  -X POST http://host.com/api/v2/sql/dk --data \
	'
	INSERT INTO my_table (id) VALUES(1)
	INSERT INTO my_table (id) VALUES(2)
	INSERT INTO my_table (id) VALUES(3)
	INSERT INTO my_table (id) VALUES(4)
	'


Feature
=================================================================

Feature er et REST API til skabelse, læsning, opdatering og sletning (CRUD) af rækker i enhver redigerbar relation. Feature "wrapper" WFS-T servicen, således alle funktioner i denne service også virker i Feature. Dvs. Versionering, Workflow, Extensions og Tile Busting.

Signaturen på Feature ved skabelse og opdatering::

	http://swarm.gc2.io/api/v2/feature/[user]/[relation]/[srid]
	
Signaturen på Feature ved læsning og sletning::

	https://swarm.gc2.io/api/v2/feature/[user]/[relation]/[srid]/{id}
	
``[user]`` = GC2 super user eller sub user.

``[relation]`` = schema og geometrifelt qualified relationnavn.

``[srid]`` = geometriens projektion (EPSG kode). Ved relationer uden geometri skal denne også angives, men har ingen betydning.

``{id}`` = Id på den feature, som skal læses eller slettes. Id feltet er relationes primær nøgle.



POST (skabelse)
-----------------------------------------------------------------

API key kan hentes vha. Session API

Der kan sendes flere GeoJSON objekter ad gangen. Alle transaktioner skal kunne gennemføres ellers bliver ingen rækker indsat::

	curl --header 'GC2-API-KEY:xxxxx' -XPOST https://swarm.gc2.io/api/v2/feature/_cowiplan@horsens/_cowiplan.kommuneplanramme_attr.gc2_non_postgis/25832 -d '
	{
	  "type": "FeatureCollection",
	  "features": [
	   {
	    "type": "Feature",
	    "properties": {
	      "planid": 3487446,
	      "objektkode": 10,
	      "komplan_id": 3438208,
	      "komtil_id": null,
	      "plantype": "10.1",
	      "plannavn": "Frederiksberg Allé \/ Sankt Thomas Allé",
	      "plannr": "7.B.4",
	      "distrikt": "Kvarter 19",
	      "anvgen": 11,
	      "fzone": 1,
	      "zone": 1
	    }
	   }
	  ]
	}
	'

PUT (opdatering)
-----------------------------------------------------------------

Ved PUT skal den den primær nøgle indgå i GeoJSON objeket. Her er det planid

API key kan hentes vha. Session API

Der kan sendes flere GeoJSON objekter ad gangen. Alle transaktioner skal kunne gennemføres ellers bliver ingen rækker opdateret::

	curl --header 'GC2-API-KEY:xxxxx' -XPOST https://swarm.gc2.io/api/v2/feature/_cowiplan@horsens/_cowiplan.kommuneplanramme_attr.gc2_non_postgis/25832 -d '
	{
	  type": "FeatureCollection",
	  "features": [
	    {
	      "type": "Feature",
	      "properties": {
	        "planid": 3487446,
	        "objektkode": 10,
	        "komplan_id": 3438208,
	        "komtil_id": null,
	        "plantype": "10.1",
	        "plannavn": "Frederiksberg Allé \/ Sankt Thomas Allé",
	        "plannr": "7.B.4",
	        "distrikt": "Kvarter 19",
	        "anvgen": 11,
	        "fzone": 1,
	        "zone": 1
	       }
	    }
	  ]
	}
	'

DELETE (sletning)
-----------------------------------------------------------------

Denne streng bruges ved sletning::

	curl --header 'GC2-API-KEY:xxxxx' -XPUT https://swarm.gc2.io/api/v2/feature/_cowiplan@horsens/_cowiplan.kommuneplanramme_attr.gc2_non_postgis/25832/3487446




Elasticsearch
=================================================================

Elasticsearch er en distribueret, multitenant-capable full-text søgemaskine med et RESTful web interface og schema-løse JSON dokumenter. Det er ikke kun fantastisk til søgning, men også til at skabe værdi ud af alle de data en virksomhed samler.

I GC2 har vi lavet et API for 1) at få data fra PostGIS til Elasticsearch på en meget enkel måde og 2) at eksponere Elasticsearchs API på en sikker måde.

Få data fra PostGIS til Elasticsearch
-----------------------------------------------------------------

Det er nemt at hente data fra PostGIS til Elasticsearch. Det er faktisk bare et klik på en knap. Men først kan du opsætte mapping mellem PostGIS feltertyper og Elasticsearch felttyper.

.. figure:: ../../../_media/api-elasticsearch-mapping-gc2.png
    :width: 600px
    :align: center
    :name: api-elasticsearch-mapping-gc2
    :figclass: align-center

    Opsætning elasticsearch

1. Vælg hvilken tabel (eller anden type af relation) du vil indeksere i Elasticsearch.
#. Klik på fanen Elasticsearch i nederste panel.
#. Kolonnen Native PG-type viser felttyperne i PostGIS. Elasticsearch type kolonnen viser de foreslåede typer i Elasticsearch. Sidstnævnte du kan ændre ved at dobbeltklikke på en værdi.
#. Kolonnerne Index til Format angiver, hvordan værdierne skal indekseres, analyseres, formateres osv. Se længere nede for yderligere oplysninger om opsætning af brugerdefinerede analyzers.
#. Klik på (Re)index in Elasticsearch for at indeksere dataene. Hvis indekset eksisterer, slettes og indekseres det igen.
#. Du kan slette et indeks ved at klikke på Slet fra Elasticsearch.

Efter indeksering kan du søge på indekseret på dette endpoint::

	https://host.com/api/v2/elasticsearch/search/[database]/[schema]/[table]
	
Du kan se mapping dokumentet ved at kalde denne::

	https://host.com/api/v2/elasticsearch/map/[database]/[schema]/[table]?key=[your API key]

Det egentlige indeks i Elasticsearch vil blive navngivet: database_schema_table

Dokumentstrukturen i Elasticsearch

De indekserede dokumenter er formateret som GeoJSON. Et eksempel på et GeoJSON dokument::

	{
	  "type": "Feature",
	  "geometry": {
	    "type": "Point",
	    "coordinates": [125.6, 10.1]
	  },
	  "properties": {
        "name": "Dinagat Islands"
	  }
	}
	
Eksponering af Elasticsearch search API
-----------------------------------------------------------------

GC2 eksponerer kun Search API'et i Elasticsearch. Du kan bruge GC2's API som du ville bruge Search API i Elasticsearch.

Nedenfor er et eksempel på en GET med en URI search. De følgende eksempler bruger cURL-programmet, men enhver HTTP-klient kan bruges::

	curl -i --header "Content-Type: application/json" -X GET \
	"https://gc2.io/api/v2/elasticsearch/search/dk/matrikel/jordstykke_view?q=properties.string1:Engelsholm&size=1&pretty"
	
Og her med en request body. Body kan sendes som enten GET og POST. Sidstnævnte kan bruges til klienter, der ikke kan GET med body. For eksempel web browsere:: 

	curl -i --header "Content-Type: application/json" -X GET \
	https://gc2.io/api/v2/elasticsearch/search/dk/matrikel/jordstykke_view --data \
	'{
	  "query": {
	    "match" : {
	      "properties.string1" : "Engelsholm"
	    }
	  }
	}'
	
Eksponering af Elasticsearch search API
-----------------------------------------------------------------

Hvis du hoster din egen GC2 server kan du opsætte Elasticsearch `index settings <https://www.elastic.co/guide/en/elasticsearch/guide/master/_index_settings.html>`_. herunder brugerdefinerede `analyzers <https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-custom-analyzer.html>`_. og `search analyzers. <https://www.elastic.co/guide/en/elasticsearch/reference/current/search-analyzer.html>`_. Analyzers kan herefter vælges i GC2 Admin.

På GC2-serveren oprettes et dokument kaldet app/conf/elasticsearch_settings.json, som udfyldes  med indeksindstillingerne::

	{
	  "settings": {
	    "number_of_shards": 4,
	    "analysis": {
	      "analyzer": {
	        "str_search_analyzer": {
	          "type": "custom",
	          "tokenizer": "whitespace",
	          "filter": ["lowercase"]
	        },
	        "str_index_analyzer": {
	          "type": "custom",
	          "tokenizer": "whitespace",
	          "filter": [
	            "lowercase",
	            "substring"
	          ]
	        }
	      },
	      "filter": {
	        "substring": {
	          "type": "edgeNGram",
	          "min_gram": 1,
	          "max_gram": 255
	        }
	      }
	    }
	  }
	}
	
Alle nye indekser vil blive oprettet med disse indstillinger.