.. _layer:

#################################################################
Lag
#################################################################

.. topic:: Overview

    :Date: |today|
    :GC2-version: 2020.12.0
    :Forfatter: `mapcentia <https://github.com/mapcentia>`_, `giovanniborella <https://github.com/giovanniborella>`_

.. contents:: 
    :depth: 3


*****************************************************************
Lag
***************************************************************** 

.. include:: ../../_subs/NOTE_GETTINGSTARTED.rst

Alt data der udstilles igennem GC2 er opdelt i lag. Disse ligger i et skama, som igen ligger i en database. Databasen er oprettet når der oprettes en bruger, derefter kan der oprettes flere skemaer. Subusers kan også have deres eget skema, se mere her :ref:`subuser`.

Der findes flere forskellige lag:
* TBD

Før man begynder at lave lag i skemaet, er det en god idé at sørge for man ikke står i ``public``-skemaet. Dette skema har en bestemt betydning i PostgreSQL-databasen.

.. figure:: ../../../_media/layer-create-schema.png
    :width: 400px
    :align: center
    :name: layer-create-map
    :figclass: align-center

    Lav nyt skema

Start med at indtaste skemanavnet, og tryk derefter på ``+``-tegnet for at oprette et nyt skema. Derefter er det muligt at gå til det nye skema ved hjælp af drop-down.


.. _layer_create:

Nyt Lag
=================================================================

Man kan tilgå funktionen ``Nyt Lag`` (**1**) i enten :ref:`gettingstarted_admin_map` eller :ref:`gettingstarted_admin_database`.

.. figure:: ../../../_media/layer-create-map.png
    :width: 200px
    :align: center
    :name: layer-create-map
    :figclass: align-center

    ``Nyt Lag`` i ``Map``-fanen

.. figure:: ../../../_media/layer-create-database.png
    :width: 400px
    :align: center
    :name: layer-create-database
    :figclass: align-center

    ``Nyt Lag`` i ``Database``-fanen

Herfra er der flere muligheder for at danne nye lag til det aktive skema.


.. _layer_create_vector:

Vektor
-----------------------------------------------------------------

.. include:: ../../_subs/WARNING_OLD_DOC.rst

.. figure:: ../../../_media/layer-create-vector.png
    :width: 400px
    :align: center
    :name: layer-create-vector
    :figclass: align-center

    Vektor-lag dialog

For at uploade vektor-filer og dermed danne lag på baggrund af disse, følges denne beskrivelse. Numre passer med :numfig:`layer-create-vector`:

1. Klik på ``Tilføj filer``, eller træk flere filer ad gangen ind i dialogboksen. Følg hjælpeteksten i boksen.

2. Sæt koordinatsystem som filerne skal ende i . Dette kan f.eks. være ``25832`` for ``utm32`` eller ``4326`` for ``wgs84``.

3. Hvis man uploader ``.shp``-filer, er det en god idé at definere geometri-typen, ellers er der risiko for at geometrien bliver læst som blandet.

4. Hvis attributterne i datafilerne indeholder ``ASCII``, skal ``encoding`` defineres korrekt. Eller kan tegn ikke vises korrekt (``Æ``, ``Ø``, ``Å`` blandt andet.)

5. Hvis data indeholder fejl, så spring disse over.

6. Hvis der allerede eksisterer et lag med samme navn som filen man uploader, så overskriv det eksisterende lag.

7. Hvis der allerede eksisterer et lag med samme navn som filen man uploader, så overskriv ikke, men læg data i samme lag.

8. Hvis denne er slået til bliver et lag med samme navn som filen man uplader ikke overskrevet eller lagt til - men derimod tømt og indhold fra den nye fil bliver lagt i laget. Man kan bruge denne funktion til at undgå at skulle fjerne views der afhænger af laget.

9. Klik ``Start upload``

Efter upload er færdig, er de nye datasæt tilgængelige i ``Map``-fanen, hvor det har fået en standard-tematisering, og i ``Database``-fanen hvor det er muligt at ændre lagets egenskaber.

.. _layer_create_raster:

Raster & billede
-----------------------------------------------------------------

.. include:: ../../_subs/WARNING_OLD_DOC.rst

.. figure:: ../../../_media/layer-create-raster.png
    :width: 400px
    :align: center
    :name: layer-create-raster
    :figclass: align-center

    Raster- & Billed-lag dialog

For at uploade raster-filer og dermed danne lag på baggrund af disse, følges denne beskrivelse. Numre passer med :numfig:`layer-create-raster`:

1. Vælg fanen ``Tilføj Raster``
2. Klik på ``Tilføj filer``, eller træk flere filer ad gangen ind i dialogboksen. Følg hjælpeteksten i boksen.
3. Sæt koordinatsystem som filerne skal ende i . Dette kan f.eks. være ``25832`` for ``utm32`` eller ``4326`` for ``wgs84``.
4. Klik ``Start upload``

Efter upload er færdig, er de nye datasæt tilgængelige i ``Map``-fanen, hvor det har fået en standard-tematisering, og i ``Database``-fanen hvor det er muligt at ændre lagets egenskaber.

.. _layer_create_view:

Database-view
-----------------------------------------------------------------

TBD

.. _layer_create_osm:

OSM
-----------------------------------------------------------------

TBD

.. _layer_create_blank:

Tomt
-----------------------------------------------------------------

TBD

.. _layer_create_qgis:

QGIS-lag
-----------------------------------------------------------------

TBD - ref to qgis

.. _layer_properties:

Egenskaber
=================================================================

TBD

.. _layer_properties_privileges:

Privilegier
-----------------------------------------------------------------

.. figure:: ../../../_media/layer-properties-privilegier.png
    :width: 400px
    :align: center
    :name: layer-properties-privilegier
    :figclass: align-center

    Privilegier

Det er muligt at styre rettighederne for den enkelte subuser under menupunktet ``Privilegier``

Hvis man er logget ind som databaseuser er det muligt at sætte rettighederne for den enkelte subuser. Bemærk at dette ikke berører de lag der i forvejen er sat til offentlige. læs mere under :ref:`layer_properties_authentication`

.. _layer_properties_meta:

Meta
-----------------------------------------------------------------

TODO: grab from 08-setup-meta-vidi

.. _layer_properties_authentication:

Authentication
-----------------------------------------------------------------

Det er muligt at sætte rettighederne på lagniveau. Disse rettigheder gælder når man tilgår laget igennem Vidi eller eksterne klienter.

Det er muligt at sætte følgende niveauer:

* ``Write`` - Laget kan læses af alle. For at editere skal brugere være logget ind i klienten.

* ``Read/Write`` - Laget kan kun læses af brugere med adgang. For at læse eller skrive skal brugeren være logget ind.

* ``None`` - Laget er åbent for alle. 

Man kan yderligere styre rettigheder i :ref:`layer_properties_privileges`

.. _layer_faq:

Oftest stillede spørgsmål
=================================================================

Under opsætning og drift kommer man tit ud i situationer hvor det kan være nødvendigt at justere opsætningen på et lag. Herunder er der nogle eksempler på ændringer.

TBD
