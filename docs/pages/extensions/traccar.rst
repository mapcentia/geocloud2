.. _traccar:

#################################################################
Traccar API Extension
#################################################################

.. topic:: Overview

    :Date: |today|
    :GC2-version: 2022.11.0
    :Forfatter: `Bo Henriksen <https://github.com/BoMarconiHenriksen>`_

.. contents::
    :depth: 3

Hvad Er Traccar?
=================================================================

`Traccar <https://www.traccar.org/>`_ er en open source GPS tracking platform.

Det er muligt at placere en gps sender i f.eks. en bil, hvorefter bilens position sendes til en traccar server.

Så for at kunne bruge Traccar extensionen kræves det, at man har adgang til en Traccar server.

Traccar API extensionen kan hente de positioner som er sendt til Traccar serveren og udstille dem i et lag.

Tilføj Traccar API i Dockerfilen
=================================================================

`Traccar <https://github.com/mapcentia/traccar_api>`_ extensionen tilføjes i GC2 dockerfilen.

.. code-block:: dockerfile
  :name: tilføj-traccar-api

  RUN cd /var/www/geocloud2/app/extensions && git clone https://github.com/mapcentia/traccar_api.git

Traccar_API Konfiguration i App.php
=================================================================

I /app/conf/App.php indsættes følgende:

.. code-block:: php
  :name: traccar-api-opsætning

  "traccar" => [
    "db1" => [
        "baseUri" => "http://myhost1.com:80",
        "host" => "myhost1.com",
        "token" => "......",
    ],
    "db2" => [
        "baseUri" => "http://myhost2.com:80",
        "host" => "myhost2.com",
        "token" => "......",
    ],
  ],

Database Migrations
=================================================================

For at traccar API'et kan gemme positioner i databasen, skal der køres en `database migration <https://github.com/mapcentia/traccar_api/blob/main/model/schema.sql>`_

1. Åben din database klient og log på den database, hvor du vil tilføje traccar api'et.
2. Lav et nyt schema og kald det traccar.
3. Lav et nyt sql script og indsæt `traccar sql scriptet <https://github.com/mapcentia/traccar_api/blob/main/model/schema.sql>`_.
4. Kør scriptet.

Opret Et Cronjob
=================================================================

For at hente positioner kaldes traccar_api'et, der har følgende endpoint:

https://gc2.myDomain.com/extensions/traccar_api/controller/traccar/[database]

Der sættes et cronjob op, så det er muligt kontinuerligt at hente positioner.

Her er et eksempel på et script, der kan bruges som et cronjob. I nedenstående ekempel sendes en mail, hvis Traccar serveren returner en fejl.
For at kunne sende en mail, skal du oprette en bruger hos `Sendgrid <https://sendgrid.com/>`_. Det er muligt, at *send 100 mails gratis pr. dag* hos Sendgrid.

.. code-block:: shell
  :name: cron-job-script

  #!/bin/bash
  SENDGRIDURL="https://api.sendgrid.com/v3/mail/send"

  statusCode=$(curl -s -o /dev/null -w "%{http_code}" -X GET -H "Content-type: application/json" "https://gc2.myDomain.com/extensions/traccar_api/controller/traccar/myDatabase")
  if [ "$statusCode" != 200 ]; then
      curl --request POST --url $SENDGRIDURL  --header 'Authorization: Bearer ADD SENDGRID API KEY.' --header 'Content-Type: application/json' --data '{"personalizations":[{"to":[{"email":"log@myDomain.com","name":"GC2 Vidi server"}],"subject":"Error on Traccar"}],"content": [{"type": "text/plain", "value": "The call to the Traccar server for getting the car positions returned an error with status code: '"${statusCode}"'."}],"from":{"email":"log@myDomain.com","name":"GC2 Vidi server"},"reply_to":{"email":"log@myDomain.com","name":"GC2 Vidi server"}}'
  fi

Giv scriptet de rigtige rettigheder:

chmod +x getCarPositions.sh

Nedenstående cronjob sender et request i minuttet.

1. crontab -e
2. MAILTO=\"\"
3. \*/1 \* \* \* \* /home/myUser/scriptsCronJobs/getCarPositions.sh

Hvis du ikke vil sende en email ved fejl request, kan du sætte følgende cronjob op.

1. crontab -e
2. MAILTO=\"\"
3. \*/1 \* \* \* \* curl -X GET -H \"Content-type: application/json\" \"https://gc2.myDomain/extensions/traccar_api/controller/traccar/databaseName\"

Test Traccar Serveren
=================================================================

Hvis du vil teste Traccar serveren, skal du først have en session:

curl -k -i \"https://traccarServer.com/api/session?token=[token]\"

Den session du modtager pastes in istedet for [session].

curl -k --cookie \"JSESSIONID=[session]\" https://traccarServer.com/api/devices?id=3

Du kan evt. tilføje -v for at få response headeren. Den kan hjælpe dig, hvis du får fejl.
