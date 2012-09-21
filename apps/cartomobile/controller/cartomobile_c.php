<?php
include '../../conf/main.php';
include '../../libs/functions.php';
include '../../model/cartomobile.php';
include '../../model/tables.php';
include '../../model/geometry_columns.php';
include '../../inc/user_name_from_uri.php';
$postgisdb = $parts[3];
$postgisschema = $parts[4];
$cartomobile = new cartomobile($postgisschema);
echo $cartomobile->getXml($postgisschema);