.. _enableExtension:

#################################################################
Tilføj Extension
#################################################################

.. topic:: Overview

    :Date: |today|
    :GC2-version: 2022.11.0
    :Forfatter: `Bo Henriksen <https://github.com/BoMarconiHenriksen>`_

.. contents::
    :depth: 3

Tilføj Extension i Dockerfilen
=================================================================

Det er muligt at tilføje en extension ved at clone det repository, hvor koden til den pågældende extension er udviklet.
Repositoryet skal klones i app/extensions.

Det gøres i GC2 dockerfilen.

.. code-block:: dockerfile
  :name: tilføj-extension-eksempel

  RUN cd /var/www/geocloud2/app/extensions && git clone https://github.com/mapcentia/vidi_cookie_getter.git
  RUN cd /var/www/geocloud2/app/extensions && git clone https://github.com/mapcentia/traccar_api.git

Tilføj Extension Manuelt
-----------------------------------------------------------------

Hvis man under udvikling vil prøve en extension af, er det også muligt at tilføje det i et image, der kører.

Dette kan dog **ikke anbefales at gøre i produktion**, for når imaget lukker ned, så forsvinder den extension, du har tilføjet.

.. code-block:: sh
  :name: tilføj-extension-i-image

  # cd til din gc2 mappe
  docker-compose exec gc2core bash
  cd var/www/geocloud2/app/extensions
  git clone https://github.com/mapcentia/vidi_cookie_getter.git

Database Migrations
=================================================================

For nogen extensions kræver det, at der køres migrations i databasen. Det vil fremgå af beskrivelsen i repositoryet.

App.php
=================================================================

Derudover skal man også være opmærksom på, om der kræves konfiguration i :file:`/app/conf/App.php` filen. Det vil også fremgå i
beskrivelsen af extensionen i repositoryet.
