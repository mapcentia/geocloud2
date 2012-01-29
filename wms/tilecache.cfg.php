<?php
function makeTileCacheFile($user,$extentLayer=NULL) {
	//return;
	global $basePath;
	global $postgisdb;
	global $postgishost;
	global $postgispw;
	global $hostName;
	global $postgisschema;
	$postgisdb = $user;


	$table = $extentLayer;
	$postgisObject = new postgis();
	$srs = "900913";
	$geomField = $postgisObject -> getGeometryColumns($table, "f_geometry_column");

	if ($extentLayer) {
		$sql = "SELECT xmin(EXTENT(transform(".$geomField.",$srs))) AS TXMin,xmax(EXTENT(transform(".$geomField.",$srs))) AS TXMax, ymin(EXTENT(transform(".$geomField.",$srs))) AS TYMin,ymax(EXTENT(transform(".$geomField.",$srs))) AS TYMax  FROM ".$table;
		$result = $postgisObject->execQuery($sql);
		//print_r($postgisObject->PDOerror);
		$row = $postgisObject->fetchRow($result);
	}

	ob_start();


	echo "[cache]\n";
	echo "type=Disk\n";
	echo "base={$basePath}/tmp/{$user}\n\n";

	$sql="SELECT * FROM settings.geometry_columns_view WHERE f_table_schema='{$postgisschema}' OR f_table_schema='public'";
	//echo $sql;
	$result = $postgisObject->execQuery($sql);
	if($postgisObject->PDOerror){
		makeExceptionReport($postgisObject->PDOerror);
	}
	while ($row = $postgisObject->fetchRow($result)) {

		echo "[{$row['f_table_schema']}.{$row['f_table_name']}]\n";
		echo "type=WMS\n";
		echo "url=http://127.0.0.1/cgi-bin/mapserv?map={$basePath}/wms/mapfiles/{$user}_{$row['f_table_schema']}.map\n";
		echo "extension=png\n";
		echo "bbox=-20037508.3427892,-20037508.3427892,20037508.3427892,20037508.3427892\n";
		echo "maxResolution=156543.0339\n";
		echo "srs=EPSG:900913\n\n";

	}
	$data = ob_get_clean();
	@unlink("{$basePath}wms/tilecache/cfgfiles/{$user}.tilecache.cfg");
	$newFile = "{$basePath}wms/tilecache/cfgfiles/{$user}.tilecache.cfg";
	$fh = fopen($newFile, 'w');
	fwrite($fh,$data);
	fclose($fh);
}