.. _metrics:

#################################################################
Prometheus Metrikker
#################################################################

.. topic:: Overview

    :Date: |today|
    :GC2-version: Unreleased
    :Forfatter: `giovanniborella <https://github.com/giovanniborella>`_

.. contents::
    :depth: 3

Udstilling
--------------------------------------------------------------

GC2 inkluderer en Prometheus-metrics server, der kan bruges til at overvåge GeoCloud2's ydeevne og brug. Metrics serveren kører på en separat port (standard: 9100) for at isolere den fra hovedapplikationstrafikken. Dette er en god praksis for sikkerhed og ydeevne..

Serveren kører på en separat port for at isolere den fra den offentlige trafik. Dette er en god praksis for sikkerhed og ydeevne.
For at aktivere metrics serveren, skal du tilføje følgende konfiguration i din `app/conf/App.php`:

```php
"metricsCache" => [
    "type" => "redis",
    "host" => "valkey:6379",
    "db" => 3
],
"enableMetrics" => true,
"metricsPort" => 9100,
"metricsHost" => "127.0.0.1",
```

Hvis metricsCache ikke er konfigureret, vil metrics serveren bruge en intern cache i hukommelsen.

Server
--------------------------------------------------------------

Metrics serveren kan startes ved at køre følgende kommando i terminalen:

```bash
# Install as a systemd service
sudo /var/www/geocloud2/app/scripts/manage-metrics-server.sh install

# Start the service
sudo /var/www/geocloud2/app/scripts/manage-metrics-server.sh start

# Check status
sudo /var/www/geocloud2/app/scripts/manage-metrics-server.sh status

```

Metrikker
--------------------------------------------------------------

Metrics endpointet eksponerer forskellige metrikker om GeoCloud2's ydeevne og brug, herunder:
- Wms

    - **Anmodningsvolumen-metrikker**
        - Samlet antal WMS/WFS-anmodninger: Sporer det samlede antal anmodninger behandlet efter servicetype (WMS, WFS, UTFGRID)
        - Anmodninger efter lag: Tæller anmodninger pr. lag for at identificere de mest hyppigt tilgåede lag
        - Fordeling af anmodningsmetoder: Sporer GET vs POST anmodninger for WFS-tjenester

    - **Ydeevne-metrikker**
        - Anmodningslatens: Måler hvor lang tid det tager at behandle forskellige typer anmodninger
            - Bruger histogrammer med passende intervaller (f.eks. 10ms, 50ms, 100ms, 250ms, 500ms, 1s, 2s, 5s)
            - Mærket efter servicetype (WMS/WFS/UTFGRID)
        - MapServer/QGIS Server behandlingstid: Sporer hvor lang tid den underliggende kortserver bruger på at behandle anmodninger
            - Dette hjælper med at identificere om forsinkelser er i GeoCloud2 eller i den underliggende kortserver

    - **Fejl-metrikker**
        - Fejlrate efter fejltype: Tæller forskellige typer fejl (autentificeringsfejl, ugyldige anmodninger, serverfejl)
        - HTTP-statuskoder: Tæller svar efter HTTP-statuskode

    - **Ressourceforbrug-metrikker**
        - Filterbrug: Sporer hvor ofte filtre anvendes på anmodninger
        - Oprettelse af midlertidige filer: Tæller midlertidige mapfiler oprettet
        - QGIS vs MapServer brug: Sporer hvilken backend der bruges til anmodninger

    - **Autentificering/Autorisation-metrikker**
        - Autentificeringsfejl: Tæller autentificerings/autorisationsfejl efter lag og bruger
        - Regelanvendelse: Sporer hvor ofte regler anvendes for at begrænse adgang

    - **Kardinalitets-metrikker**
        - Unikke brugere: Tæller unikke brugere der tilgår tjenesten
        - Unikke lag: Tæller unikke lag der tilgås

    - **Størrelsesmetrikker**
        - Svarstørrelse: Sporer størrelsen af svar sendt tilbage til klienter
        - Multi-lag-anmodninger: Sporer hvor mange lag der anmodes om i et enkelt kald



Disse og andre metrikker kan bruges til at oprette dashboards og alarmer i Grafana eller andre overvågningsværktøjer.