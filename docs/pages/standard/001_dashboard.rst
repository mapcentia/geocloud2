.. _dashboard:

#################################################################
Kontrolcenter/Dashboard
#################################################################

.. topic:: Overview

    :Date: |today|
    :GC2-version: 2022.9.1
    :Forfatter: `mapcentia <https://github.com/mapcentia>`_, `giovanniborella <https://github.com/giovanniborella>`_, `Bo Henriksen <https://github.com/BoMarconiHenriksen>`_

.. contents::
    :depth: 3

Når der er logget ind i GC2, så vises kontrolcenter/dashboardet.

Kontrolcenter er stedet hvor man i venstre side kan se en oversigt over skemaer eller konfigurationer i databasen. I højre side vises en oversigt over Sub-brugere. Der kan også tilføjes Sub-brugere.

I den blå topbar er der et spørgsmålstegn, som giver adgang til dokumentationen, og der kan åbnes en brugerprofil for den bruger der logget ind ved at klikke på brugernavnet. Se mere om brugerprofil her: :ref:`dashboard_userprofile`

.. figure:: ../../_media/gettingstarted-dashboard.png
    :width: 550px
    :align: center
    :name: gettingstarted-dashboard
    :figclass: align-center

    Kontrolcenter

.. _dashboard_schemas:

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

.. _dashboard_userprofile:

Brugerprofil
-----------------------------------------------------------------

Når der er logget ind i GC2, kan man tilgå sin brugerprofil i den blå topbar. Der klikkes på brugernavnet, og der åbnes en dialogboks, hvor der kan ses brugeroplysninger og skiftes password.

.. figure:: ../../_media/gettingstarted-userprofile.png
    :width: 550px
    :align: center
    :name: gettingstarted-userprofile
    :figclass: align-center

    Brugerprofil

.. _dashboard_subuser:

Subusers
-----------------------------------------------------------------

Her vises alle subusers. For at få mere information om subusers, kan du læse :ref:`subuser`

