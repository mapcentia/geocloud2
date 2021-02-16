.. _subuser:

#################################################################
Subuser
#################################################################

.. topic:: Overview

    :Date: |today|
    :GC2-version: 2020.12.0
    :Forfatter: `giovanniborella <https://github.com/giovanniborella>`_

.. contents:: 
    :depth: 3


*****************************************************************
Subusers
***************************************************************** 

.. include:: ../../_subs/NOTE_GETTINGSTARTED.rst

Subuser
=================================================================

Når man indlesningsvis opretter en bruger på gc2, bliver dette toppen rettigheds-hierarkiet. Det er muligt at lave underbruger med forskellige rettigheder i databasen. Dette kan være hensigtsmæssigt hvis man har ikke-offentlige lag som skal deles med eksempelvis eksterne samarbejdspartnere.

Det er som udgangspunkt kun databasebrugeren der kan oprette subusers.

.. note::
    Selv om der opsættes meget få rettigheder for subuser, vil denne altid kunne se andre skemaer i samme database. Også selv om subuser ikke har rettighed til at tilgå nogen lag i de nævnte skemaer.

.. _subuser_create:

Opret subuser
-----------------------------------------------------------------

For at oprette en subuser, navigeres der til :ref:`gettingstarted_dashboard`.

.. figure:: ../../../_media/subuser-create.png
    :width: 400px
    :align: center
    :name: subuser-create
    :figclass: align-center

    Opret subuser

Der angives følgende:

* Brugernavn for subuser

* Mail-adresse - Denne kan benyttes til at genskabe password.

* Password x2 - Indledende kodeord for subuser.

* Nedarv rettigheder - Der er her muligt at koble rettighederne for den subuser man er ved at lave med en anden. Dette er nærmere beskrevet i næste afsnit.

Når man er færdig klikkes der på ``Gem``

Subuser som gruppe
-----------------------------------------------------------------

Et typisk use-case er hvor man ønsker at opdele adgangen til data mellem interne og eksterne interessenter.

I dette tilfælde vil man indledningsvis oprette 1 eller flere subusere som grupper. Man kan oprette en subuser ``ekstern`` som håndterer rettigheder for alle udenfor organisationen, ``intern_read`` for interne som ikke skal have skrive-rettigheder, og ``Admin`` for subusers som skal have rettighed til alt.

Når man efterfølgende opretter en subuser, vælger man hvilken "gruppe" den pågældende subuser skal nedarve rettigheder fra - altså hvilken gruppe subuseren skal være medlem af.

Når man efterfølgende skal håndtere de direkte privilegier i :ref:`layer_properties_privileges`, kan man nøjes med at sætte rettigheder for "gruppen" - og ikke den enkelte "bruger"

TOOD: add image to explain idea
