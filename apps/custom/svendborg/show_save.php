<?php
include("html_header.php");
?>
<table border="0" class="pagewrapper" cellpadding="0" cellspacing="0" align="center">
  <tbody><tr>
    <td class="headerwrapper">
    	<div class="logo">
    		<a href="/dk/forside.htm" title="Til Kulturarv Svendborgs forside">
    		<img src="/gifs/apps/kulturarvsvendborg/kslogo.gif" border="0"></a>
    	</div>
    	<div class="servicewrapper">
    		<a href="/dk/soeg.htm" title="Søg">Søg</a>
    		&nbsp;|&nbsp;
    		<a href="/dk/kontakt.htm" title="Kontakt">Kontakt</a>
    		&nbsp;|&nbsp;
    		<a href="/dk/sitemap.htm" title="Sitemap">Sitemap</a>
    		&nbsp;|&nbsp;
    		<a href="/dk/sideindex.htm" title="A-Å Indeks">A-Å Indeks</a>
    	</div>
    </td>
  </tr>
  <tr>
    <td>
    </td>
  </tr>
  <tr>
    <td class="contentwrapper">
<?php
include("controller/table_c.php");

$imUrl = "/rudersdalbilleder/{$row['registrator']}/".date("dmy",$row['registreringsdato'])."/";
$imPath = "/srv/www/sites/mobreg/htdocs/images/svendborg/".date("dmy",$row['registreringsdato'])."/";
//echo $imPath."<br/>";
$imNames = explode(",",$row['man_billeder']);
/*if (!$arr['vcUserName']) {
	$tmp = $imNames[0];
	unset($imNames);
	$imNames[0] = $tmp;
	$tmp = NULL;
}*/
if (!$arr['vcUserName']) {
	//echo "Kvalitetssikret <input type='checkbox' name='ok".$_REQUEST['id']."'";

	if ($checkRow['ok']==1) echo " checked";
	//echo " onClick='okChange(".$_REQUEST['id'].");'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
}
//echo "<a href='http://mobreg.cowi.webhouse.dk/admin/formsadmin.php?Mode=print&iFormID={$_REQUEST['formid']}&rowid={$_REQUEST['id']}'>Ret i data</a>";
echo "<table><tr>";
echo "<td valign='top'><div style='border:0px solid silver;'><table cellpadding='3' cellspacing='0' width='500'>";

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

echo "<table>";
foreach($imNames as $name) {
	
	if($name) {
		
		$name = trim($name);
		if ($name=="aaa") {
			echo "<tr><td valign='top'><img style='border:1px solid silver;' src='/kortbrowser/rudersdalbilleder/0_intet_foto_aaa.JPG'/></td></tr>";
		}
		else {
			$name = $name."\.";
			find_files($imPath, '/'.$name.'/', 'my_handler');
		}
	}
}
echo "</table>";

echo "</td>";
echo "</tr></table>";

// FUNCTIONS
function find_files($path, $pattern, $callback) {
  $path = rtrim(str_replace("\\", "/", $path), '/') . '/';
  $matches = Array();
  $entries = Array();
  @$dir = dir($path);
  if ($dir){
  while (false !== ($entry = $dir->read())) {
    $entries[] = $entry;
  }
  $dir->close();
  foreach ($entries as $entry) {
	//echo $entry."<br>";
    $fullname = $path.$entry;
    if ($entry != '.' && $entry != '..' && is_dir($fullname)) {
      find_files($fullname, $pattern, $callback);
    } else if (is_file($fullname) && preg_match($pattern, $entry)) {
	   // echo  $pattern."<br>";
      call_user_func($callback, $fullname);
    }
  }
  }
}
function my_handler($filename) {
	$exifArr = exif_read_data($filename);
	echo "<!--";
	//print_r($exifArr);
	echo "-->";
	$prop= $exifArr['COMPUTED']['Width']/$exifArr['COMPUTED']['Height'];
  $filename = str_replace("/srv/www/sites/mobreg/htdocs/images/svendborg/","http://mobreg.cowi.webhouse.dk/images/svendborg/",$filename);
  echo "<tr><td valign='top'>
  <a target='_blank' title='Klik for stort billede' alt='Klik for stort billede' href='{$filename}'><img style='border:1px solid silver;'";
	if ($prop>1) {
		echo "width='400' height='300'";
	}
	else {
		echo "width='300' height='400'";
	}

	echo " src='{$filename}'/></a></td></tr>";
}
?>
<script>
function okChange(id)
{
	sndReq("&id="+id+"&type=json","/apps/custom/svendborg/controller/save_check_c.php",handler_check);
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
 </td>
  </tr>
  <tr>
    <td class="addresswrapper">
    	<b>Svendborg Kommune</b> | Ramsherred 5, 5700 Svendborg Telefon 62 23 30 00, <a href="mailto:svendborg@svendborg.dk">svendborg@svendborg.dk</a>    </td>
  </tr>
</tbody></table>
</body>
</html>