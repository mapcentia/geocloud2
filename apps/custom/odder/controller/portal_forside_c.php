<?php
$table = new table("lokalplaner.lpplandk2_join");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$response = $table->getRecords(NULL,"forsidebillede,plannr,plannavn,forsidetekst","forsidebillede<>'' order by planid");
$row = $response['data'];
