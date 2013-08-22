<?php 
include_once("server_header.inc");
//$geometryColumns = new GeometryColumns();
$sql="SELECT * FROM settings.geometry_columns_view order by sort_id";
$result = $postgisObject->execQuery($sql);
$rows = pg_fetch_all($result);
//print_r($rows);
if ($_REQUEST['layer']) $_REQUEST['layers'] = $_REQUEST['layer'];
$layers = explode(";",$_REQUEST['layers']);
$response['html'].= "<table>";
for ($i = 0; $i < sizeof($layers); $i++) {
	if ($layers[$i]) {
		$st = postgis::explodeTableName($layers[$i]);
		$wmsUrl = getValueFromKey($layers[$i].".the_geom","wmssource");
		//echo "test".$wmsUrl;
		if(!$wmsUrl){
			$layerConnectionString = "http://{$_SERVER['SERVER_NAME']}/wms/{$postgisdb}/{$st["schema"]}";
			$response['html'].= "<tr><td><img src='{$layerConnectionString}/?LAYER={$layers[$i]}&SERVICE=WMS&VERSION=1.1.1&REQUEST=getlegendgraphic&FORMAT=image/png'/></td></tr>";
		}
		else {
			$layerConnectionString = $wmsUrl;
			$response['html'].= str_replace("layers","layer","<tr><td><img src='{$layerConnectionString}&REQUEST=getlegendgraphic&FORMAT=image/png'/></td></tr>");
		}
	}
}
$response['html'].= "</table>";
function getValueFromKey($_key_,$column) {
	global $rows;
		foreach ($rows as $row) {
			foreach ($row as $field => $value) {
				if ($field == "_key_" && $value==$_key_) {
					return ($row[$column]);
				}
			}
		}
	}
include_once("server_footer.inc");