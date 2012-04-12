<?php
include("html_header.php");
?>
</head>
<body >
<script type="text/javascript">
    dojo.require("dijit.TitlePane");
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
<?php
include ("inc/lp_fields.php");
include ("inc/lp_ref.php");
$table = new table("lokalplaner.lpplandk2_join");
$table->execQuery("set client_encoding='latin1'","PDO");
$response = $table->getRecords("planid='{$_REQUEST['planid']}'");
//print_r($response['data'][0]);
$row = $response['data'][0];

/*
$table = new table("public.lpdelplandk2_join");
$response = $table->getRecords("lokplan_id='{$_REQUEST['planid']}'");
print_r($response['data']);
$rowsLpDel = $response['data'];
*/
$postgisObject = new postgis();
//$postgisObject->execQuery("set client_encoding='latin1'","PG");
$query="SELECT * FROM lokalplaner.lpdelplandk2_join where lokplan_id='".$_REQUEST["planid"]."' order by delnr";
//echo $query;
$result=$postgisObject-> execQuery($query,"PG");
$rowsLpDel = pg_fetch_all($result);
if (!is_array($rowsLpDel)) $rowsLpDel = array();

$i = 1;
?>
<table border="0" cellspacing="0" width="100%"><tr>
<td width="100%" valign="top">
<table border="0" cellspacing="10" width="100%"><tr>
<td width="0" valign="top">
<p alt="Åbn kort i nyt vindue"  title="Åbn kort i nyt vindue" href="javascript:void(0)" onclick="javascript:openMapWin('http://mygeocloud.cowi.webhouse.dk/apps/viewer/openlayers/<?php echo $postgisdb;?>/?lan=da&usepopup=false&popup=true&filter=&layers=lokalplaner.lpplandk2_view;lokalplaner.lpdelplandk2_view&sld=<?php echo urlencode("<StyledLayerDescriptor version='1.1.0'><NamedLayer><Name>lokalplaner.lpplandk2_view</Name><UserStyle><Title>xxx</Title><FeatureTypeStyle><Rule><Filter><PropertyIsEqualTo><PropertyName>planid</PropertyName><Literal>{$_REQUEST["planid"]}</Literal></PropertyIsEqualTo></Filter><LineSymbolizer><Stroke><CssParameter name='stroke'>#000000</CssParameter><CssParameter name='stroke-width'>3</CssParameter></Stroke></LineSymbolizer></Rule></FeatureTypeStyle></UserStyle></NamedLayer></StyledLayerDescriptor>");?>',600,500)">Se interaktivt kort over lokalplanomr&aring;det</p>
<h1 class="h1"><?php echo "{$row['plannr']} {$row['plannavn']}";?></h1>
<?php
$open = "true";
foreach($lp_fields as $key=>$value){
	if ($row[$key] && substr($key,0,5)!="bilag"){?>
		<div dojoType="dijit.TitlePane" open="<?php echo $open;?>" title="<?php echo "{$i} {$lp_fields[$key]}";?>">
		<?php
		if ($open=="true") $open = "false";
		if ($lp_ref[$key]){
			$query="SELECT distinct(textvalue) as textvalue FROM {$lp_ref[$key]} where fieldkey='{$row[$key]}'";
			$ref = $postgisObject->fetchRow($postgisObject-> execQuery($query));
			$row[$key] = $ref["textvalue"];
		}
		$split = explode("#",$row[$key]);
		echo "<table border='0' cellspacing='10'>";
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
		if (is_array($rowsLpDel)) {
		foreach ($rowsLpDel as $lpDels) {
			if ($lpDels[$key]) {
				$num++;
				$split = explode("#",$lpDels[$key]);
				echo "<tr valign='top'><td></td><td><b>For {$lpDels['delnr']} g&aelig;lder</b></td></tr>";
				foreach($split as $num2=>$text){
					$text = html_entity_decode($text);
					$text = str_replace("<p>","",$text);
					$text = str_replace("</p>","<br/>",$text);
					if (substr($text,0,6)=="&nbsp;") {
						$text = substr_replace($text,"",0,6);
					}
					if (sizeof($split)>1) {
						if ($num2) echo "<tr valign='top'><td>{$i}.{$num}.{$num2}</td><td>".trim($text)."</td></tr>";

					}
					elseif (sizeof($split)==1) {
						echo "<tr><td></td><td>{$text}</td></tr>";
					}
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
</td><td valign="top">
<?php
foreach($lp_fields as $key=>$value){
if (substr($key,0,5)=="bilag" && $row[$key]){?>
<a title="Klik for stor udgave" class="thickbox" href="<?php echo str_replace("/thumbs1","",$row[$key]);?>"><img alt="Klik for stor udgave" border="0" src="<?php echo $row[$key];?>"></a><br/>
<?php }};?>
</td></tr></table>
<?php include("html_footer.php");?>
