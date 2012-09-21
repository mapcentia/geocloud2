<?php
include("html_header.php");
include ("inc/lp_fields.php");
include ("inc/lp_ref.php");
$table = new table("lokalplaner.lpplandk2_join");
$table->execQuery("set client_encoding='latin1'","PDO");
$response = $table->getRecords(null,"*","plannr='{$_REQUEST['plannr']}'");
//print_r($response['data'][0]);
$row = $response['data'][0];
if (empty($row)) die();

/*
$table = new table("public.lpdelplandk2_join");
$response = $table->getRecords("lokplan_id='{$_REQUEST['planid']}'");
print_r($response['data']);
$rowsLpDel = $response['data'];
*/
$postgisObject = new postgis();
//$postgisObject->execQuery("set client_encoding='latin1'","PG");
$query="SELECT * FROM lokalplaner.lpdelplandk2_join where lokplan_id='".$row["planid"]."' order by delnr";
//echo $query;
$result=$postgisObject-> execQuery($query,"PG");
$rowsLpDel = pg_fetch_all($result);
if (!is_array($rowsLpDel)) $rowsLpDel = array();

$i = 1;
?><!DOCTYPE html>
<html >
  <head>
    <title>MyGeoCloud - Online GIS - Store geographical data and make online maps - WFS and WMS</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Store geographical data and make online maps" />
	<meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC" />
	<meta name="author" content="Martin Hoegh" />
</head>
<body >
<script type="text/javascript">

	 
	function showParagraph(id) {
		
		//document.getElementById(id).style.display="inline";
		$(".paragraph").css({'display':'none'})
		$(".niv1").css({'color':''});
		$("#"+id).css({'display':'inline'});
		$("#"+id+"_menu").css({'color':'red'});
	}


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
<div id="map-win"></div>


<table border="0" cellspacing="10" width="100%"><tr>
<td width="227" valign="top">
<!--<a target="_blank" alt="Åbn kort i nyt vindue"  title="Åbn kort i nyt vindue" href="/kortbrowser/appformap/openlayers.phtml?layers=lpplandk2;lpdelplandk2&planid=<?php echo $row['planid'];?>">Se interaktivt kort over lokalplanområdet</a>-->
<?php /*foreach($lp_felter as $key=>$value){
	if (substr($key,0,5)=="bilag" && $row[$key]){?>
	<a title="Klik for stor udgave" class="thickbox" href="<?php echo str_replace("jpg","png",str_replace("/thumbs3","",$row[$key]));?>"><img alt="Klik for stor udgave" border="0" src="<?php echo $row[$key];?>"></a>
	<?php }};*/?>

<table class="leftmenutank" border="0" cellpadding="0" cellspacing="0">
<?php
	foreach($lp_fields as $key=>$value){
	foreach ($rowsLpDel as $lpDels) {
			if ($lpDels[$key]) {
			$delExist = true;
		}
	}
	if (($row[$key] && substr($key,0,5)!="bilag") or $delExist){
		$delExist = false;?>
	<tr>
		<td class="leftmenucell">
			<a id ="<?php echo $key;?>_menu" class="niv1" onfocus="this.blur()" href="javascript: void(0)" onclick="showParagraph('<?php echo $key;?>')"><?php echo "{$i} {$lp_fields[$key]}";?></a>
		</td>
	</tr>
<?php
$i++;
}}?>

</table>
</td>
<td valign="top">
<h1 class="h1"><?php echo "{$row['plannr']} {$row['plannavn']}";?></h1>
<?php
$i = 1;
foreach($lp_fields as $key=>$value){
	foreach ($rowsLpDel as $lpDels) {
			if ($lpDels[$key]) {
			$delExist = true;
		}
	}
	if (($row[$key] && substr($key,0,5)!="bilag") or $delExist){
		$delExist = false;?>
		
		<?php
		if ($open=="true") $open = "false";
		if ($lp_ref[$key]){
			$query="SELECT distinct(textvalue) as textvalue FROM {$lp_ref[$key]} where fieldkey='{$row[$key]}'";
			$ref = $postgisObject->fetchRow($postgisObject-> execQuery($query));
			$row[$key] = $ref["textvalue"];
		}
		$split = explode("#",$row[$key]);
		echo "<div id='{$key}' class='paragraph' style='display:none'><table border='0' cellspacing='10'>";
		echo "<div><h3>{$i} {$lp_fields[$key]}</h3></div>";
		foreach($split as $num=>$text){
			$text = html_entity_decode($text);
			$text = str_replace("<p>","",$text);
			$text = str_replace("</p>","<br/>",$text);
			if (substr($text,0,6)=="&nbsp;") {
				$text = substr_replace($text,"",0,6);
			}
			if (sizeof($split)>1) {
				if ($num) {
					echo "<tr valign='top'><td>{$i}.{$num}</td><td>".trim($text)."</td></tr>";
				}
				else {
					echo "<tr valign='top'><td></td><td>".trim($text)."</td></tr>";
				}
			}
			elseif (sizeof($split)==1) {
				echo "<tr><td></td><td>{$text}</td></tr>";
			}
		}
		foreach ($rowsLpDel as $lpDels) {
			if ($lpDels[$key]) {
				$num++;
				$split = explode("#",$lpDels[$key]);
				echo "<tr valign='top'><td></td><td><b>For {$lpDels['delnr']} {$lpDels['delomraade_navn']} g&aelig;lder</b></td></tr>";
				foreach($split as $num2=>$text){
					$text = str_replace("<p>","",$text);
					$text = str_replace("</p>","<br/>",$text);
					if (substr($text,0,6)=="&nbsp;") {
						$text = substr_replace($text,"",0,6);
					}
					if (sizeof($split)>1) {
						if ($num2) {
							echo "<tr valign='top'><td>{$i}.{$num}.{$num2}</td><td>".trim($text)."</td></tr>";
						}
						else {
							echo "<tr valign='top'><td></td><td>".trim($text)."</td></tr>";
						}
					}
					elseif (sizeof($split)==1) {
						echo "<tr><td></td><td>{$text}</td></tr>";
					}
				}
				}
		}
		echo "</table>";
		//echo "----------<br/>";
		//echo nl2br($row[$key]);
		echo "</div>\n";
		$i++;
	}
}	
?>
</td>
</tr></table>
<?php include("html_footer.php");?>
