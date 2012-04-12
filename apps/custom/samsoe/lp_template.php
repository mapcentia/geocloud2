<?php
include("html_header.php");
include("controller/table_c.php");
?>
<body>
<script src="http://ajax.googleapis.com/ajax/libs/dojo/1.6/dojo/dojo.xd.js" djConfig="parseOnLoad: true"></script>
<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/dojo/1.6/dijit/themes/claro/claro.css">
<!--
<script type="text/javascript" src="http://beta.mygeocloud.cowi.webhouse.dk/js/ext/adapter/ext/ext-base.js"></script>
<script type="text/javascript" src="http://beta.mygeocloud.cowi.webhouse.dk/js/ext/ext-all.js"></script>

<script type="text/javascript" src="beta.mygeocloud.cowi.webhouse.dk/js/jquery/1.6.4/jquery.min.js"></script>

<script type="text/javascript" src="http://beta.mygeocloud.cowi.webhouse.dk/js/bootstrap/js/bootstrap.min.js"></script>
<link rel="stylesheet" type="text/css" href="http://beta.mygeocloud.cowi.webhouse.dk/js/bootstrap/css/bootstrap.min.css">
-->
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
<style>
* {  /* Global */ 
	font-family:  Verdana, Helvetica, Tahoma, sans-serif;
}
</style>
<table border="0" cellspacing="0" width="100%"><tr>
<td width="100%" valign="top">

<p alt="Åbn kort i nyt vindue"  title="Åbn kort i nyt vindue" href="javascript:void(0)" onclick="javascript:openMapWin('http://mygeocloud.cowi.webhouse.dk/apps/viewer/openlayers/<?php echo $postgisdb;?>/?lan=da&usepopup=false&popup=true&layers=lokalplaner.lpplandk2_view;lokalplaner.lokalplan_vedtaget&sld=<?php echo urlencode("<StyledLayerDescriptor version='1.1.0'><NamedLayer><Name>lokalplaner.lpplandk2_view</Name><UserStyle><Title>xxx</Title><FeatureTypeStyle><Rule><Filter><PropertyIsEqualTo><PropertyName>planid</PropertyName><Literal>{$row["planid"]}</Literal></PropertyIsEqualTo></Filter><LineSymbolizer><Stroke><CssParameter name='stroke'>#000000</CssParameter><CssParameter name='stroke-width'>3</CssParameter></Stroke></LineSymbolizer></Rule></FeatureTypeStyle></UserStyle></NamedLayer></StyledLayerDescriptor>");?>',600,500)">Se interaktivt kort over lokalplanomr&aring;det</p>
<h1 class="h1"><?php echo "{$row['plannr']} {$row['plannavn']}";?></h1>
<table><tr><td valign="top">
<?php
$open = "true";
foreach($lp_fields as $key=>$value){
	foreach ($rowsLpDel as $lpDels) {
			if ($lpDels[$key]) {
			$delExist = true;
		}
	}
	if (($row[$key] && substr($key,0,5)!="bilag" && $key!="pdf1") or $delExist){
		$delExist = false;
	?>
		<div dojoType="dijit.TitlePane" open="<?php echo $open;?>" title="<?php echo "{$i} {$lp_fields[$key]}";?>">
		<?php
		if ($open=="true") $open = "false";
		if ($lp_ref[$key]){
			$query="SELECT distinct(textvalue) as textvalue FROM {$lp_ref[$key]} where fieldkey='{$row[$key]}'";
			$ref = $postgisObject->fetchRow($postgisObject-> execQuery($query));
			$row[$key] = $ref["textvalue"];
		}
		$row[$key] = str_replace("#.#","#",$row[$key]);
		$split = explode("#",$row[$key]);?>
		<table border='0' cellspacing='10'>
		<?php
		foreach($split as $num=>$text){
			$text = html_entity_decode($text);
			$text = str_replace("<p>","",$text);
			$text = str_replace('<p align="left">',"",$text);
			$text = str_replace("</p>","<br/><br/>",$text);
			$text = str_replace("<strong>","<b>",$text);
			$text = str_replace("</strong>","</b>",$text);
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
				$lpDels[$key] = str_replace("#.#","#",$lpDels[$key]);
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
		}?>
		</table>
		<?php
		//echo "----------<br/>";
		//echo nl2br($row[$key]);
		echo "</div>\n";
		$i++;
	}
}	
?>

</td><td valign="top">
<?php foreach($lp_fields as $key=>$value){
	if (substr($key,0,5)=="bilag" && $row[$key]){?>
	<a target="_blank" title="Klik for stor udgave" class="thickbox" href="http://samsoe-lp.odeum.com/<?php echo str_replace("/thumbs2","",$row[$key]);?>"><img width="210" height="310" alt="Klik for stor udgave" border="0" src="http://samsoe-lp.odeum.com/<?php echo $row[$key];?>"></a><br/><br/>
	<?php }
	if ($row[$key] && $key=="pdf1") {?>
	<a target="_blank" alt="&Aring;bn i nyt vindue" title="&Aring;bn i nyt vindue" href="<?php echo $row['pdf1'];?>">Matrikelkort over lokalplanomr&aring;det</a>
	<?php }
	}
	?>
	</td></tr></table>
</td></tr>
</table>
<?php include("html_footer.php");?>
