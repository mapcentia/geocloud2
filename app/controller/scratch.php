<?php
include("../server_header.inc");

$table = new table(NULL);
$response = $table->create($_REQUEST['name'],$_REQUEST['type'],$_REQUEST['srid']);

$table = new table('settings.geometry_columns_join');
$obj = json_decode('{"data":{"f_table_name":"'.$response['tableName'].'","f_table_title":""}}');
$response2 = $table->updateRecord($obj->data,'f_table_name',"f_table_schema='{$postgisschema}'");

// If layer is new (inserted) then insert a new class for it
if ($response2['operation'] == "inserted") {
	$class = new _class($_REQUEST['name'],$postgisschema);
	$class->insert();
}
makeMapFile($_SESSION['screen_name']);
makeTileCacheFile($_SESSION['screen_name']);

include_once("../server_footer.inc");