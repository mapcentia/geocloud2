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
	
	//echo "type=AWSS3\n";
	//echo "access_key=AKIAIZUYE3I462NPVANQ\n";
	//echo "secret_access_key=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\n";
	//echo "db={$user}\n";

	$sql="SELECT * FROM settings.geometry_columns_view";
	$result = $postgisObject->execQuery($sql);
	if($postgisObject->PDOerror){
		makeExceptionReport($postgisObject->PDOerror);
	}
	
	while ($row = $postgisObject->fetchRow($result)) {
		$def = json_decode($row['def']);
		$def->meta_tiles==true ? $meta_tiles="yes" : $meta_tiles="no";
		$def->ttl<30 ? $expire=30:$expire=$def->ttl;
		echo "[{$row['f_table_schema']}.{$row['f_table_name']}]\n";
		echo "type=WMS\n";
		echo "url={$hostName}/wms/{$user}/{$row['f_table_schema']}/?";
		echo "extension=png\n";
		echo "bbox=-20037508.3427892,-20037508.3427892,20037508.3427892,20037508.3427892\n";
		echo "maxResolution=156543.0339\n";
		echo "metaTile={$meta_tiles}\n";
		echo "metaSize=3,3\n";
		echo "srs=EPSG:900913\n";
		echo "expire={$expire}\n\n";
	}
	$data = ob_get_clean();
	@unlink("{$basePath}wms/cfgfiles/{$user}.tilecache.cfg");
	$newFile = "{$basePath}wms/cfgfiles/{$user}.tilecache.cfg";
	$fh = fopen($newFile, 'w');
	fwrite($fh,$data);
	fclose($fh);
}
