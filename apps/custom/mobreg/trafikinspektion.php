<?php
include("html_header.php");
include("controller/table_c.php");
if (!$arr['vcUserName']) {
	echo "Kvalitetssikret <input type='checkbox' name='ok".$_REQUEST['id']."'";

	if ($checkRow['ok']==1) echo " checked";
	echo " onClick='okChange(".$_REQUEST['id'].");'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	//echo $query;
	//echo "<a target='blank' href='/dk/kulturmiljoe/rudersdal_indtastning.htm?iFormID=	115770004863329&rowid={$row['irowid']}'>Ret i posten</a>";
}
echo "<a href='http://mobreg.cowi.webhouse.dk/admin/formsadmin.php?Mode=print&iFormID={$_REQUEST['formid']}&rowid={$_REQUEST['id']}'>Ret i data</a>";
echo "<table><tr>";
echo "<td valign='top'><div style='border:0px solid silver;'><table border='1' cellpadding='3' cellspacing='0' width='500'>";

foreach($fields as $key=>$value) {
	//if (!$row[$key]) $row[$key] = "-";
	if ($key=="registreringsdato") $row[$key] = date("d.m.Y",$row[$key]);
	if (!$arr['vcUserName']) {
		if ($key=="bevaring") {
				if ($row[$key]==-1) $bevarText = "Ikke vurderet";
				if ($row[$key]>0 AND $row[$key]<=3) $bevarText = "Høj værdi";
				if ($row[$key]>3 AND $row[$key]<=6) $bevarText = "Middel værdi";
				if ($row[$key]>6 AND $row[$key]<=9) $bevarText = "Lav værdi";
				if ($row[$key]>0) {
					$row[$key] = "{$row[$key]}&nbsp;&nbsp;&nbsp;{$bevarText}";
				}
				else {
					$row[$key] = "{$bevarText}";
				}
		}
	}
	if (!preg_match ("/_kommentar/" , $key) && $row[$key]) {
		if ($key=="man_billeder") {
			echo "<tr><td class='content-cell' style='text-align:left;width:180px'>{$value}</td><td class='content-cell'><div style='width:280px;height:31px;overflow:auto'>{$row[$key]}";
		}
		elseif ($key=="adresse") {
			echo "<tr><td class='content-cell' style='text-align:left;width:180px'><b style='font-size:1.4em'>{$value}</b></td><td class='content-cell'><b style='font-size:1.4em'>{$row[$key]}</b>";
		}
		elseif ($key=="bevaring") {
			echo "<tr><td class='content-cell' style='text-align:left;width:180px'><b>{$value}</b></td><td class='content-cell'><b>{$row[$key]}</b>";
		}
		else {
			echo "<tr><td class='content-cell' style='text-align:left;width:180px'>{$value}</td><td class='content-cell'>{$row[$key]}";
		}
	}
	if (preg_match("/_kommentar/" , $key)) {
		echo "<div class='content-cell' style='color:#848484;margin-top:5px'>{$row[$key]}</div></td>";
	}

	if (!preg_match ("/Kommentar/" , next($fields))) {

		if (!preg_match("/_kommentar/" , $key)) {
			echo "</td>";
		}
		echo "</tr>\n";
	}
	else {
		//echo "<td>as</td></tr>\n";
	}
}
echo "</table></div></td>";
echo "<td>";
if ($row['assets3']) {
$assets3Arr = explode(",",$row['assets3']);
foreach($assets3Arr as $value) {
	$arr = explode("-",$value);
	$str = "http://mobreg.cowi.webhouse.dk/mobiledata/images/{$arr[1]}/{$value}";
	echo "<div><a target='_blank' href='{$str}'><img style='width:300px;height:225px' src='{$str}' /></a></div>";
	//echo $str."<br/>";
}
}

if ($row['assets4']) {
$assets4Arr = explode(",",$row['assets4']);
foreach($assets4Arr as $value) {
	$arr = explode("-",$value);
	$str = "http://mobreg.cowi.webhouse.dk/mobiledata/images/{$arr[1]}/{$value}";
	echo "<div><a target='_blank' href='{$str}'><img style='width:300px;height:225px' src='{$str}' /></a></div>";
	//echo $str."<br/>";
}
}

if ($$row['assets5']) {
$assets5Arr = explode(",",$row['assets5']);
foreach($assets5Arr as $value) {
	$arr = explode("-",$value);
	$str = "http://mobreg.cowi.webhouse.dk/mobiledata/images/{$arr[1]}/{$value}";
	echo "<div><a target='_blank' href='{$str}'><img style='width:300px;height:225px' src='{$str}' /></a></div>";
	//echo $str."<br/>";
}
}

echo "</td>";
echo "</tr></table>";
?>
<script>
function okChange(id)
{
	sndReq("&id="+id+"&type=json","/apps/custom/save/controller/check_c.php",handler_check);
}
handler_check = function(){}
function createRequestObject() {
	var ro;
	// Mozilla, Safari,...
	if (window.XMLHttpRequest) {
		ro = new XMLHttpRequest();
		if (ro.overrideMimeType) {
			ro.overrideMimeType('text/xml');
			// See note below about this line
		}
		// IE
	} else if (window.ActiveXObject) {
		try {
			ro = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try {
				ro = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e) {}
		}
	}
	if (!ro) {
		alert('Giving up :( Cannot create an XMLHTTP instance');
		return false;
	}
	return ro;
}
function sndReq(param,server,handler) {
	//location.href = server+"?"+action; //uncomment if you need for debugging
	http = createRequestObject();
	http.open('POST', server, true);
	http.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
	http.onreadystatechange = handler;
	http.send(param);
}
</script>