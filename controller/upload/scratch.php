<?php
include("../server_header.inc");

$table = new table(NULL);
$response = $table->create($_REQUEST['name'],$_REQUEST['type'],4326);

$class = new _class();
$class->insert(postgis::toAscii($_REQUEST['name'],array(),"_"));
makeMapFile($_SESSION['screen_name']);

include_once("../server_footer.inc");