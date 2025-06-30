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

Hvis metricsCache ikke er konfigureret, vil metrics serveren bruge en intern cache. Det anbefales dog at bruge Redis for bedre ydeevne.

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
-

Disse og andre metrikker kan bruges til at oprette dashboards og alarmer i Grafana eller andre overvågningsværktøjer.