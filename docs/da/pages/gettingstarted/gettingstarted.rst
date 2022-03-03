.. _gettingstarted:

*****************************************************************
Kom godt i gang med GC2
*****************************************************************

.. topic:: Overview

    :Date: |today|
    :GC2-version: 2020.12.0
    :Forfatter: `giovanniborella <https://github.com/giovanniborella>`_

.. contents:: 
    :depth: 3


Hvad er GC2?
================================================================= 

GeoCloud2 eller fremover GC2 er en enterprise platform for håndtering af geospatial data, kort-visualisering og spatiale værktøjer. Bygget på de bedste opensource og standard baserede programmer.

GC2 gør det nemt at starte med PostGIS, MapServer, QGIS Server, MapCache, Elasticsearch, GDAL/OGR2OGR. Brugerfladen i GC2, er et simpelt web-interface til administration af hele software-pakken.

Målet med GC2 er at gøre det nemt for organisationer at bruge opensource værktøjer til at bygge en geospatial infrastruktur.

For at læse mere kan du gå til :ref:`readme`

Kom i gang
=================================================================

TBD

.. _gettingstarted_login:

Log ind
-----------------------------------------------------------------

Url'en til GC2 vælges når GC2 installeres. Når man forbinder til GC2 bliver man mødt med log-in skærmen som det første. Her er det muligt at logge ind som database-bruger eller sub-bruger.

Først taster man brugernavn, derefter på ``Log ind``. Der bliver lavet et hurtigt tjek på om brugeren eksiterer. Hvis den gør, bliver det muligt at udfylde password. afslut med ``Log ind``.

Det er muligt at bruge enten brugernavn eller email.

Hvis det lykkedes at logge ind bliver man automatisk bragt videre til :ref:`dashboard`

.. figure:: ../../../_media/gettingstarted-login.png
    :width: 400px
    :align: center
    :name: gettingstarted-login
    :figclass: align-center

    Log ind

.. _gettingstarted_register:

Tilmeld/opret databasebruger
-----------------------------------------------------------------

.. note::
  Hvis du leder efter subusers, kan du læse mere her: :ref:`subuser`

En databasebruger er ejeren af den database som data kommer til at leve i. Det er denne bruger der typisk bliver brugt til at administrere løsningen.

For at oprette en database-bruger trykker man ``Tilmeld``. Følg herefter registrerings-formularen for at oprette en database-bruger.

Når man er færdig, kan man bruge oplysningerne til at logge ind.

.. figure:: ../../../_media/gettingstarted-register.png
    :width: 400px
    :align: center
    :name: gettingstarted-register
    :figclass: align-center

    Opret databasebruger

.. _gettingstarted_dashboard:

Kontrolcenter/Dashboard
=================================================================

Når der er logget ind i GC2, så vises kontrolcenter/dashboardet. 

Kontrolcenter er stedet hvor man i venstre side kan se en oversigt over skemaer eller konfigurationer i databasen. I højre side vises en oversigt over Sub-brugere. Der kan også tilføjes Sub-brugere.

I den blå topbar er der et spørgsmålstegn, som giver adgang til dokumentationen, og der kan åbnes en brugerprofil for den bruger der logget ind ved at klikke på brugernavnet. Se mere om brugerprofil her: :ref:`gettingstarted_userprofile`

.. figure:: ../../../_media/gettingstarted-dashboard.png
    :width: 400px
    :align: center
    :name: gettingstarted-dashboard
    :figclass: align-center

    Kontrolcenter

Skemaer
-----------------------------------------------------------------

Hvert skema under databasebrugeren bliver vist. Der er et filter-felt, som kan bruges til at filtrere i listen.

Skemaet ``public`` bliver som standard oprettet sammen med databasebrugeren, og bør nomalt ikke bruges til noget.

Hvis der klikkes på et skema foldes det ud, og det er muligt at gøre følgende:

* Åbne Vidi med lagene der er opsat i skemaet.
* Gå til administrationsmodulet


Konfigurationer
-----------------------------------------------------------------

Konfigurationer er json filer, som gemmes i databasen. Konfigurationerne bruges til at styre opsætningen af Vidi. Dvs, det kan styres hvilke lag der vises, hvilke extensions og hvilke baggrundskort der er tilgængelige. 

Konfigurationerne oprettes her, og skal have et navn, der kan suppleres med en beskrivelse.

For en grundig gennemgang af mulighederne i konfigurationerne, så læs afsnittet i Vidi dokumentationen `Vidi kørselskonfiguration <https://vidi.readthedocs.io/da/latest/pages/standard/91_run_configuration.html>`_

.. _gettingstarted_userprofile:

Brugerprofil
-----------------------------------------------------------------

Når der er logget ind i GC2, kan man tilgå sin brugerprofil i den blå topbar. Der klikkes på brugernavnet, og der åbnes en dialogboks, hvor der kan ses brugeroplysninger og skiftes password.

.. figure:: ../../../_media/gettingstarted-userprofile.png
    :width: 400px
    :align: center
    :name: gettingstarted-dashboard
    :figclass: align-center

    Brugerprofil

Subusers
-----------------------------------------------------------------

Her vises alle subusers. For at få mere information om subusers, kan du læse :ref:`subuser`

.. _gettingstarted_admin:

Administrationsmodul
=================================================================

Administrationsmodulet er delt op i faner. Fanerne er nærmere beskrevet herunder.

.. _gettingstarted_admin_map:

Kort
-----------------------------------------------------------------

I fanen "Kort" kan man i venstre side se en oversigt over de lag der er i skemaet. Hvis lagene ikke er sat op endnu, findes de under ungrouped. Ellers findes de i den gruppering der er lavet i database fanen, som beskrives senere. Over laglisten er der mulighed for at tilføje nye lag, og reloade siden, hvis der er lavet noget der ikke vises rigtigt.

Til højre for lagoversigten findes styling vinduet. Her kan der for hvert lag laves en opsætning af kartografien på laget. Der er en class wizard, som kan bruges til at lave en hurtig opsætning, som så efterfølgende kan justeres.

I resten af fanen vises et kort, hvor de opsatte data kan se, når laget tændes i lag træet.

.. _gettingstarted_admin_database:

.. figure:: ../../../_media/gettingstarted-admin-map.png
    :width: 400px
    :align: center
    :name: gettingstarted-admin-map
    :figclass: align-center

    Map




Database
-----------------------------------------------------------------

I Databasefanen kan databasen administreres. Det er her de overordnede egenskaber på lag sættes og tabelstrukturen kan ændres.

.. figure:: ../../../_media/gettingstarted-admin-database.png
    :width: 400px
    :align: center
    :name: gettingstarted-admin-database
    :figclass: align-center

    Database

Laglisten
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Øverste del af fanen er rummer en linje med forskllige funktioner. Under linjen findes laglisten.

.. figure:: ../../../_media/gettingstarted-admin-database-layerlist.png
    :width: 400px
    :align: center
    :name: gettingstarted-database-layerlist
    :figclass: align-center

    Lagliste

Lags egenskaber kan ændres ved at dobbeltklikke på det felt i listen, som ønskes ændret.  

(1)Type: Lagets geometritype som kan være (MULTI)POINT, (MULTI)LINESTRING, (MULTI)POLYGON eller GEOMETRY. Sidste betyder, at laget kan have en blandning af flere forskellige typer. Lagets type kan ikke ændres.
(2)Navn: Det tekniske navn på laget. Hvis laget er importeret fra en fil svarer navnet på laget til filnavnet. Lagets tekniske navn kan ikke ændres.
(3)Titel: Lagets titel. Hvis titel er sat, er det den, som vises i lagtræ, signaturer, WMS/WFS titler mv.
(4)Beskrivelse: En beskrivende tekst til laget. Bruges i WMS/WFS abstract.
(5)Gruppe: Grupper anvendes til at inddele lagtræet i Map fanen og i Vieweren. Dette er combo felt: Enten skrives navnet på en ny gruppe eller der vælges en allerede eksisterende.
(6)Sort id: Placering af laget i laghierarki. Dvs. om et lag ligger ovenpå eller underneden et andet lag, når de vises sammen i Map fanen eller Vieweren.
(7)Authentication: Hvilket niveau af authentication ønskes for det enkelte lag i WMS og WFS tjenester? Write = authentication kun ved editering, Read/Write = authentication ved både læsning og editering, None = ingen authentication på laget.
(8)Skrivebar: Hvis slået fra, kan laget ikke editeres i Map fanen eller gennem WFS-T.
(9)Tile cache: Manuelt sletning af lagets tile cache. Dette er normalt ikke nødvendigt at gøre, da GC2 søger for sletning, når der er brug for det.

Tabelstruktur
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~


.. _gettingstarted_admin_workflow:

Workflow
-----------------------------------------------------------------

TBD

.. figure:: ../../../_media/gettingstarted-admin-workflow.png
    :width: 400px
    :align: center
    :name: gettingstarted-admin-workflow
    :figclass: align-center

    Workflow

.. _gettingstarted_admin_log:

Log
-----------------------------------------------------------------

TBD

.. figure:: ../../../_media/gettingstarted-admin-log.png
    :width: 400px
    :align: center
    :name: gettingstarted-admin-log
    :figclass: align-center

    Log