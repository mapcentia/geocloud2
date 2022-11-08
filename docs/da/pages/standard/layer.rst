.. _layer:

#################################################################
Lag
#################################################################

.. topic:: Overview

    :Date: |today|
    :GC2-version: 2022.9.1
    :Forfatter: `mapcentia <https://github.com/mapcentia>`_, `giovanniborella <https://github.com/giovanniborella>`_, `Bo Henriksen <https://github.com/BoMarconiHenriksen>`_

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

For at uploade vektor-filer og dermed danne lag på baggrund af disse, følges denne beskrivelse. Numre passer med :numref:`layer-create-vector`:

#. Klik på ``Tilføj filer``, eller træk flere filer ad gangen ind i dialogboksen. Følg hjælpeteksten i boksen.

#. Sæt koordinatsystem som filerne skal ende i . Dette kan f.eks. være ``25832`` for ``utm32`` eller ``4326`` for ``wgs84``.

#. Hvis man uploader ``.shp``-filer, er det en god idé at definere geometri-typen, ellers er der risiko for at geometrien bliver læst som blandet.

#. Hvis attributterne i datafilerne indeholder ``ASCII``, skal ``encoding`` defineres korrekt. Eller kan tegn ikke vises korrekt (``Æ``, ``Ø``, ``Å`` blandt andet.)

#. Hvis data indeholder fejl, så spring disse over.

#. Hvis der allerede eksisterer et lag med samme navn som filen man uploader, så overskriv det eksisterende lag.

#. Hvis der allerede eksisterer et lag med samme navn som filen man uploader, så overskriv ikke, men læg data i samme lag.

#. Hvis denne er slået til bliver et lag med samme navn som filen man uplader ikke overskrevet eller lagt til - men derimod tømt og indhold fra den nye fil bliver lagt i laget. Man kan bruge denne funktion til at undgå at skulle fjerne views der afhænger af laget.

#. Klik ``Start upload``

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

For at uploade raster-filer og dermed danne lag på baggrund af disse, følges denne beskrivelse. Numre passer med :numref:`layer-create-raster`:

#. Vælg fanen ``Tilføj Raster``
#. Klik på ``Tilføj filer``, eller træk flere filer ad gangen ind i dialogboksen. Følg hjælpeteksten i boksen.
#. Sæt koordinatsystem som filerne skal ende i . Dette kan f.eks. være ``25832`` for ``utm32`` eller ``4326`` for ``wgs84``.
#. Klik ``Start upload``

Efter upload er færdig, er de nye datasæt tilgængelige i ``Map``-fanen, hvor det har fået en standard-tematisering, og i ``Database``-fanen hvor det er muligt at ændre lagets egenskaber.

.. _layer_create_view:

Database-view
-----------------------------------------------------------------

Du kan skabe et view ovenpå en SELECT forespørgelse, der giver forespørgelsen et navn som du kan referere som en normal tabel. Views er meget veleget til fx at filtrere og sortere data, uden at skulle oprette en ny tabel.

Views kan bruges i næsten alle sammenhænge som en rigtig tabel.

Opret view
*****************************************************************

.. figure:: ../../../_media/layer-create-view.png
    :width: 400px
    :align: center
    :name: layer-create-map
    :figclass: align-center

    Opret nyt view

#. Klik på "Nyt lag".
#. Klik på "Database view".
#. Giv view'et et navn.
#. Skriv SELECT SQL, som skal definere view'et.
#. MATERIALIZE
#. Klik "Skab".

En tabel og view skal have en primær-nøgle. GC2 detekterer primær-nøgler på tabeller, men views har ikke primær-nøgler, så derfor falder GC2 tilbage på feltet "gid". Dvs. at et view skal have et felt "gid" med unikke værdier. Det skal også have et geometri-felt, så det dukker op i listen over lag (der er ingen krav til navngivningen af geometri-felter). Hvis en tabel er oprettet gennem GC2, vil tabellen have gid som primær-nøgle. Så en SELECT som denne vil virke:

``SELECT * FROM foo WHERE bar=1``

Udvælges der ikke med * skal gid og geometri-felt vælges:

``SELECT gid,the_geom FROM foo WHERE bar=1``

Der kan også skabes et "gid" felt med "As" syntax. Her bliver der skabt et view med ét punkt:

``SELECT 1 As gid, ST_SetSRID (ST_Point(-123.365556, 48.428611),4326)::geometry(Point,4326) AS the_geom``

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

*****************************************************************
Låse Lag Og Tildele Rettigheder
*****************************************************************

Som udgangspunkt er alle lag åbne. Hvis man vil beskytte et lag, er det muligt at låse det.
For at låse et lag vælges ``Read/Write`` i authentication kolonnen.

Det er muligt at sætte rettighederne på lagniveau. Disse rettigheder gælder når man tilgår laget igennem Vidi eller eksterne klienter.

Det er muligt at sætte følgende niveauer:

* ``Write`` - Laget kan læses af alle. For at editere skal brugere være logget ind i klienten.

* ``Read/Write`` - Laget kan kun læses af brugere med adgang. For at læse eller skrive skal brugeren være logget ind.

* ``None`` - Laget er åbent for alle.


Privilegier
=================================================================

Det er muligt at styre rettighederne for den enkelte subbruger under menupunktet ``Privilegier``.

.. image:: ../../../_media/layer-properties-privileges.png

Hvis man er logget ind som databaseuser, er det muligt at sætte rettighederne for den enkelte subbruger. Bemærk at dette ikke berører de lag, der i forvejen er sat til offentlige.

Eksempel - Authentication
=================================================================

I dette eksempel låses et lag og der tildeles rettigheder til det låste lag til en subbruger.

Der er lavet en subbruger , der hedder reader, som skal have læse rettigheder til et lag.

1. Login med din admin bruger, så du kan se alle subbrugere.
2. Tryk på tandhjulet for se de lag, der ligger under subbrugeren.

.. image:: ../../../_media/authentication-on-layer/click_on_gear.png

3. Tilføj authentication ved at klikke på et lag og ændre ``Write`` til ``Read/Write`` i ``Authentication`` kolonnen til højre. Dobbel klik på write.

.. image:: ../../../_media/authentication-on-layer/add_read_write_on_layer.png

4. Tildel rettigheder ved at klikke på et lag og derefter ``Privilegier`` i øverste venstre hjørne.

.. image:: ../../../_media/authentication-on-layer/click_on_layer_and_privilegier.png

5. Klik på ``Kun Læse``.

.. image:: ../../../_media/authentication-on-layer/add_read_privileger.png


For at kunne se det låse lag, skal der logges ind ved at trykke på låse ikonet på Vidi.

Kald Lag Med Authentication
=================================================================

Hvis du ønsker at kalde et lag, der har read/write authentication slået til kan det gøres på følgende måde.

1. Klik på den tandhjulet for den subbruger, der har laget, som er låst.

.. image:: ../../../_media/authentication-on-layer/click_on_gear.png

2. Click på ``Tjenester`` og tilføj et password i ``HTTP Basic Auth password for WMS and WFS`` og click opdater.

.. image:: ../../../_media/authentication-on-layer/basic_authentication.png

3. Det er derefter muligt at logge ind med subbrugeren og det password du lige har lavet, hvis du f.eks. kalder nedenståenden url:

https://myDomain.com/ows/[database]/[subbruger]/wfs?service=wfs&request=getfeature&version=2.0.0&TYPENAMES=[database:subbruger.lag&MAXFEATURES=1]

.. _layer_faq:

Oftest stillede spørgsmål
=================================================================

Under opsætning og drift kommer man tit ud i situationer hvor det kan være nødvendigt at justere opsætningen på et lag. Herunder er der nogle eksempler på ændringer.

TBD
