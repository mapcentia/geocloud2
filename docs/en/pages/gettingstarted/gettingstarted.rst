.. _gettingstarted:

============================================================
Documentation for GC2
============================================================

Here you can find documentation for GeoCloud2

In this document you can learn hoow GC2 is used, and how the different parts work.

API documentation for GC2 is made in swagger. This means that teh documentation on the site you work on. To find the dokumentation you will have to navigate to an url at your site. The url is made like this <GC2 site url>/swagger-ui/

It is important that you use the API-documentation on your actual site. IT will always apply to the version of GC2 that you develop on. `Here you can see an example of the dokumentation. <https://dk.gc2.io/swagger-ui/>`_


*****************************************************************
Get started using GC2
*****************************************************************

.. topic:: Overview

    :Date: |today|
    :GC2-version: 2020.12.0
    :Forfatter: `GEOsmeden <https://github.com/geosmeden>`_,

.. contents:: 
    :depth: 3


What is GC2?
================================================================= 

GeoCloud2, hereafter GC2 will be used, is an enterprise platform for handling geospatial data, map-visualisation and spatiale tools. The platform is built on the best opensource and standard based programs.

GC2 makes it easy to start using PostGIS, MapServer, QGIS Server, MapCache, Elasticsearch, GDAL/OGR2OGR. User interface to GC2, is a simple web-interface for administration of the software stack.

The aim for GC2 is to make it easy for organisations to use opensource tools to build a geospatial infrastructure.

To read more go to :ref:`readme`

Get started
=================================================================

The url of your site is chosen when GC2 is installed. Navigate to the site url to get started.


.. _gettingstarted_login:

Log ind
-----------------------------------------------------------------

Start by signing into GC2 at the front page. You can login as the Database user or a sub user.

The first thing you see is the Dashboard, where a list of database schemas are displayed. Schemas represent a logical division of the database. Schema 'public' is a standard schema that always exist in the database, and should normally not be used.

enther the username and press ``Enter password``. It will then be checked if the user exist. If it does it will be possible to enter the password. after that press ``Sign in``.

If login succeeds it will automatically open the dashboard :ref:`dashboard`

.. figure:: ../../../_media/en/gettingstarted-login.png
    :width: 400px
    :align: center
    :name: gettingstarted-login
    :figclass: align-center

    Log ind

.. _gettingstarted_register:

Register/create databaseuser/new database
-----------------------------------------------------------------

.. note::
  If you are looking for subusers, read more here: :ref:`subuser`

A databaseuser is the owner of the database where data resides. It is this user that normally is used for administration of the site.

To create a database-user u press the ``Register``. Fill in the registration form to create a database user and the new database.

When done use the information to to log in.

.. figure:: ../../../_media/en/gettingstarted-register.png
    :width: 400px
    :align: center
    :name: gettingstarted-register
    :figclass: align-center

    Opret databasebruger

.. _gettingstarted_dashboard:

Dashboard
=================================================================

When you are logge in to GC2, then u see the dashboard. 

The Dashboard is the place where there in the left side is a list of the schemes og configurations in the database. In the right side is a list of subusers. You can also create subusers here.

In the blue topbar is at questionmark, this gives acces to this documentation, and beside is the usenmae of the profile that is logged in. If you click the username it will open a userprofile. Read more ablout userprofile here: :ref:`gettingstarted_userprofile`

.. figure:: ../../../_media/en/gettingstarted-dashboard.png
    :width: 550px
    :align: center
    :name: gettingstarted-dashboard
    :figclass: align-center

    Kontrolcenter

Schemes
-----------------------------------------------------------------

Each scheme in the database will be shown. there is a filter option to filter the list.

The scheme ``public`` is always created when the dabase is created, and should not normally be used.

If you click on a scheme it is unfolded and it is possible to either:

* Open Vidi with the layers in the current scheme.
* Go to the administration page(the gear).


Configurations
-----------------------------------------------------------------

Configurations is json files that is stored in the database. Configurations are used to control the Vidi viewer. So this is where you control which layers and background layers is displayed, and which extensions are available. 

The configurations are create here and must have a name, it can be supplied with a description too.

For more information about the possibilities in the configurations, read the section in the Vidi dokumentation (currently only in danish) `Vidi kørselskonfiguration <https://vidi.readthedocs.io/da/latest/pages/standard/91_run_configuration.html>`_

.. _gettingstarted_userprofile:

User profile
-----------------------------------------------------------------

When you are logged in to GC2, You can view the userprofile in the blue topbar. Click on the username, and a dialog will open, where it is possible to view user information and change password.

.. figure:: ../../../_media/en/gettingstarted-userprofile.png
    :width: 550px
    :align: center
    :name: gettingstarted-dashboard
    :figclass: align-center

    Brugerprofil

Subusers
-----------------------------------------------------------------

This is a list of all subusers. Read more about subusers here :ref:`subuser`

.. _gettingstarted_admin:

Administration module
=================================================================

Administration module is divided in tabs. The tabs are explained below.

.. _gettingstarted_admin_map:

Map
-----------------------------------------------------------------

In the tab "Map" on the left side you can see an overview of the layers in the scheme. If the layers are not set up yet, they are found under ungrouped. Otherwise, they are found in the grouping made in the database tab, which is described later. Above the layer list, it is possible to add new layers and reload the page if something has been made that does not display correctly.

To the right of the layer overview is the styling window. Here, a setup of the cartography on the layer can be made for each layer. There is a class wizard which can be used to make a quick setup, which can then be adjusted afterwards.

In the rest of the tab, a map is displayed where the set data can be seen when the layer is switched on in the layer tree.

.. _gettingstarted_admin_database:

.. figure:: ../../../_media/en/gettingstarted-admin-map.png
    :width: 690px
    :align: center
    :name: gettingstarted-admin-map
    :figclass: align-center

    Map




Database
-----------------------------------------------------------------

In the Database tab, the database can be managed. This is where the overall layer properties are set and the table structure can be changed.

.. figure:: ../../../_media/en/gettingstarted-admin-database.png
    :width: 690px
    :align: center
    :name: gettingstarted-admin-database
    :figclass: align-center

    Database

Layer list
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The upper part of the tab contains a line with different functions. Below the line is the layer list.

.. figure:: ../../../_media/en/gettingstarted-admin-database-layerlist.png
    :width: 690px
    :align: center
    :name: gettingstarted-database-layerlist
    :figclass: align-center

    Layer list

Layer properties can be changed by double-clicking on the field in the list that you want to change.

1. Type: The geometry type of the layer which can be (MULTI)POINT, (MULTI)LINESTRING, (MULTI)POLYGON or GEOMETRY. The latter means that the layer can have a mixture of several different types. The layer type cannot be changed.
#. Name: The technical name of the layer. If the layer is imported from a file, the name of the layer corresponds to the file name. The technical name of the layer cannot be changed.
#. Title: The title of the team. If title is set, it is the one that appears in the layer tree, signatures, WMS/WFS titles, etc.
#. Description: A descriptive text for the layer. Used in WMS/WFS abstract.
#. Group: Groups are used to divide the layer tree in the Map tab and in the Viewer. This is a combo field: Either write the name of a new group or select an existing one.
#. Black id: Position of the layer in layer hierarchy. That is whether a layer lies above or below another layer when they are displayed together in the Map tab or the Viewer.
#. Authentication: What level of authentication is desired for the individual layer in WMS and WFS services? Write = authentication only when editing, Read/Write = authentication when both reading and editing, None = no authentication on the layer.
#. Writable: If turned off, the layer cannot be edited in the Map tab or through WFS-T.
#. Tile cache: Manual deletion of the layer's tile cache. This is usually not necessary to do as GC2 checks for deletion when needed.



Tabelstructure
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When a layer in the layer list is selected, the layer's table structure is displayed in the section below. Properties can be set on the columns here. Properties can be changed by double-clicking on the field in the list that you want to change. Columns can be added and deleted.

.. figure:: ../../../_media/en/gettingstarted-admin-database-table-structure.png
    :width: 690px
    :align: center
    :name: gettingstarted-database--tablestructure
    :figclass: align-center

    Tabelstruktur

1. Sort id: In which order the columns can be displayed in queries in the Viewer. Columns with lower sort id are displayed at the top.
#. Column: Name of the column. The name can be changed, but consider using Alias ​​(4) instead.
#. Type: The type of the column. Can not be changed.
#. ALLOW NULL:
#. Alias: An alias for the column. Shown for queries in the Viewer.
#. Show in click-info: Should the column be displayed for queries in the Viewer? The starting point is that all columns are displayed. If these properties are changed, only those that are checked will be displayed.
#. SHOW IN MOUSE OVER:
#. SEARCHABLE:
#. ENABLE FILTERING:
#. Make a link: If the content in the column is a link, it can be made active in the Viewer by queries.
#. IMAGES:
#. Link prefix: If links e.g. are missing "http://" this can be added here.
#. PROPERTIES:
#. Properties: Can contain arbitrary information for use in custom applications.
#. Add New Column: Add a new column to the layer's table.
#. Delete Column: Delete the selected column.

Flyt lag mellem schemaer
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. figure:: ../../../_media/gettingstarted-admin-database-movelayer-schema.png
    :width: 690px
    :align: center
    :name: gettingstarted-database-layerlist
    :figclass: align-center

    Flyt lag mellem schemaer

1. Vælg et eller flere lag på laglisten (hold Shift eller Ctrl nede for at vælge flere) og klik "Flyt lag".
#. Vælg hvilket schema de skal flyttes til.

Omdøb lag
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. figure:: ../../../_media/gettingstarted-admin-database-rename-layer.png
    :width: 690px
    :align: center
    :name: gettingstarted-database-layerlist
    :figclass: align-center

    Omdøb lag
	
1. Vælg et enkelt lag og klik "Omdøb layer".
#. Vælg et nyt navn til laget.

Skab tabel fra bunden
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Du kan skabe en ny tom tabel fra bunden ved først at klikke på nyt lag

.. figure:: ../../../_media/gettingstarted-admin-database-create-table.png
    :width: 690px
    :align: center
    :name: gettingstarted-database-layerlist
    :figclass: align-center

    Klik nyt lag
	
	
.. figure:: ../../../_media/gettingstarted-admin-database-create-table-dialog.png
    :width: 690px
    :align: center
    :name: gettingstarted-database-layerlist
    :figclass: align-center

    Nyt lag dialogboks
	
1. Klik på Blank layer.
#. giv den nye tabel et navn.
#. Sæt EPSG kode for geometri-feltet.
#. Sæt type kode for geometri-feltet.

Hvis du vil have en tabel uden geometri, så slettes geometri-feltet bare efter tabellen er oprettet.


.. _gettingstarted_admin_versioning:

Versionering af data (Track changes)
-----------------------------------------------------------------

Ved versionering af data beholdes alle ændringer i tabellen. Dvs. at den fulde transaktions-historik beholdes. Versionering gør brugerne i stand til at gå tilbage i historien og se hvordan et lag så ud på et bestemt tidspunkt. Versionering virker også som journalisering af alle transaktioner foretaget på laget.

Et versioneret lag fungerer ligesom alle andre lag og kan redigeres på normal vis. GC2 tager sig af versioneringen i baggrunden.

Versionering foregår i WFS laget. Dvs. at det fungerer både ved redigering i GC2's Map fane, men også igennem eksterne WFS editors som f.eks. QGIS.

.. include:: ../../_subs/WARNING_OLD_DOC.rst

**Start "Tracking changes" på et lag**

.. figure:: ../../../_media/versioning-start-tracking-changes.png
    :width: 600px
    :align: center
    :name: versioning-start-tracking-changes
    :figclass: align-center

    start versioning

1. Vælg lag ved at klikke på linjen så den bliver grå.
#. Klik på "Start versionering".

**Nye system-felter i tabellen**

.. figure:: ../../../_media/versioning-system-fields.png
    :width: 600px
    :align: center
    :name: versioning-system-fields
    :figclass: align-center

    Nye felter

Lagets tabel får fem nye system-felter (system-felter starter altid med "gc2_"). Felter indeholder versionsdata for hver enkelt feature i tabellen. System-felterne er:

**gc2_versions_gid** Er en nøgle, som relaterer en features forskellige versioner. Dvs. er to versioner af denne samme feature, har samme nøgleværdi.

**gc2_version_start_date** Er et tidsstempel, som angiver hvornår versionen er oprettet. Alle features har en værdi i dette felt.

**gc2_version_end_date** Er et tidsstempel, som angiver hvornår versionen er afsluttet. Kun afsluttede versioner har en værdi i dette felt og den aktuelle version har ikke værdi i dette felt.

**gc2_version_uuid** Er en "universally unique identifier" som alle versioner får tildelt. Denne værdi er global unik.

**gc2_version_user** Er den (sub-)bruger, der har skabt versionen.


**Versionsdata i tabellen**

.. figure:: ../../../_media/versioning-versiondata.png
    :width: 600px
    :align: center
    :name: versioning-versiondata
    :figclass: align-center

    versionsdata

Dette er et eksempel på versionsdata i tabellen. Denne versionerede tabel har tre nye punkter, som hver har dato/tid for oprettelsen (gc2_version_start_date), et unikt id (gc2_version_uuid) samt hvilken bruger, der har oprettet punkterne (gc2_version_user).

**Se alle versioner**

.. figure:: ../../../_media/versioning-see-all-versions.png
    :width: 600px
    :align: center
    :name: versioning-see-all-versions
    :figclass: align-center

    versionsdata

Som standard vises kun aktuelle features, dvs. dem uden en gc2_version_end_date. Det er muligt at se alle features på en gang.

1. Start redigering af laget.
#. Vælg "all" under "Time slicing", som skal tjekkes af i boksen.
#. Load features.

.. figure:: ../../../_media/versioning-example.png
    :width: 600px
    :align: center
    :name: versioning-example
    :figclass: align-center

    Eksempel på versioner
	
Eksemplet viser to aktuelle punkter. Endvidere ses det, at punktet med **gc2_version_gid** = 2 findes i to versioner: En aktuel og en afsluttet. Dvs. at punktet har været redigeret. Punktet med **gc2_version_gid** = 1 er afsluttet og der er ikke andre versioner af dette punkt. Dvs. at punktet er slettet.

Afsluttede versioner vise med rød, stiplet kant. Disse kan ikke redgieres.

**Time slicing**
	
.. figure:: ../../../_media/versioning-timeslicing.png
    :width: 600px
    :align: center
    :name: versioning-timeslicing
    :figclass: align-center

    Timeslicing
	
Det er muligt at se hvordan et lag så ud på et bestemt tidspunkt.

1. Start redigering af laget.
#. Tjek "Time slicing" af og skriv en dato/tid i formatet yyyy-mm-dd hh:mm:ss fx 2015-06-30 14:34:00. Undlades tiden vil den blive sat til 00:00:00.
#. Load features.
	
	
.. figure:: ../../../_media/versioning-timeslicing-example.png
    :width: 600px
    :align: center
    :name: versioning-timeslicing-example
    :figclass: align-center

    Timeslicing eksempel
	
Ved Time slicing vises de versioner, som var aktuelle på den pågældende dato/tid. Dvs. at der kun vises én version pr. feature (**gc2_version_gid** værdierne er unikke). Eksemplet viser, at der var tre aktuelle punkter, hvoraf et stadig er aktuelt (det blå) og to, som senere enten er ændret eller slettet (røde). For de ændrede punkter viser **gc2_version_end_date** tidspunktet for ændringen.
	
	**Versionering i ekstern editor fx QGIS**
	
	
.. figure:: ../../../_media/versioning-example-qgis.png
    :width: 600px
    :align: center
    :name: versioning-example-qgis
    :figclass: align-center

    versionering i QGIS

Versionering foregår i WFS laget. Dvs. at det fungerer både ved redigering i GC2's Map fane, men også igennem eksterne WFS editors som f.eks. QGIS.

Ved brug af standard WFS forbindelsesstrengen vises kun de aktuelle versioner. Dvs. at dette er ikke andersledes end for ikke-versionerede lag:

http://example.com/wfs/mydb/public/4326

Hvis alle versioner skal vises i QGIS bruges denne streng:

http://example.com/wfs/mydb/public/4326/all

Og ved Time slicing bruges denne: (Bemærk "T" mellem dato og tid)

http://example.com/wfs/mydb/public/4326/2015-06-30T14:34:00


.. _gettingstarted_admin_workflow:

Workflow management
-----------------------------------------------------------------

Workflow giver mulighed for at kontrollere redigeringen af et lag i en typisk forfatter-redaktør-udgiver kæde.

Et lag under workflow kontrol fungerer ligesom alle andre lag og kan redigeres på normal vis. GC2 tager sig af workflowet i baggrunden.

Workflow foregår i WFS laget. Dvs. at det fungerer både ved redigering i GC2's Map fane, men også igennem eksterne WFS editors som f.eks. QGIS.

**Start "track changes" på laget.**

.. figure:: ../../../_media/workflow-start-tracking-changes.png
    :width: 600px
    :align: center
    :name: workflow-start-tracking-changes
    :figclass: align-center

    start versioning
	
Workflow bygger oven på versionerings-systemet i GC2, så det er nødvendigt at starte "Track changes" på laget.

1. Vælg et lag ved at klikke på linjen så den bliver grå.
#. Klik på "Track changes". Læs mere om "Track changes"
#. VIGTIGT! Husk at sætte Authentication niveauet til "Read/write" på laget.	
	
**Tildel privilegier til sub-brugere**
	
.. figure:: ../../../_media/workflow-add-privileges.png
    :width: 600px
    :align: center
    :name: workflow-add-privileges
    :figclass: align-center

    Workflow
	
De sub-brugere, som skal have en rolle i workflowet, skal have tildelt privilegier til laget. Læs mere om sub-brugere og privilegier.

1. Vælg et lag ved at klikke på linjen så den bliver grå.
#. Klik på "Privilegier".
#. Sæt privilegiet til "Læse og skrive" (eller "Alle") for hver sub-bruger, der skal have en rolle i workflowet.
	
**Start Workflow på laget**
	
.. figure:: ../../../_media/workflow-start-workflow.png
    :width: 600px
    :align: center
    :name: workflow-start-workflow
    :figclass: align-center

    Start Workflow
	
1. Start workflow på laget ved at klikke på "Workflow".
#. Workflow dialogen vises.
#. Lagets tabel får to nye system-felter (system-felter starter altid med "gc2_"). Felterne indeholder workflow-data for hver enkelt feature i tabellen. Felterne er:

``gc2_status`` Indeholder featurens status, som er enten: 1 = Draft, 2 = Reviewed eller 3 = Published.

``gc2_workflow`` Indeholder workflow-kæden.

**Tildel roller i Workflowet**
	
.. figure:: ../../../_media/workflow-add-roles.png
    :width: 600px
    :align: center
    :name: workflow-add-roles
    :figclass: align-center

    Workflow roller
	
En sub-bruger kan have en af følgende roller i et workflow:

**Author** Kan oprette nye features. Kan også ændre en feature, som IKKE er Reviewed eller Published. Dvs. som ikke er kommet videre i workflowet.

**Reviewer** Kan ændre eller godkende en feature. Kan IKKE ændre en feature, som er Published.

**Publisher** Kan ændre eller godkende en feature til endelig udgivelse.

Sub-brugere, som ikke har en rolle i workflowet, kan ikke lave ændringer i laget.

En rolle kan varetages af to eller flere brugere. Fx kan et lag have to Authors.

Bemærk, at det ikke er nødvendigt, at alle roller er besatte. Fx hvis man ønsker at springe Reviewer ledet over, kan dette gøres.
	
	
**Workflow fanen**
	
.. figure:: ../../../_media/workflow-workflow-tab.png
    :width: 600px
    :align: center
    :name: workflow-workflow-tab
    :figclass: align-center

    Workflow fanen
	
Når et lag er under workflow-kontrol kan alle transaktioner på laget ses i fanen "Workflow". Hver linje er en transaktion. Listen viser kun transaktioner, som er relavante for brugeren, dvs. dem som brugeren skal tage action på.

Eksemplet viser en transaktion på et punkt foretaget af Lilly, som er Author.

Hver transaktion har følgende værdier:

**Operation** Er hvilken operation, der er foretaget på laget: insert, update eller delete.

**Table** Hvilken tabel transaktionen udført på.

**Fid**  Primærnøglens værdi på den feature transaktionen er udført på.

**Version id** Versions-id'ets værdi på den feature transaktionen er udført på.

**Status** Den status featuren har efter transaktionen.

**Latest edit by** Den sub-bruger, som har udført transaktionen.

**Authored by** Den Author, der har oprettet featuren.

**Reviewed by** Den Reviewer, som har godkendt featuren.

**Published by** Den Publisher, som har godkendt featuren.

**Created** Transaktionen tidsstempel.

Knapper i Workflow

1. **Show all**. Viser alle transaktioner. Også den, som ikke er aktuelle for brugeren.
#. **See/edit feature**. Skifter til Map fanen og loader featuren.
#. **Check feature**. Godkender featuren. Svarer til en update af featuren uden at ændre den.
	
	
**Efter "Review"**
	
.. figure:: ../../../_media/workflow-after-review.png
    :width: 600px
    :align: center
    :name: workflow-after-review
    :figclass: align-center

    Workflow status
	
Eksemplet viser et punkt, som er reviewed (godkendt af Reviewer Carl). Punktet har nu status 2.


**Efter "Publish"**
	
.. figure:: ../../../_media/workflow-after-publish.png
    :width: 600px
    :align: center
    :name: workflow-after-publish
    :figclass: align-center

    Workflow status published
	
Eksemplet viser et punkt, som er published (godkendt af Publisher Julie). Punktet har nu status 3.
	
**Overspring i Workflow kæden**
	
.. figure:: ../../../_media/workflow-skip-step.png
    :width: 600px
    :align: center
    :name: workflow-skip-step
    :figclass: align-center

    Workflow overspring
	
Det er muligt for en bruger at springe et lavere led i workflow-kæden over. Eksemplet viser, at reviewer Carl er sprunget over af publisher Julie.
	
**Workflow information i data**
	
.. figure:: ../../../_media/workflow-display-data.png
    :width: 600px
    :align: center
    :name: workflow-display-data
    :figclass: align-center

    Workflow visning i data
	
Workflow informationerne til de enkelte features bliver gemt i lagets tabel i felterne **gc2_status** og **gc2_workflow**.

**gc2_status** Angiver status 1-3.

**gc2_workflow** Indeholder workflow-kæden. Kæden er en liste i formen fx: "author"=>"lilly", "reviewer"=>"carl" Denne kæde viser, at punktet er oprettet af Lilly, reviewed af Carl, men stadig ikke godkendt af en publisher.
	
**Brug af ekstern editor i Workflow**
	
.. figure:: ../../../_media/workflow-in-qgis.png
    :width: 600px
    :align: center
    :name: workflow-in-qgis
    :figclass: align-center

    Workflow i QGIS
	
Workflow foregår i WFS laget. Dvs. at det fungerer både ved redigering i GC2's Map fane, men også igennem eksterne WFS editors som f.eks. QGIS.

Laget hentes ind og redigeres på sædvanlig vis i QGIS.
	
	
	
	
	
	
	
	
	

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