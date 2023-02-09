.. _localDevelopment:

#################################################################
Lokal udvikling
#################################################################

.. topic:: Overview

    :Date: |today|
    :GC2-version: 2023.2.0
    :Forfatter: `Bo Henriksen <https://github.com/BoMarconiHenriksen>`_

.. contents::
    :depth: 3

Lokalt Udviklingsmiljø
=================================================================

Denne guide viser, hvordan man sætter et lokalt udviklingsmiljø op for GC2.

Til det bruger vi `VS Code's Dev Containers <https://code.visualstudio.com/docs/devcontainers/containers>`_ udvidelse. Det giver mulighed for at udvikle inde i containeren.

Krav
=================================================================

Windows
-----------------------------------------------------------------

- Docker Desktop 2.2+ - Download og installer [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- WSL2 backend - Installeres fra Windows Store. [Docker Desktop setup](https://docs.docker.com/desktop/windows/wsl/)
- VS Code - Download og installer [VS Code](https://code.visualstudio.com/)
- VS Code Extensions Dev Containers created by Microsoft

MacOS
-----------------------------------------------------------------

- Docker Desktop 2.0+
- VS Code - Download og installer [VS Code](https://code.visualstudio.com/)
- VS Code Extensions Dev Containers created by Microsoft

Linux
-----------------------------------------------------------------

- Docker CE/EE 18.06+ and Docker Compose 1.21+. (Ubuntu Snap package er ikke supportet)
- VS Code - Download og installer [VS Code](https://code.visualstudio.com/)
- VS Code Extensions Dev Containers created by Microsoft

Git
=================================================================

Sørg for at git er sat rigtigt op ift. origin og upstream.

Origin
-----------------------------------------------------------------

git remote add origin [URL_OF_YOUR_FORK]

Eksempel:
git remote add origin https://github.com/JohnDoe/geocloud2.git

Upstream
-----------------------------------------------------------------

git remote add upstream [URL_OF_MAPCENTIA_PROJECT]

Eksempel:
git remote add upstream https://github.com/mapcentia/geocloud2.git

Tjek at du har de rigtige url'er
git remote -v

Hent Tags Upstream
-----------------------------------------------------------------

git fetch --tags upstream

Det er nu muligt at lave et image, der starter på en specifik version. Du kan evt. pushe tagsene til din egen fork.

git push --tags origin

GC2 Image
=================================================================

Du starter med at lave et GC2 image. Det gør du ved at gå til mappen :file:`docker/development/Dockerfile`.

I Dockerfilen tilføjer du dit github brugernavn, så koden der clones kommer fra din fork.

Det gøres i følgende step:

RUN cd /var/www/ &&\
  git clone https://github.com/[ADD_GITHUB_USERNAME]/geocloud2.git --branch master

Hvis du lavet en remote branch til din feature/bug fix så fjern --tags og lav en chekout på den pågældende branch.
Det gøres i følgende step:

cd /var/www/geocloud2 &&\
  git fetch --tags &&\
  git checkout tags/2022.11.0

Ændres til:

cd /var/www/geocloud2 &&\
  git fetch &&\
  git checkout docBugFix

Hvis du skal arbejde med en extension, så tilføj dit github brugernavn for den pågældende extension.

Derefter kører du scriptet :file:`buildGc2Image.sh dev`

I docker-compose filen i samme mappe skal du være sikker, at tagget for det image du lige har bygget, er det samme som
det tag i service: :file:`gc2core`. Ændres f.eks. til gc2core:dev

Start Dev Containers
=================================================================

- Klik på filen devcontainer.json i mappen .devcontainer.
- Tryg F1 og skriv devcontainer og vælg :file:`Rebuild and reopen in container`. Hvis devcontaineren har været bygget vælges :file:`Reopen in Container`.

Når devcontaineren er bygget åbner VS Code et nyt vindue, hvor du ser koden inde i devcontaineren.

Start GC2 Vidi
=================================================================

I det VS Code vindue, der åbnede op åbner du en terminal og cd'er til development mappen :file:`cd docker/development`.

Derefter skriver du :file:`docker-compose up` for at starte GC2 Vidi.

Forbind Til gc2core Containeren
=================================================================

I nederste venstre hjørne klikker du på :file:`Dev Container: Docker in Docker` og vælger :file:`Attach to container`.
Derefter vælger du :file:`development_gc2core` containeren.

Et nyt VS Code vindue åbner op, og du er nu inde i gc2core containeren.

OBS! Hvis du bliver bedt om at vælge en mappe så vælg :file:`root`. Derefter skriver du:

cd ..

cd var/www/geocloud2

code . (for at åbne et nyt VS Code vindue)

I browseren går du til http://localhost:8080

Det ser ud til, at ændringer i koden gemmes i imaget, men hvis du vil være sikker på, ikke at miste din kode
så sørg for at comitte og pushe, til din remote branch, ofte.
