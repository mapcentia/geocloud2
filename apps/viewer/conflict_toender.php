<?php include_once("../../conf/main.php");?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
	"http://www.w3.org/TR/html4/loose.dtd">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<script src="http://ajax.googleapis.com/ajax/libs/dojo/1.6/dojo/dojo.xd.js" djConfig="parseOnLoad: true"></script>
<script type="text/javascript">
	dojo.require("dijit.form.Button");
</script>
<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;sensor=false&amp;key=<?php echo $gMapsApiKey;?>"
		type="text/javascript"></script>
<script type="text/javascript" src="http://beta.mygeocloud.cowi.webhouse.dk/api/v1/js/api.js"></script>

<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/dojo/1.6/dijit/themes/claro/claro.css"/>
<link rel="stylesheet" type="text/css" href="/styles/styles.css"/>
<script type="text/javascript" charset="utf-8">
	var MapappWin = null;
	function openMapWin(page,width,height)
		{
			var iLangID = iLangID;
			var iWidth 					= width;
			var iHeight 				= height;
			var strWinName = "MapServer";
			var strURL = page;
			var popleft = (screen.width - iWidth) / 2;
			var poptop = (screen.height - iHeight) / 2;

			var strParameters 	= "width=" + iWidth + ",height=" + iHeight +
								",resizable=1,scrollbars=0,status=1,left="+
								popleft+",top="+poptop+",screenX="+popleft+
								",screenY="+poptop+",toolbar=0";

			if (MapappWin == null) openWin = true;
			else if (MapappWin.closed) openWin = true;
			else openWin = false;

			if (openWin) {
				MapappWin 	= window.open(strURL, strWinName, strParameters);
				MapappWin.focus();
			} else {
				if (!MapappWin.closed) {
					MapappWin.focus();
				}
			}
	}
</script>
<link rel="stylesheet" type="text/css" href="styles/styles_bleed.css">
<style>
	body {
		font-family: arial, helvetica, sans-serif;
		font-size: 90%;
		text-align: left;
		font-weight: normal;
	}
</style>


</head>
<body class=" claro ">
<form id="theForm" name="theForm">
<?php
$defaultSearchLayer = "matrikel";

$matrikelTabel='matrikel';
$ejerlavbetegn = 'elavsnavn';
$matrikelbetegn = 'matrnr';
$ejendomsnr = 'esr_ejdnr';
$ejerlavkode="elavskode";
$komnrMatrikel="komkode";
$matrikelOrder=" substring($matrikelbetegn from '[0-9]*')::integer,substring($matrikelbetegn from '[a-z]*$')::text";
$rammebetegn="kpplandk2";

$adresseTabel='adresser';
$vejkodebetegn='vejkode';
$vejnavnbetegn='vejnavn';
$husnummerbetegn='husnrbogst';
$komnrAdresse="kommnr";
$adresseOrder="substring($husnummerbetegn from '[0-9]*')::integer,substring($husnummerbetegn from '[a-z]*$|[A-Z]*$')::text";



//$kommuneNavn='nyborg';
//$kommuneKode=450;

$pg_query_function = "Intersects";



include_once("functions.php");
include_once("libs/phpgeometry_class.php");


ini_set("display_errors", "On");
ini_set("memory_limit","350M");
error_reporting(3);

$width = 400;
$height = 400;

//*************
$proj = "900913";
$units = "xy";
$postgisdb="toender";
//*************



$postgisObject = new postgis();
$controlObject = new control();
$postgisObject -> setControlObject($controlObject);
$postgisObject -> pg_query_function = "intersects";

$sql="SELECT * FROM settings.geometry_columns_view WHERE f_table_schema='{$_REQUEST['schema']}'";
$result = $postgisObject->execQuery($sql);
$slayer=pg_fetch_all($result);
//print_r($slayer);

?>
<script>
	function update(){
		clearForm();
		document.theForm.submit();
	}
	function spatialsearch(){
		document.theForm.submit();
	}
	function clearForm()
	{
		try {
			document.theForm.matrnr.disabled=true;

		} catch (e) {
			//alert(e.message);
		}
		try {
			document.theForm.husnummer.disabled=true;
		} catch (e) {
			//alert(e.message);
		}

	}

</script>
<div style="display:none">
<select class='selectbox' name="query_mode" onChange="update();">
<option>S&oslash;g p&aring;:</option>
<option>- - - - - - - - - - - - -</option>
<option value="adresse" <?php if ($query_mode=="adresse") echo "SELECTED";?>>Adresse</option>
<option value="matrikel" <?php if ($query_mode=="matrikel") echo "SELECTED";?>>Matrikelnr</option>
</select>
</div>
<!-- Select form for matrikel query -->
<?
$postgisObject->open();
if ($query_mode == "matrikel")
{
	echo "<input type='hidden' name='db_field' value='LOD_ID'>";
	echo "<SELECT class='selectbox' name='elavnavn' onChange='update();'>";
	$query = ("select $ejerlavbetegn,$ejerlavkode from $matrikelTabel group by $ejerlavkode,$ejerlavbetegn order by $ejerlavbetegn");
	$result = pg_exec($query);
	$num_results = pg_numrows($result);
	echo "TEST ".$elavnavn;
	if (!$elavnavn)
	{
		echo "<option SELECTED;>Ejerlav</option>";
		echo "<option>- - - - - - - - - - - - - - - - -</option>";
	}
	for ($i = 0; $i < $num_results; $i ++)
	{
		$row = pg_fetch_array($result);
		echo "<option value='".$row[$ejerlavkode]."'";
		if ($row[$ejerlavkode]==$elavnavn) echo " name='select1' id='select1' SELECTED";
		echo ">".$row[$ejerlavbetegn];
		echo "</option>";
	}
	if ($elavnavn)
	{
		$query = ("select $matrikelbetegn from $matrikelTabel where $ejerlavkode='$elavnavn' order by $matrikelOrder");
		echo $query;
		$result = pg_exec($query);
		$num_results = pg_numrows($result);
		echo "</select><SELECT class='selectbox' name='matrnr' onchange='spatialsearch();'>";
		if (!$matrnr)
		{
			echo "<option SELECTED;>Matrikelnr</option>";
			echo "<option>- - - - - - - - - - - -</option>";
		}
  		for ($i = 0; $i < $num_results; $i ++)
		{
			$row = pg_fetch_array($result);
			echo "<option value='".base64_encode($row[$matrikelbetegn])."'";
			if (base64_encode($row[$matrikelbetegn])==$matrnr) echo " name='select2' id='select2' SELECTED";
			echo ">".$row[$matrikelbetegn];
			echo "</option>";
		}
		echo "</SELECT>";
	}
	$query = ("select * from $matrikelTabel where $matrikelbetegn='".base64_decode($matrnr)."' and $ejerlavkode='$elavnavn'");
	$result = pg_exec($query);
	$gid  = pg_fetch_array($result);
}
?>
<!-- Select form for adresse query -->
<?
 if ($query_mode == "adresse")
{
?>
	<SELECT class='selectbox' name="vejkode" onChange='update();'>
<?
$query = ("select $vejkodebetegn,$vejnavnbetegn from $adresseTabel group by $vejkodebetegn,$vejnavnbetegn order by $vejnavnbetegn");
echo $query;
$result = pg_exec($query);
$num_results = pg_numrows($result);
if (!$vejkode)
{
	echo "<option SELECTED;>Vejnavn</option>";
	echo "<option>- - - - - - - - - - - - - -</option>";
}
for ($i = 0; $i < $num_results; $i ++)
{
	$row = pg_fetch_array($result);
	echo "<option value='".base64_encode($row[$vejkodebetegn])."'";
	if ($row[$vejkodebetegn]==base64_decode($vejkode)) echo " name='select1' id='select1' SELECTED";
	echo ">".$row[$vejnavnbetegn];
	echo "</option>";
}
echo "</select>";
if ($vejkode)
{
	$query = ("select $husnummerbetegn from $adresseTabel where $vejkodebetegn='".base64_decode($vejkode)."' order by $adresseOrder");
	$result = pg_exec($query);
	$num_results = pg_numrows($result);
	echo "<SELECT class='selectbox' name='husnummer' onchange='spatialsearch();'>";
	if (!$husnummer)
	{
		echo "<option SELECTED;>husnummer</option>";
		echo "<option>- - - -</option>";
	}
	for ($i = 0; $i < $num_results; $i ++)
	{
		$row = pg_fetch_array($result);
		echo "<option value='".$row[$husnummerbetegn]."'";
		if ($row[$husnummerbetegn]==$husnummer) echo " name='select2' id='select2' SELECTED";
		echo ">".$row[$husnummerbetegn];
		echo "</option>";
	}
	echo "</SELECT>";
	$query = ("select * from $adresseTabel where $vejkodebetegn='".base64_decode($vejkode)."' and $husnummerbetegn='$husnummer'");
	//echo $query;
	$result = pg_exec($query);
	$gid = pg_fetch_array($result);
}
}

if (($matrnr && $query_mode=="matrikel") || ($husnummer && $query_mode=="adresse")) {
	if ($query_mode=="matrikel") {
		$table = $matrikelTabel;
	}
	if ($query_mode=="adresse") {
		$table = $adresseTabel;
	}	
	$postgisObject->execQuery("set client_encoding='UTF8'");
	$result=$postgisObject->featureQuery($table, $gid['gid'], $matrikelTabel, "*",0);
	$row = pg_fetch_array($result);
	//echo "TEST";
	//print_r($row);
	//echo "TEST".$gid['gid'];
	$postgisObject -> search($matrikelTabel, "GID", $gid['gid'], 0.5);
		for ($u = 0; $u < sizeof($slayer); $u ++)
		{
			// We set the query vars
			$bit = $slayer[$u]['f_table_schema'].".".$slayer[$u]['f_table_name'];
			$fieldconfArr = (array)json_decode($slayer[$u]['fieldconf']);
			//print_r($fieldconfArr);
			

			$postGisQueryName[strtoupper($bit)] = $slayer[$u]['f_table_title'];
			$queryable=true;
			if ($slayer[$u]['not_querable']=="t") {
				//echo $postgisObject->getGeometryColumns($bits[0],"not_querable");
				$queryable=false;
			}
			foreach($fieldconfArr as $key=>$value){
				if ($value->querable) {
					if ($value->sort_id) {
						$strs[$value->sort_id]= strtoupper($value->column);
					}
					else {
						$strs[]= strtoupper($value->column);
					}
				}
				$postGisQueryFieldName[strtoupper($bit)][strtoupper($value->column)]=$value->alias;
				if ($value->link) {
					$postGisQueryContentLink[strtoupper($bit)]=strtoupper($value->column);
					$postGisQueryLinkTarget[strtoupper($bit)]="target='_parent'";
				}
				if ($value->linkprefix) {
					$postGisQueryDataPrefix[strtoupper($bit)]=$value->linkprefix;
				}
			}
			if (is_array($strs)) {
				ksort($strs);
				$str = implode(",",$strs);
				$postGisQueryFieldRow[strtoupper($bit)]= $str;
			}
			
			if ($postGisQueryBuffer[$slayer[$u]['f_table_name']]) $conflict_buffer=$postGisQueryBuffer[$slayer[$u]['f_table_name']];
			
			if ($queryable == true) {
				$output = $postgisObject -> queryDump($matrikelTabel,NULL,$slayer[$u]['f_table_schema'].".".$slayer[$u]['f_table_name'], "*", "SPATIELANALYSIS", $row['fid'],0,NULL,NULL);
			}
			else {
				if ($output=="") $output = false;
			}

			if ($output!="") {
				$layersForAppForMap[] = strtolower($slayer[$u]['f_table_schema'].".".$slayer[$u]['f_table_name']);
				$titlesForAppForMap[] = urlencode($slayer[$u]['f_table_title']);
			}
			$dump.= $output;
			$output = "";
			$conflict_buffer=0;
			unset($strs);
		}

	//$layersForAppForMap[] = "public.matrikel";
	$str = implode("','",$layersForAppForMap);
	?>
		
	<script>
		$(window).load(function() {
			var cloud_example2 = new mygeocloud_ol.map("map_example2","<?php echo $postgisdb ?>");
			var store_example2 = new mygeocloud_ol.geoJsonStore("<?php echo $postgisdb ?>");
			cloud_example2.click.activate();
			//console.log(cloud_example2.map.addBaseLayer.baseGNORMAL);
			//cloud_example2.map.setBaseLayer(cloud_example2.map.baseGNORMAL);
			var b = cloud_example2.addTileLayers(["public.orto_2010"],{isBaseLayer:true,name:"Ortofoto 2010"});
			cloud_example2.map.setBaseLayer(b[0]);
			cloud_example2.addTileLayers(['<?php echo $str ?>'],{opacity : 0.5,visibility:true});
			cloud_example2.addGeoJsonStore(store_example2);
			store_example2.sql = "SELECT the_geom FROM public.matrikel where gid=<?php echo $row['fid'] ?>";
			store_example2.load();
			store_example2.onLoad = function(){
				cloud_example2.zoomToExtentOfgeoJsonStore(store_example2);
			};
			var param = 'layers=' + cloud_example2.getVisibleLayers() + '&amp;type=text&amp;lan=da';
			$.ajax({
			url: '/apps/viewer/servers/legend/toender?' + param,
			async: false,
			dataType: 'json',
			type: 'GET',
			success: function (data, textStatus, http) {
					if (http.readyState == 4) {
						if (http.status == 200) {
							var response = eval('(' + http.responseText + ')');
							// JSON
							//alert(response.html);
							$('#legendContent').empty();
							$('#legendContent').append(response.html);
						}
					}
				}
			});
		});
		</script>

	<?php
}
?>
<div style="margin-top:15px;width:;height:240px;overflow:auto;">
	<?php echo $dump; ?>
</div>

<input type='hidden' name='schema' value='<?php echo $_REQUEST['schema'];?>'>
</form>
<table border=0>
<tr>
<td width="500" height="340" valign="top"><div id="map_example2" style="width: 100%;height: 100%"></div></td>
<td width="200" height="340" valign="top"><div id="legendContent" style="height:340px;overflow:auto"></div></td>
</tr>
</table>
</body>
</html>
