<?php
/* This notice must be untouched at all times.

AppForMap v. 0.6
The latest version is available at
http://sourceforge.net/projects/appformap/

Copyright (c) 2003-2006 Martin H�gh. All rights reserved.
Created 17. 6. 2003 by Martin H�gh <mh@svaj.dk>

Php class library. This file is the engine for the AppForMap client,
and is supposed to be included in a html/javascript GUI.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

See the GNU General Public License
at http://www.gnu.org/copyleft/gpl.html for more details.
*/
class maplink
{
	var $ele;
	function maplink()
	{

		global $controlObject;
		global $postgisObject;
		global $serviceObject;
		global $HTTP_FORM_VARS;

		if (sizeof($_POST) > 0)
		{
			$HTTP_FORM_VARS = $_POST;
		}
		else
		{
			if (sizeof($_GET) > 0)
			{
				$HTTP_FORM_VARS = $_GET;
			}
			else
			$HTTP_FORM_VARS = array("");
		}
		$this->ele.="<input type='hidden' name='RubberWidth' value='' />";
		$this->ele.="<input type='hidden' name='RubberHeight' value='' />";
		$this->ele.="<input type='hidden' name='RubberWidthCache' value='' />";
		$this->ele.="<input type='hidden' name='RubberHeightCache' value='' />";
		$this->ele.="<input type='hidden' name='tool' value='' />";
		$this->ele.="<input type='hidden' name='currState' value='' />";
		$this->ele.="<input type='hidden' name='centerX' value='' />";
		$this->ele.="<input type='hidden' name='centerY' value='' />";
		$this->ele.="<input type='hidden' name='search' value='' />";
		$this->ele.="<input type='hidden' name='featurequery' value='' />";
		$this->ele.="<input type='hidden' name='deleteobject' value='' />";
		$this->ele.="<input type='hidden' name='deleteobjectkey' value='' />";
		$this->ele.="<input type='hidden' name='shapeId' value='' />";
		$this->ele.="<input type='hidden' name='editKey' value='' />";
		$this->ele.="<input type='hidden' name='pointArray' value='' />";
		$this->ele.="<input type='hidden' name='pointArrayQuery' value='' />";
		$this->ele.="<input type='hidden' name='pointArrayEdit' value='' />";
		$this->ele.="<input type='hidden' name='pointArrayRegen' value='' />";
		$this->ele.="<input type='hidden' name='jsGeomType' value='' />";
		$this->ele.="<input type='hidden' name='scale' value='' />";
		$this->ele.="<input type='hidden' name='mapDragX' value='' />";
		$this->ele.="<input type='hidden' name='mapDragY' value='' />";
		$this->ele.="<input type='hidden' name='updateIsClicked' value='' />";
		$this->ele.="<input type='hidden' name='clickX' value='' />";
		$this->ele.="<input type='hidden' name='clickY' value='' />";
	}
}
class control
{
	var $width;
	var $height;
	var $minx;
	var $miny;
	var $maxx;
	var $maxy;
	var $dminx;
	var $dminy;
	var $dmaxx;
	var $dmaxy;
	var $pRubberMinx;
	var $pRubberMiny;
	var $pRubberMaxx;
	var $pRubberMaxy;
	var $queryServiceTitle;
	var $queryLayerTitle;
	var $queryLayerName;
	var $queryServiceNum;
	var $units;
	var $crsName;
	var $notInDb;
	var $tool;
	var $proportion;
	var $serverCount;
	var $wfsCount;
	var $proj;
	var $infoFormat;
	var $parser;
	var $d; //array with width & height in world units
	var $pointArrayRegen;
	var $dragX;
	var $dragY;
	var $controlId;// an unik id for the obeject. Is use in the global array render_im
	var $doNotUseBackGroundWMS; // a flag
	var $doNotUseWMS; // a flag
	var $mapscript;
	var $subpressBBoxForm;
	var $doNotRenderMap;
	var $renderGeometryArray;
	function control($subpressBBoxForm=FALSE,$setCrs=TRUE) //constructor
	{
		global $HTTP_FORM_VARS;
		global $proj;
		global $units;
		global $width;
		global $height;

		$this -> subpressBBoxForm=$subpressBBoxForm;
		$this -> minx = $HTTP_FORM_VARS["minx"];
		$this -> miny = $HTTP_FORM_VARS["miny"];
		$this -> maxx = $HTTP_FORM_VARS["maxx"];
		$this -> maxy = $HTTP_FORM_VARS["maxy"];
		$this -> dminx = $HTTP_FORM_VARS["dminx"];
		$this -> dminy = $HTTP_FORM_VARS["dminy"];
		$this -> dmaxx = $HTTP_FORM_VARS["dmaxx"];
		$this -> dmaxy = $HTTP_FORM_VARS["dmaxy"];
		$this -> tool = $HTTP_FORM_VARS["tool"];
		$this -> proj = trim($proj);
		$this -> units = $units;
		$this -> dragX = str_replace("px","",$HTTP_FORM_VARS["mapDragX"]);
		$this -> dragY = str_replace("px","",$HTTP_FORM_VARS["mapDragY"]);
		$this -> controlId = rand(0,9999).time();
		$this -> width = $width;
		$this -> height = $height;

		if ($setCrs) $this -> crs();

		$this -> setproportion();

		$this -> doNotRenderMap=false;
		// Convert pix coords form http var to geo and store them in a property.
		// This way it can be converted back to pix coords after navigating
		if ($HTTP_FORM_VARS["pointArrayRegen"]!="")
		{
			$this->pointArrayRegenGeo=$this->convertpointarray(urldecode($HTTP_FORM_VARS["pointArrayRegen"]));
		}
		if (($HTTP_FORM_VARS["map_x"] || $HTTP_FORM_VARS["mapDragX"])  && $this -> tool != 'info')
		{
			$this -> navigate();
		}
		$this -> gml();
		$this->calculateCenter();


	}
	function setMapscriptObject(& $mapscriptObject)
	{
		$this->mapscript = & $mapscriptObject;
	}
	function setMinX($i)
	{
		switch ($this -> units)
		{
			case 'xy' :
				$this -> minx = $i;
				break;
			case 'degrees' :
				$this -> dminx = $i;
				break;
		}
	}
	function setMinY($i)
	{
		switch ($this -> units)
		{
			case 'xy' :
				$this -> miny = $i;
				break;
			case 'degrees' :
				$this -> dminy = $i;
				break;
		}
	}
	function setMaxX($i)
	{
		switch ($this -> units)
		{
			case 'xy' :
				$this -> maxx = $i;
				break;
			case 'degrees' :
				$this -> dmaxx = $i;
				break;
		}
	}
	function setMaxY($i)
	{
		switch ($this -> units)
		{
			case 'xy' :
				$this -> maxy = $i;
				break;
			case 'degrees' :
				$this -> dmaxy = $i;
				break;
		}
	}
	function getMinX()
	{
		switch ($this -> units)
		{
			case 'xy' :
				return ($this -> minx);
				break;
			case 'degrees' :
				return ($this -> dminx);
				break;
		}
	}
	function getMinY()
	{
		switch ($this -> units)
		{
			case 'xy' :
				return ($this -> miny);
				break;
			case 'degrees' :
				return ($this -> dminy);
				break;
		}
	}
	function getMaxX()
	{
		switch ($this -> units)
		{
			case 'xy' :
				return ($this -> maxx);
				break;
			case 'degrees' :
				return ($this -> dmaxx);
				break;
		}
	}
	function getMaxY()
	{
		switch ($this -> units)
		{
			case 'xy' :
				return ($this -> maxy);
				break;
			case 'degrees' :
				return ($this -> dmaxy);
				break;
		}
	}
	function calculateCenter()
	{
		$this->center[0]=$this->getMinX()+($this->geowidth()/2);
		$this->center[1]=$this->getMinY()+($this->geoheight()/2);
	}
	function getCenterX()
	{
		return ($this->center[0]);
	}
	function getCenterY()
	{
		return ($this->center[1]);
	}
	function setCenter($x,$y,$border,$orient="l")
	{
		if ($orient=="l") {
			$l=10;
			$p=0;
		}
		elseif ($orient=="p") {
			$l=0;
			$p=10;
		}

		$this -> setMinX($x - $border+$p);
		$this -> setMinY($y - $border+$l);
		$this -> setMaxX($x + $border-$p);
		$this -> setMaxY($y + $border-$l);
		$this -> setproportion();
	}
	function set_bbox_form()
	{
		$bbox.="<input type='hidden' name='minx' value=".$this -> minx." />";
		$bbox.="<input type='hidden' name='miny' value=".$this -> miny." />";
		$bbox.="<input type='hidden' name='maxx' value=".$this -> maxx." />";
		$bbox.="<input type='hidden' name='maxy' value=".$this -> maxy." />";
		$bbox.="<input type='hidden' name='dminx' value=".$this -> dminx." />";
		$bbox.="<input type='hidden' name='dminy' value=".$this -> dminy." />";
		$bbox.="<input type='hidden' name='dmaxx' value=".$this -> dmaxx." />";
		$bbox.="<input type='hidden' name='dmaxy' value=".$this -> dmaxy." />";
		$bbox.="<input type='hidden' name='mapWidth' value=".$this -> width." />";
		$bbox.="<input type='hidden' name='mapHeight' value=".$this -> height." />";
		$bbox.="<input type='hidden' name='crsName' value='".$this -> crsName."' />";
		$bbox.="<input type='hidden' name='crsUnit' value='".$this -> units."' />";
		$bbox.="<!-- helloo".$this -> getMinX()." ".$this -> getMinY()." ".$this -> getMaxX()." ".$this -> getMaxY()." $-->";
		if (!$this -> subpressBBoxForm) echo $bbox;
	}
	function pixLength($orientation) // width or height
	{
		switch ($orientation)
		{
			case width :
				$pixlength = $this -> geowidth() / $this -> width;
				return ($pixlength);
				break;
			case height :
				$pixlength = $this -> geoheight() / $this -> height;
				return ($pixlength);
				break;
		}
	}
	function geowidth()
	{
		$geowidth = $this -> getMaxX() - $this -> getMinX();
		return ($geowidth);
	}
	function geoheight()
	{
		$geoheight = $this -> getMaxY() - $this -> getMinY();
		return ($geoheight);
	}
	function getExtentAsPolygon()
	{
		$a=$this -> getMinX()." ".$this -> getMinY();
		$b=$this -> getMinX()." ".$this -> getMaxY();
		$c=$this -> getMaxX()." ".$this -> getMaxY();
		$d=$this -> getMaxX()." ".$this -> getMinY();
		return($a.",".$b.",".$c.",".$d);
	}
	function getExtentAsStr()
	{
		$str = $this -> getMinX().",".$this -> getMinY().",".$this -> getMaxX().",".$this -> getMaxY();
		return($str);
	}
	function navigate()
	{
		global $HTTP_FORM_VARS;
		global $pointArrayRegen;
		$map_x=$HTTP_FORM_VARS["map_x"];
		$map_y=$HTTP_FORM_VARS["map_y"];
		$RubberWidth=$HTTP_FORM_VARS["RubberWidth"];
		$RubberHeight=$HTTP_FORM_VARS["RubberHeight"];

		$pan_x = $this -> getMinX() + ($this -> d[x] * $map_x) / $this -> width;
		$pan_y = $this -> getMaxY() - ($this -> d[y] * $map_y) / $this -> height;
		$zoomfactor = 2;


		switch ($this -> tool)
		{

			case "dragpan" :
				$this -> setproportion();
				$diffGeo[0] = $this -> dragX*$this -> pixlength('width')*2;
				$diffGeo[1] = ($this -> dragY*$this -> pixlength('width'))*2;
				$this -> setMinX($this -> getMinX()-$diffGeo[0]);
				$this -> setMinY($this -> getMinY()+$diffGeo[1]);
				$this -> setMaxX($this -> getMaxX()-$diffGeo[0]);
				$this -> setMaxY($this -> getMaxY()+$diffGeo[1]);
				//echo "<script>alert('".$diffGeo[0].",".$diffGeo[1]."')</script>";
				//echo "<script>alert('".print_r($diff)."')</script>";

				break;
			case "pan" :
				$this -> setMinX($pan_x - ($this -> d[x] / 2.0));
				$this -> setMaxX($pan_x + ($this -> d[x] / 2.0));
				$this -> setMinY($pan_y - ($this -> d[y] / 2.0));
				$this -> setMaxY($pan_y + ($this -> d[y] / 2.0));
				break;
			case "zoomin" :
				if ($RubberWidth != NaN
				&& $RubberWidth != 0
				&& $RubberHeight != NaN
				&& $RubberHeight != 0)
				{
					$x1 = $map_x;
					$x2 = $map_x - $RubberWidth;
					$y1 = $map_y;
					$y2 = $map_y - $RubberHeight;
					if ($x1 < $x2)
					{
						$minx = $x1;
						$maxx = $x2;
					} else
					{
						$minx = $x2;
						$maxx = $x1;
					}
					if ($y1 < $y2)
					{
						$maxy = $y1;
						$miny = $y2;
					} else
					{
						$maxy = $y2;
						$miny = $y1;
					}
					$geominx = $this -> pixtogeoX($minx);
					$geominy = $this -> pixtogeoY($miny);
					$geomaxx = $this -> pixtogeoX($maxx);
					$geomaxy = $this -> pixtogeoY($maxy);
					$this -> setMinX($geominx);
					$this -> setMinY($geominy);
					$this -> setMaxX($geomaxx);
					$this -> setMaxY($geomaxy);

				} else
				{
					$zoomfactor = 2;
					$this -> d[x] = $this -> d[x] / $zoomfactor;
					$this -> d[y] = $this -> d[y] / $zoomfactor;
					$this -> setMinX($pan_x - ($this -> d[x] / 2.0));
					$this -> setMaxX($pan_x + ($this -> d[x] / 2.0));
					$this -> setMinY($pan_y - ($this -> d[y] / 2.0));
					$this -> setMaxY($pan_y + ($this -> d[y] / 2.0));
				}

				break;
			case "zoomout" :
				$this -> d[x] = $this -> d[x] * $zoomfactor;
				$this -> d[y] = $this -> d[y] * $zoomfactor;
				$this -> setMinx($pan_x - ($this -> d[x] / 2.0));
				$this -> setMaxx($pan_x + ($this -> d[x] / 2.0));
				$this -> setMiny($pan_y - ($this -> d[y] / 2.0));
				$this -> setMaxy($pan_y + ($this -> d[y] / 2.0));
				break;
		}
		// Convert the the http geo coords back to pix and set the global var $pointArrayRegen
		// The pix coords will be used to regenerate the js-graphic
		$this -> setproportion();
		if ($pointArrayRegen!="")
		{
			$pointArrayRegen=$this->convertpointarray_geo($this->pointArrayRegenGeo,"");
		}

	}
	function getinfo($infoFormat)
	{
		global $HTTP_FORM_VARS;
		$map_x=$HTTP_FORM_VARS["map_x"];
		$map_y=$HTTP_FORM_VARS["map_y"];
		global $serviceObject;
		$pointArrayQuery = $map_x.",".$map_y;
		$this -> renderGeometry($pointArrayQuery, "point", 1, $r, $g, $b);
		if ($this -> units == 'xy')
		{
			$BBOX =
			$this -> minx
			.","
			.$this -> miny
			.","
			.$this -> maxx
			.","
			.$this -> maxy;
		}
		if ($this -> units == 'degrees')
		{
			$BBOX =
			$this -> BBOX =
			$this -> dminx
			.","
			.$this -> dminy
			.","
			.$this -> dmaxx
			.","
			.$this -> dmaxy;
		}
		$url =
		$serviceObject[$this -> queryServiceNum] -> server
		."VERSION=1.1.0&REQUEST=GetFeatureInfo&BBOX="
		.$BBOX
		."&WIDTH="
		.$this -> width
		."&HEIGHT="
		.$this -> height
		."&query_layers="
		.$this -> queryLayerName
		."&layers="
		.$this -> queryLayerName
		."&info_format="
		.$infoFormat
		."&x="
		.$map_x
		."&y="
		.$map_y;
		if ($this -> proj != "")
		$url = $url."&SRS=EPSG:".$this -> proj;
		//echo "<!-- getfeature url ".$url." -->";
		$fp = @file_get_contents($url);
		return ($fp);
	}
	function getCRSfromDB($value, $field)
	{
		global $postgisObject;
		global $CRSdatabase;
		$query = ("select * from crs where code=".$value);
		if ($CRSdatabase=="mysql")
		{
			$conn = db_connect();
			@ $result = mysql_query($query);
			@ $row = mysql_fetch_array($result);
		}
		else
		{
			@ $result = pg_exec($postgisObject->connect(), $query);
			@ $row = pg_fetch_array($result);
		}

		if ($field == 'name')
		return $row[name];
		if ($field == 'kind')
		return $row[kind];
	}
	function crs()
	{
		if ($this -> proj != "")
		{
			$this -> crsName = $this -> getCRSfromDB($this -> proj, name);
			if ($this -> crsName == '')
			$this -> crsName = 'Unkown CRS';
			$kind = $this -> getCRSfromDB($this -> proj, kind);
			if ($kind == 'projected')
			{
				$this -> units = 'xy';
			}
			if ($kind == 'geographic 2D')
			{
				$this -> units = 'degrees';
			}
			if ($kind == '')
			{
				$this -> notInDb = true;
			}
		}
	}
	function setproportion()
	{
		$this -> d[x] = $this -> getMaxX() - $this -> getMinX();
		$this -> d[y] = $this -> getMaxY() - $this -> getMinY();

		if (($this -> width) && ($this -> height) && ($this -> d[x]) && ($this -> d[y]))
		{
			$imageProportion = $this -> width / $this -> height;
			$geoProportion = $this -> d[x] / $this -> d[y];
		}

		if ($imageProportion < $geoProportion)
		{
			$this -> proportion = $this -> height / $this -> width;
			$this -> setMaxY(
			($this -> getMaxY() - $this -> d[y] / 2)
			+ ($this -> height / 2 * $this -> pixlength('width')));
			$this -> setMinY(
			($this -> getMinY() + $this -> d[y] / 2)
			- ($this -> height / 2 * $this -> pixlength('width')));
			$this -> d[y] = $this -> d[x] * $this -> proportion;
		}
		else
		{
			$this -> proportion = $this -> width / $this -> height;
			$this -> setMaxX(
			($this -> getMaxX() - $this -> d[x] / 2)
			+ ($this -> width / 2 * $this -> pixlength('height')));
			$this -> setMinX(
			($this -> getMinX() + $this -> d[x] / 2)
			- ($this -> width / 2 * $this -> pixlength('height')));
			$this -> d[x] = $this -> d[y] * $this -> proportion;
		}
	}
	function CRSdialog()
	{
		global $serviceObject;
		echo "Select a common projection for all services:<br /><br />";
		echo "EPSG:<input type='text' name='proj' value='";
		if (isset($this -> proj))
		{
			echo $this -> proj;
		}
		echo "' /><i> eg. EPSG:4269</i>";
		if ($this -> crsName != '')
		echo "<p>$this->crsName</p>";
		if ($this -> notInDb == true)
		{
			echo "<p>Could not find the crs in the database! Please select units for the crs:</p>";
			echo "<input type='radio' name='units' value='xy'";
			if ($this -> units == 'xy')
			echo " checked";
			echo ">x,y coordinates<br />";
			echo "<input type='radio' name='units' value='degrees'";
			if ($this -> units == 'degrees')
			echo " checked";
			echo ">degrees";
		}
		echo "<p>And here's what the loaded wms say they can do:</p>";
		echo "<table>";
		for ($i = 0; $i <= $this -> serverCount; $i ++)
		{
			echo "<tr><td valign='top'>Service#"
			.$i
			.":</td><td valign='top'> "
			.$serviceObject[$i] -> srs
			."</td></tr>";
		}
		echo "</table><br />";

	}
	function convertpointarray($pointArray) // takes x1,y1|x2,y2 returns x1 y1,x2 y2
	{
		$NewPointArray = explode("|", $pointArray);
		$i = 0;
		while ($NewPointArray[$i] != "")
		{
			$pixCoord = explode(",", $NewPointArray[$i]);
			$geoCoord[0] = $this -> pixtogeoX($pixCoord[0]);
			$geoCoord[1] = $this -> pixtogeoY($pixCoord[1]);
			$newGeoCoord[$i] = implode(" ", $geoCoord);
			$i ++;
		}
		$NewPointArray = implode(",", $newGeoCoord);
		return ($NewPointArray);
	}
	function pixtogeoX($pixCoord)
	{
		if ($this -> units == 'xy')
		{
			$geowidth = $this -> maxx - $this -> minx;
			$pixlength = $geowidth / $this -> width;
			$geoCoord = $this -> minx + $pixCoord * $pixlength;
			return ($geoCoord);
		}
		if ($this -> units == 'degrees')
		{
			$geowidth = $this -> dmaxx - $this -> dminx;
			$pixlength = $geowidth / $this -> width;
			$geoCoord = $this -> dminx + $pixCoord * $pixlength;
			return ($geoCoord);
		}
	}
	function pixtogeoY($pixCoord)
	{
		if ($this -> units == 'xy')
		{
			$geowidth = $this -> maxx - $this -> minx;
			$pixlength = $geowidth / $this -> width;
			$geoCoord = $this -> maxy - $pixCoord * $pixlength;
			return ($geoCoord);
		}
		if ($this -> units == 'degrees')
		{
			$geowidth = $this -> dmaxx - $this -> dminx;
			$pixlength = $geowidth / $this -> width;
			$geoCoord = $this -> dmaxy - $pixCoord * $pixlength;
			return ($geoCoord);
		}
	}
	function convertpointarray_geo($pointArray,$units="") //Takes (wkt) x1 y1,x2 y2 returns x1,y1|x2,y2
	{
		$NewPointArray = explode(",", $pointArray);
		$i = 0;
		while ($NewPointArray[$i] != "")
		{
			$geoCoord = explode(" ", $NewPointArray[$i]);
			$pixCoord[0] = $this -> geotopixX($geoCoord[0], $units);
			$pixCoord[1] = $this -> geotopixY($geoCoord[1], $units);
			$newPixCoord[$i] = implode(",", $pixCoord);
			$i ++;
		}
		$NewPointArray = implode("|", $newPixCoord);
		return ($NewPointArray);
	}
	function geotopixX($geoCoord, $units)
	{
		if ($this -> units == 'xy')
		{
			$geowidth = $this -> maxx - $this -> minx;
			$mapUnit = $this -> width / $geowidth;
			$pixCoord = ($geoCoord - $this -> minx) * $mapUnit;
			return (round($pixCoord, 0));

		}
		if ($this->units == 'degrees')
		{
			$geowidth = $this -> dmaxx - $this -> dminx;
			$mapUnit = $this -> width / $geowidth;
			$pixCoord = ($geoCoord - $this -> dminx) * $mapUnit;
			return (round($pixCoord, 0));
		}
	}
	function geotopixY($geoCoord, $units)
	{
		if ($this -> units == 'xy')
		{
			$geowidth = $this -> maxx - $this -> minx;
			$mapUnit = $this -> width / $geowidth;
			$pixCoord = ($this -> maxy - $geoCoord) * $mapUnit;
			return (round($pixCoord, 0));
		}
		if ($this->units == 'degrees')
		{
			$geowidth = $this -> dmaxx - $this -> dminx;
			$mapUnit = $this -> width / $geowidth;
			$pixCoord = ($this -> dmaxy - $geoCoord) * $mapUnit;
			return (round($pixCoord, 0));
		}
	}
	function getScale()
	{
		$scale=$this->geowidth()*($this->width/72);
		return($scale);
	}
	function measureDialog()
	{
		global $languageText;
		echo "<script><!--\n var languageTextNodes='$languageText[nodes]';\n --></script>";
		echo "<script><!--\n var languageTextLength='$languageText[length]';\n --></script>";
		echo "<script><!--\n var languageTextTotal='$languageText[total]';\n --></script>";
		echo "<script><!--\n var languageTextArea='$languageText[area]';\n --></script>";
		echo "<script><!--\n var languageTextDeleteNode='$languageText[deleteNode]';\n --></script>";
		echo "<script><!--\n var languageTextCloneNode='$languageText[cloneNode]';\n --></script>";
		echo "<script><!--\n var languageTextDeleteFeature='$languageText[deleteFeature]';\n --></script>";
		echo "<script><!--\n var languageTextAddShape='$languageText[addShape]';\n --></script>";
		echo "<script><!--\n var languageTextdoubleClick='$languageText[doubleClick]';\n --></script>";
		echo "<script><!--\n var languageTextInterRuptDigi='$languageText[interRuptDigi]';\n --></script>";

		echo "<table class='postgis'><tr><td><a style='color: #000000;' href='javascript:popArray();'>$languageText[undo]</a>&nbsp;&nbsp;";
		echo "<a style='color: #000000;' href='javascript:redraw();'>$languageText[redraw]</a></td></tr></table>";
	}
	function outputvaribles()
	{
		echo "<div class='postgis' id='output' style='background-color:#ffffff;border-style:solid;border-width:1px;border-color:#000000; position: absolute;visibility: visible;height: 200px;width:250px;left:800px;top:300px'>";
		echo "minx ".$this -> minx."<br>";
		echo "maxx ".$this -> maxx."<br>";
		echo "miny ".$this -> miny."<br>";
		echo "maxy ".$this -> maxy."<br>";
		echo "Rminx ".$this -> pRubberMinx."<br>";
		echo "Rmaxx ".$this -> pRubberMaxx."<br>";
		echo "Rminy ".$this -> pRubberMiny."<br>";
		echo "Rmaxy ".$this -> pRubberMaxy."<br>";
		global $map_x;
		global $map_y;
		echo "map.x ".$map_x."<br>";
		echo "map.y ".$map_y."<br>";
		global $RubberWidth;
		global $RubberHeight;
		echo "Width ".$RubberWidth."<br>";
		echo "Height ".$RubberHeight."<br><br>";
		global $newMinx;
		global $newMiny;
		global $newMaxx;
		global $newMaxy;
		echo "new minx ".$newMinx."<br>";
		echo "new maxx ".$newMaxx."<br>";
		echo "new miny ".$newMiny."<br>";
		echo "new maxy ".$newMaxy."<br>";
		echo "<br>fd".$this -> getMaxX();
		echo "</div>";
	}
	function directGetmap($url,$layers)
	{
		global $BBOX;
		//$this -> setproportion();
		$BBOX =
		$this -> getMinX()
		.","
		.$this -> getMinY()
		.","
		.$this -> getMaxX()
		.","
		.$this -> getMaxY();

		$wmsUrl =
		$url
		."REQUEST=GetMap&BBOX="
		.$BBOX
		."&WIDTH=$this->width&HEIGHT=$this->height&layers=$layers&TRANSPARENT=false";
		if ($this -> proj != "")
		$wmsUrl = $wmsUrl."&SRS=EPSG:".$this -> proj;
		if (!$mapImage = imagecreatefrompng($wmsUrl))

		$mapImage = imagecreatefromjpeg($wmsUrl);

		//echo "<!-- wmsurl".$wmsUrl."-->";
		return ($mapImage);
	}
	function createMapImage($path,$imgPrefix,$imageLabel,$imgName=FALSE,$mergeOverlay=FALSE)
	{
		global $serviceObject;
		global $render_im;
		global $getWMSmapForBackGround;
		global $getWMSmapForBackGroundLayers;
		$session_id = rand(10000, 99999);
		$time = time();
		if (!$imgName) {
			$imgName = "mapimage".$imgPrefix.$session_id.$time.".png";
			$imgPath = $path.$imgName;

		}
		else {
			$imgPath = $path.$imgName.".png";
		}
		if ($this -> doNotRenderMap==false) {
			$im = imagecreatetruecolor($this -> width, $this -> height);
			$background_color = ImageColorAllocate( $im, 160, 202, 214);
			imagefill($im, 0, 0, $background_color);
			// get a back from a WMS if requested
			if ($getWMSmapForBackGround && $getWMSmapForBackGroundLayers && $this->doNotUseBackGroundWMS==FALSE)
			{
				$wmsMap = $this -> directGetmap($getWMSmapForBackGround,$getWMSmapForBackGroundLayers);
				//imagecolortransparent($wmsMap);
				imagecopy($im,$wmsMap,0,0,0,0,$this -> width,$this -> height);
				imagedestroy($wmsMap);
			}

			if (!$this->doNotUseWMS)
			{
				for ($i = 0; $i <= $this -> serverCount; $i ++)
				{
					$map[$i] = $serviceObject[$i] -> getmap($i,$this);
					if ($map[$i]) {
						imagecolortransparent($map[$i]);
						imagecopy(
						$im,
						$map[$i],
						0,
						0,
						0,
						0,
						$this -> width,
						$this -> height);
						imagedestroy($map[$i]);
					}
				}
			}
			if ($this->mapscript -> mapfileUrl)
			{
				$ms_im = imagecreatefrompng($this->mapscript -> drawImage());
				imagecolortransparent($ms_im,0);
				imagecopy($im, $ms_im, 0, 0, 0, 0, $this -> width, $this -> height);
				imagedestroy($ms_im);
				imagepng($im, $imgPath);
			}
			$text_color = imagecolorallocate($im, 233, 14, 91);
			if ($this->mapscript -> mapfileUrl)
			{
				$scalebarImg =
				imagecreatefrompng($this->mapscript -> drawScalebar());
				ImageColorTransparent($scalebarImg,0);
				imagecopy(
				$im,
				$scalebarImg,
				0,
				$this -> height - 50,
				0,
				0,
				imagesx($scalebarImg),
				imagesy($scalebarImg));
				imagedestroy($scalebarImg);
			}

		}

		if ($render_im[$this -> controlId])
		{

		}
		else
		{
			$render_im[$this -> controlId] = imagecreate($this -> width, $this -> height);
			$background_color = imagecolorallocate($render_im[$this -> controlId], 255, 255, 255);
		}
		imagecolortransparent($render_im[$this -> controlId],0);

		// Put on label and writte the map image to file then detroy the resource
		if ($this -> doNotRenderMap==false)
		{
			if($mergeOverlay)
			{
				imagecopy($im,$render_im[$this -> controlId],0,0,0,0,$this -> width,$this -> height);
			}
			imagestring($im, 1.5, 3, 3, $imageLabel, $text_color);
			imagepng($im, $imgPath);
			imagedestroy($im);
		}
		// Write and destroy the overlay resource
		imagepng($render_im[$this -> controlId], $path."overlay".$imgName);
		imagedestroy($render_im[$this -> controlId]);
		$this -> set_bbox_form();
		return($imgName);
	}
	function gml()
	{
		$this -> parser = xml_parser_create();
		xml_set_object($this -> parser, $this);
		xml_set_element_handler($this -> parser, "startElement", "endElement");
		xml_set_character_data_handler($this -> parser, "characterData");
	}
	function parse($gmlSource)
	{
		global $data;
		global $title_row;
		global $data_row;
		global $table_title;
		global $currentTag;
		global $check;
		global $index;
		global $check2;
		global $postGisQueryName;
		global $the_geom;
		global $postGisQueryColor;

		if (!$index)
		$index = 0;
		$check = false;
		$this -> gml();
		// data file
		$currentTag = "";
		$title_row = "";
		$data_row = "";
		// open XML file
		xml_parse($this -> parser, $gmlSource);
		xml_parser_free($this -> parser);
		if ($postGisQueryColor[$table_title] != "")
		$tabelColor = $postGisQueryColor[$table_title];
		else
		$tabelColor = "#ffff00";
		if ($postGisQueryName[$table_title] != "")
		$table_title_new = $postGisQueryName[$table_title];
		else
		$table_title_new = $table_title;

		if ($data_row) {
			$table_title_new = "<table><tr><td><b class='output_table_row' style='background:".$tabelColor.";'>".$table_title_new."</b></td></tr></table>";
			if (strLen($title_row)==0) $table_title_new="";
			$dumpTable = $table_title_new."<table border=0><tr class='output_table_row'>"
			.$title_row
			."<tr>"
			.$data_row
			."</table>";
		}
		else {
			$dumpTable = null;
		}
		return (urldecode($dumpTable));
	}
	function startElement($parser, $name, $attrs)
	{
		global $title_row;
		global $check;
		global $table_title;
		global $currentTag;
		global $check2;
		global $postGisQueryTabel;
		global $postGisQueryField;
		global $postGisQueryFieldName;
		global $postGisQueryFieldLink;
		global $index;
		global $check3;
		global $data_row;
		global $w;
		global $the_geom;
		$the_geom = false;
		$currentTag = $name;
		//echo "<script>alert('".$table_title."');</script>";
		if ($currentTag == "GML:COORDINATES") $the_geom = true;
		if (substr($currentTag, -6) == "_LAYER")
		{
			//echo "<script>alert('$currentTag');</script>";
			$table_title = substr($currentTag, 0, (strlen($currentTag) - 6));
			for ($w = 0; $w < sizeof($postGisQueryTabel); $w ++)
			{
				if ($table_title == $postGisQueryTabel[$w])
				{

					$index = $w;
				}

			}
		} else

		if (substr($currentTag, -8) == "_FEATURE")
		{

		} else
		if ($currentTag != "MSGMLOUTPUT" && substr($currentTag, 0, 3) != "GML")
		{
			if ($currentTag == "gml:coordinates")
			$the_geom = true;
			if ($postGisQueryTabel[$index] == $table_title)
			{

				for ($u = 0;
				$u
				< sizeof($postGisQueryField[$postGisQueryTabel[$index]]);
				$u ++)
				{
					if ($currentTag == $postGisQueryField[$postGisQueryTabel[$index]][$u])
					{
						if ($postGisQueryFieldName[$table_title][$currentTag]!= "")
						$fieldName = $postGisQueryFieldName[$table_title][$currentTag];
						else
						$fieldName = $currentTag;
						if ($check != true)
						$title_row = $title_row
						."<td class='output-table-cell'><b>"
						.$fieldName
						."<b></td>";
						$check2 = true;
					}
				}
			} else
			{
				$check2 = true;
				//$check3 = false; // no increase in $index
				if ($check != true && ($currentTag != "GID" && $currentTag != "FID") ) {
					if ($postGisQueryFieldName[$table_title][$currentTag]!="") $currentTagNewName = $postGisQueryFieldName[$table_title][$currentTag];
					else $currentTagNewName = $currentTag;
					$title_row =
					$title_row."<td class='output-table-cell'><b>".$currentTagNewName."<b></td>";}
			}
			//}
		}
	}
	function endElement($parser, $name)
	{
		global $title_row;
		global $data_row;
		global $check;
		global $currentTag;
		global $w;
		if (substr($name, -8) == "_FEATURE")
		{
			$data_row = $data_row."</tr><tr>";
			$check = true;
		}
		// clear current tag variable
		$currentTag = "";
	}
	function characterData($parser, $data)
	{
		global $data_row;
		global $currentTag;
		global $check2;
		global $title_row;
		global $w;
		global $postGisQueryFieldLink;
		global $postGisQueryLinkTarget;
		global $postGisQueryContentLink;
		global $postGisQueryDataPrefix;
		global $postGisQueryLinkStyle;
		global $postGisGetPlanUrl;
		global $postGisQueryReference;
		global $postGisQueryCustomFunction;
		global $table_title;
		global $postGisQueryUrlEncode;
		global $gid;
		global $the_geom;
		global $iLangID;
		global $postgisObject;

		if ($currentTag == "GID")
		$gid = $data;
		if ($check2 == true && ($currentTag != "GID" && $currentTag != "FID"))
		{
			if ($postGisQueryCustomFunction[$table_title][$currentTag])
			{
				$data = altValue($data);
			}
			if ($postGisQueryReference[$table_title][$currentTag])
			{
				$__array=explode(",",$postGisQueryReference[$table_title][$currentTag]);
				$data=$postgisObject->referenceTableLookup($__array[0],$__array[1],$__array[2],$data);
			}
			if ($postGisQueryFieldLink[$table_title][$currentTag] == "" && $postGisQueryContentLink[$table_title]!=$currentTag && $postGisGetPlanUrl[$table_title]!=$currentTag && $postGisQueryUrlEncode[$table_title][$currentTag]=="")
			{
				$data_row = $data_row."<td class='output-table-cell'>".$data."</td>";
			}
			if ($postGisQueryFieldLink[$table_title][$currentTag] != "" && $postGisGetPlanUrl[$table_title]!=true)
			{
				$data_row =
				$data_row
				."<td class='output-table-cell'><a ".$postGisQueryLinkStyle[$table_title]." "
				.$postGisQueryLinkTarget[$table_title]
				." href='"
				.$postGisQueryFieldLink[$table_title][$currentTag]
				."?table="
				.$table_title
				."&gid="
				.$gid
				."'>"
				.$data
				."</a></td>";
			}
			if($postGisQueryContentLink[$table_title]== $currentTag && $postGisGetPlanUrl[$table_title]!=$currentTag)
			{
				$newData="<a ".$postGisQueryLinkStyle[$table_title]." href='".$postGisQueryDataPrefix[$table_title].urldecode($data)."' ".$postGisQueryLinkTarget[$table_title].">".urldecode($data)."</a>";
				$data=$newData;
				$data_row = $data_row."<td class='output-table-cell'>".$data."</td>";
				//echo "<script>alert(\"$newData\");</script>";
			}
			if ($postGisQueryUrlEncode[$table_title][$currentTag] && $postGisQueryContentLink[$table_title]!= $currentTag)
			{
				$newData=urldecode($data);
				$data=$newData;
				$data_row = $data_row."<td class='output-table-cell'>".$data."</td>";
			}
			if($postGisGetPlanUrl[$table_title]==$currentTag && $postGisGetPlanUrl[$table_title]!="")
			{
				$arrContentInfo = getPlanContentInfoByKeyValue($data);
				$newData="<a ".$postGisQueryLinkStyle[$table_title]." href='".getPageURL($arrContentInfo["IPAGEID"], $iLangID)."' ".$postGisQueryLinkTarget[$table_title].">$data</a>";
				$data=$newData;
				$data_row = $data_row."<td class='output-table-cell'>".$data."</td>";
			}
			$check2 = false;
		}
	}
	function renderGeometry($pointArrayQuery, $geometry, $brush, $r, $b, $g) //takes pixel coords
	{
		$this -> renderGeometryArray[] = array($this -> convertpointarray($pointArrayQuery), $geometry, $brush, $r, $b, $g);
		global $render_im;
		$imgName = "render.png";
		if (!$render_im[$this -> controlId])
		$render_im[$this -> controlId] = imagecreate($this -> width, $this -> height);
		$background_color = imagecolorallocate($render_im[$this -> controlId], 255, 255, 255);
		$NewPointArray = explode("|", $pointArrayQuery);
		$line_color = imagecolorallocate($render_im[$this -> controlId], 34, 255, 91);
		$brush_im = imagecreate($brush, $brush);
		$brush_color = imagecolorallocate($brush_im, $r, $b, $g);
		imageLine($brush_im, 8, 0, 8, 16, $brush_color);
		imageLine($brush_im, 0, 8, 16, 8, $brush_color);
		imagesetbrush($render_im[$this -> controlId], $brush_im);
		$geometry=strtoupper($geometry);
		if ($geometry=="MULTIPOINT") $geometry="POINT";
		if ($geometry=="MULTILINE" || $geometry=="MULTILINESTRING" || $geometry=="LINESTRING") $geometry="LINE";
		if ($geometry=="MULTIPOLYGON") $geometry="LINE";
		//echo "<script>alert('$geometry')</script>";
		switch ($geometry)
		{
			case "POINT" :
				for ($i = 0; $i < sizeof($NewPointArray); $i ++)
				{
					$pixCoord = explode(",", $NewPointArray[$i]);
					$marker_im = imagecreate(11, 11);
					$background_color =
					imagecolorallocate($marker_im, 255, 255, 255);
					$line_color = imagecolorallocate($marker_im, $r, $b, $g);
					imageLine($marker_im, 5, 0, 5, 11, $line_color);
					imageLine($marker_im, 0, 5, 11, 5, $line_color);
					imagecopy(
					$render_im[$this -> controlId],
					$marker_im,
					$pixCoord[0] - 5,
					$pixCoord[1] - 5,
					0,
					0,
					11,
					11);
					imagedestroy($marker_im);
				}
				break;
			case "LINE" :
				//	echo "<script>alert('line')</script>";
				for ($i = 0; $i < sizeof($NewPointArray); $i ++)
				{
					for ($u = 0; $u < 2; $u ++)
					{
						$pixCoord[$u] = explode(",", $NewPointArray[$i + $u]);
					}
					if ($pixCoord[1][0])
					imageLine(
					$render_im[$this -> controlId],
					$pixCoord[0][0],
					$pixCoord[0][1],
					$pixCoord[1][0],
					$pixCoord[1][1],
					IMG_COLOR_BRUSHED);
					//		echo "<script>alert('".$pixCoord[1][0]."','".$pixCoord[1][1]."');</script>";
				}
				break;
			case "POLYGON" :
				//echo "<script>alert('polygon')</script>";
				$pointArrayQuery = str_replace("|", ",", $pointArrayQuery);
				$array = explode(",", $pointArrayQuery);
				@imagepolygon(
				$render_im[$this -> controlId],
				$array,
				sizeof($NewPointArray),
				IMG_COLOR_BRUSHED);
				break;
		}
		//$test=imagepng($render_im[$this -> controlId], "render.png");
		//echo "<script>alert('render=$test')</script>";
		imagedestroy($brush_im);
	}
	function viewRegion($coord) // takes "MinX,MinY,MaxX,MaxY"
	{
		$coord = explode(",", $coord);
		$this -> setMinX($coord[0]);
		$this -> setMinY($coord[1]);
		$this -> setMaxX($coord[2]);
		$this -> setMaxY($coord[3]);
		$this -> setproportion();
	}
	function setExtentFromGeoObj($geoObj,$buffer=0)
	{
		$this -> setMinX($geoObj -> getMinX() - $buffer);
		$this -> setMinY($geoObj -> getMinY() - $buffer);
		$this -> setMaxX($geoObj -> getMaxX() + $buffer);
		$this -> setMaxY($geoObj -> getMaxY() + $buffer);
		$this -> setproportion();
	}
	function renderFromWKT($wktArray,$persistent=true, $r=255, $b=0, $g=0)
	{
		$__geofactory=new geometryfactory;
		$__geoObjCol =$__geofactory->createGeometryCollection($wktArray);
		foreach($__geoObjCol->getGeometryArray() as $__geoObj)
		{
			foreach($__geoObj->getShapeArray() as $__key=>$__shape)
			{
				$__pixCoord = $this -> convertpointarray_geo($__shape, "");
				$this -> renderGeometry($__pixCoord, $__geoObj->getGeomType(), 2, $r, $b, $g);
			}
		}
		$form="<input type='hidden' name='WKTstring' value='".implode(";",$wktArray)."' />";
		if ($persistent) return ($form);
		else return;
	}
	function renderFromGML($gml,$persistent=TRUE,$r=255,$b=0,$g=0)
	{
		$gmlCon=new gmlConverter;
		$wktArray=$gmlCon->gmlToWKT($gml);
		if ($wktArray[0])
		{
			foreach ($wktArray[0] as $value)
			{
				$__geofactory=new geometryfactory;
				if (!$this -> control -> proj) $__srid=-1;
				else $__srid="EPSG:".$this -> control -> proj;
				$__geoObj=$__geofactory->createGeometry($value,$__srid);
				$this -> renderFromWKT(array($__geoObj -> getWKT()),$persistent,$r,$b,$g);
			}
		}
	}
}
class service
{
	var $interval;
	var $serviceNum;
	var $layerUrl;
	var $layertitle;
	var $layerTable;
	var $layername;
	var $layerAttrs;
	var $layerCount;
	var $layerlevel;
	var $levelcount;
	var $queryTitle;
	var $srs;
	var $parser;
	var $layerAbstract;
	var $control;
	var $jsTableCheck;
	var $subPressServerForm;
	function service($i, $server, & $controlObject, $subPressServerForm=FALSE) //constructor
	{
		$this -> subPressServerForm=$subPressServerForm;
		$this -> control= & $controlObject;
		if ($server != "")
		{
			$u = $i +1;
			$this -> serviceNum = $i;
			$this -> server = $server;
			$this -> interval = $u * 100;
			$this -> xml();
			$this -> parse();
			$this -> serverForm();
		}
	}
	function setControlObject(& $controlObject)
	{
		$this -> control = & $controlObject;
	}
	function serverForm()
	{
		if (!$this -> subPressServerForm) echo "<input type='hidden' name='server_$this->serviceNum' value='".$this -> server."' />";
	}
	function xml()
	{
		$this -> parser = xml_parser_create();
		xml_set_object($this -> parser, $this);
		xml_set_element_handler($this -> parser, "startElement", "endElement");
		xml_set_character_data_handler($this -> parser, "characterData");
	}
	function parse()
	{
		global $data;
		global $num;
		$currentTag = "";
		$serviceNum = $i;
		$file = $this -> server."VERSION=1.1.0&REQUEST=GetCapabilities";
		// open XML file
		$fp = fopen($file, "r");
		while ($data = fread($fp, 100000)) //		{
		xml_parse($this -> parser, $data);
		// clean up
		xml_parser_free($this -> parser);
		$this -> layerCount = $num;
		//Number of layer in each service is saved in a array;
		$num = 0; // Ready for a new service
		$layerleveltrack = 0;
	}
	function layercontrol()
	{
		global $HTTP_FORM_VARS;
		$queryLayer=$HTTP_FORM_VARS["queryLayer"];
		//Globalizing variable variables
		for ($count = 2 + $this -> interval;$count <= $this -> layerCount + $this -> interval;$count ++)
		{
			$temp = "layer_$count";
			global $$temp;
		}
		echo "<table border='0'>";
		for ($count = 1 + $this -> interval;$count <= $this -> layerCount + $this -> interval;$count ++)
		{
			if ($this -> layerlevel[$count - $this -> interval] == 1)
			{
				echo "<tr><td align='middle'><IMG border='0' SRC='images/icon_eye.gif' WIDTH='17' HEIGHT='11'></td><td align='middle'><IMG border='0' SRC='images/thmIdOn13x13.gif' WIDTH='13' HEIGHT='13'></td><td><i>".$this -> layertitle[$count - $this -> interval]."[Title]</i></td></tr>";
			}
			if ($this -> layerlevel[$count - $this -> interval] < $this -> layerlevel[$count - $this -> interval - 1] && $this -> layerlevel[$count - $this -> interval] < $this -> layerlevel[$count - $this -> interval + 1] && $this -> layerlevel[$count - $this -> interval] != 1)
			{
				echo "<tr><td></td><td></td><td valign='middle'><b>";
				for ($i = 2;$i <= $this -> layerlevel[$count - $this -> interval];$i ++)
				echo "<img src='images/bullet_next.gif' width='10' height='15' align='middle'>";
				echo $this -> layertitle[$count - $this -> interval]." [Theme]</b></td></tr>";
			}
			if ($this -> layerlevel[$count - $this -> interval] > $this -> layerlevel[$count - $this -> interval - 1] && $this -> layerlevel[$count - $this -> interval] < $this -> layerlevel[$count - $this -> interval + 1] && $this -> layerlevel[$count - $this -> interval] != 1)
			{
				echo "<tr><td></td><td></td><td valign='middle'><b>";
				for ($i = 2;$i <= $this -> layerlevel[$count - $this -> interval];$i ++)
				echo "<img src='images/bullet_next.gif' width='10' height='15' align='middle'>";
				echo $this -> layertitle[$count - $this -> interval]." [Theme]</b></td></tr>";
			}
			if ($this -> layerlevel[$count - $this -> interval] >= $this -> layerlevel[$count - $this -> interval + 1])
			{
				echo "<tr><td><input id=\"".$this -> layername[$count - $this -> interval]."\" onclick='javascript:loadLayer(".$count.",\"".$this -> layername[$count - $this -> interval]."\",\"".$this -> layertitle[$count - $this -> interval]."\",\"".$this -> layername[$count - $this -> interval]."\",\"".$opacity."\",\"".$this -> server."\",this)' type='checkbox' name='layer_$count' value='on'";
				$temp = "layer_$count";
				if ($$temp == "on")
				{
					echo " checked";
					$this -> layerUrl = $this -> layerUrl.$this -> layername[$count - $this -> interval].",";
				}
				echo "></td><td valign='middle'>";
				if ($this -> layerAttrs[$count - $this -> interval]["queryable"] == 1)
				{
					echo "<input type='radio' name='queryLayer' value='$count'";
					//set query attributes in the control object
					if ($queryLayer == $count)
					{
						echo " checked>";
						$this -> control -> queryServiceTitle = $this -> layertitle[1];
						$this -> control -> queryServiceNum = $this -> serviceNum;
						$this -> control -> queryLayerName = $this -> layername[$count - $this -> interval];
						$this -> control -> queryLayerTitle = $this -> layertitle[$count - $this -> interval];
					}
					elseif ($this -> queryLayer != $count) echo ">";
				}
				echo "</td><td valign='middle'>";
				for ($i = 2;$i <= $this -> layerlevel[$count - $this -> interval];$i ++)
				echo "<img align='middle' src='images/bullet_next.gif' width='10' height='15' border='0'>";
				echo $this -> layertitle[$count - $this -> interval];
				echo "</td><td>";
				if ($this -> layerAbstract[$count - $this -> interval])
				{
					echo "<img src='images/info.gif' onmouseover=\"return escape('".$this -> layerAbstract[$count - $this -> interval]."')\">";
				}
				echo "</td></tr>\n";
			}
		}
		echo "</table>";
	}
	function layercontrolExpandableMenu()
	{
		global $HTTP_FORM_VARS;
		global $defaultLayers;
		$queryLayer=$HTTP_FORM_VARS["queryLayer"];
		//Globalizing variable variables
		for ($count = 2 + $this -> interval;
		$count <= $this -> layerCount + $this -> interval;
		$count ++)
		{
			$temp = "layer_$count";
			global $$temp;
		}
		for ($count = 1 + $this -> interval;
		$count <= $this -> layerCount + $this -> interval;
		$count ++)
		{
			if ($this -> layerlevel[$count - $this -> interval] == 1)
			{
				$jsStr.="{item:new outlineItem('".$this -> layertitle[$count
				- $this -> interval]."'),childNodes:[";
			}
			if ($this -> layerlevel[$count - $this -> interval] > 1)
			{
				// every layer is written out to the javascript string var
				$jsStr.="{item:new outlineItem('".$this -> layertitle[$count
				- $this -> interval]."', 'layer_$count'";
				$temp = "layer_$count";
				if ($defaultLayers) { $defaultLayersArray=explode(",",$defaultLayers);
				// Switch defaultlayers on
				foreach($defaultLayersArray as $m)
				{
					if ($this->layername[$count - $this -> interval]==$m)
					{
						$$temp = "on";
						if ($this -> layerTable[$count - $this -> interval])// if postgis query
						{
							$this -> jsTableCheck.="<script>tableCheck('".$this -> layerTable[$count - $this -> interval]."','check');</script>";
						}
					}
				}
				}
				if ($$temp == "on")
				{
					$jsStr.= ",'checked'";
					$this -> layerUrl =
					$this -> layerUrl.$this -> layername[$count
					- $this -> interval].",";
				}
				else
				$jsStr.=",'notchecked'";
				if ($this -> layerAttrs[$count
				- $this -> interval]["queryable"]
				== "1")
				{
					$jsStr.=",'queryable','$count', '"
					.$this -> layerAbstract[$count
					- $this -> interval]."','".$this -> layerTable[$count
					- $this -> interval]."'";
					//set query attributes in the control object
					if ($queryLayer == $count)
					{
						$jsStr.=",'checked'";
						$this -> control -> queryServiceTitle =
						$this -> layertitle[1];
						$this -> control -> queryServiceNum = $this -> serviceNum;
						$this -> control -> queryLayerName =
						$this -> layername[$count - $this -> interval];
						$this -> control -> queryLayerTitle =
						$this -> layertitle[$count - $this -> interval];
					}
					elseif ($this -> queryLayer != $count); // echo ">";
				} else
				$jsStr.=",'','','".$this -> layerAbstract[$count
				- $this -> interval]."','".$this -> layerTable[$count
				- $this -> interval]."'";
				$jsStr.=",'')";
				//no childnodes, same level
				if ($this -> layerlevel[$count - $this -> interval]
				== $this -> layerlevel[$count - $this -> interval + 1])
				{
					$jsStr.= "}";
					if ($count < $this -> layerCount + $this -> interval)
					$jsStr.=","; //fininsh the node
				}
				//a childnodes, higher level
				if ($this -> layerlevel[$count - $this -> interval]
				< $this -> layerlevel[$count - $this -> interval + 1])
				{
					$jsStr.=",childNodes:[";
				}
				//a childnodes ends, lower level
				if ($this -> layerlevel[$count - $this -> interval]
				> $this -> layerlevel[$count - $this -> interval + 1]
				&& $this -> layerCount != $count
				&& $this -> layerlevel[$count - $this -> interval + 1] != "")
				{
					$jsStr.="}]}";

					$__diff=$this -> layerlevel[$count - $this -> interval] -
					$this -> layerlevel[$count - $this -> interval + 1];
					if ($__diff>1){
						for ($i=1;$i<$__diff;$i++) $jsStr.= "]}";
					}

					if ($count < $this -> layerCount + $this -> interval)
					$jsStr.=","; //fininsh the node
				}
				if ($count == $this -> layerCount + $this -> interval)
				{
					//$jsStr.= "level:".$this -> layerlevel[$count - $this -> interval];
					for ($i = 3;
					$i <= $this -> layerlevel[$count - $this -> interval];
					$i ++)
					{
						$jsStr.= "}]";
					}
				}
			}
		}
		$jsStr.= "}]}";
		$this -> layerUrl =	substr($this -> layerUrl, 0, strlen($this -> layerUrl) - 1);//remove the lat comma
		return($jsStr);
	}
	function jsTableCheck()
	{
		return($this -> jsTableCheck);
	}
	function getmap($i,$controlObject)
	{
		global $BBOX;
		$this -> control -> setproportion();
		$BBOX =
		$this -> control -> getMinX()
		.","
		.$this -> control -> getMinY()
		.","
		.$this -> control -> getMaxX()
		.","
		.$this -> control -> getMaxY();
		//	$showPosition = ""; //OnMouseMove='showPositionGeo(pos);'";
		$wmsUrl =
		$this -> server
		."VERSION=1.1.0&request=GetMap&BBOX="
		.$BBOX
		."&WIDTH=".$this -> control->width."&HEIGHT=".$this -> control->height."&layers=$this->layerUrl&FORMAT=image/png&styles=";
		if ($this -> control -> proj != "")
		$wmsUrl = $wmsUrl."&SRS=EPSG:".$this -> control -> proj;
		$mapImage = imagecreatefrompng($wmsUrl);
		//		echo "<!-- wmsurl ".$wmsUrl."-->";
		return ($mapImage);
	}
	function startElement($parser, $name, $attrs)
	{
		global $layerleveltrack;
		global $styletag;
		global $currentTag;	//used by function characterData when parsing xml data
		global $num; //Each layer is provided with a number
		global $SRS;
		$currentTag = $name;
		$serviceNum = 0;
		// output opening HTML tags
		switch ($currentTag)
		{
			case "BOUNDINGBOX" :
				//parse only if extent is not set yet. The extent is set the first time the page is vistied
				if ($this -> control -> minx == "")
				{
					$this -> control -> minx = $attrs[MINX];
					$this -> control -> miny =
					number_format($attrs[MINY], 0, "", "");
					$this -> control -> maxx = $attrs[MAXX];
					$this -> control -> maxy =
					number_format($attrs[MAXY], 0, "", "");
					$this -> control -> setproportion();
				}
				break;
			case "LATLONBOUNDINGBOX" :
				//parse only if extent is not set yet. The extent is set the first time the page is vistied
				if ($this -> control -> dminx == "")
				{
					$this -> control -> dminx = $attrs[MINX];
					$this -> control -> dminy = $attrs[MINY];
					$this -> control -> dmaxx = $attrs[MAXX];
					$this -> control -> dmaxy = $attrs[MAXY];
					$this -> control -> setproportion();
				}
				break;
			case "LAYER" :
				$this -> levelcount = 0;
				$styletag = 0;
				if (!$layerleveltrack)
				$layerleveltrack = 0;
				if (!$num)
				$num = 0;
				$num = $num +1;
				//increase with one for every layertag parsed. keeps track of number of layers
				$layerleveltrack = $layerleveltrack +1;
				//increase with one for every start-layertag parsed
				$this -> layerAttrs[$num]["queryable"] = $attrs[QUERYABLE];
				$this -> layerlevel[$num] = $layerleveltrack;
				//each layer is provided with a level number
				break;
			case "TITLE" :
				break;
			case "STYLE" :
				$styletag = 1;
				break;
		}
	}
	function endElement($parser, $name)
	{
		global $layerleveltrack;
		global $currentTag;
		global $levelcount;
		global $serviceNum;
		global $num;
		// output closing HTML tags
		switch ($name)
		{
			case "LAYER" :
				$layerleveltrack = $layerleveltrack -1;
				//decrease with one for every stop-layertag parsed
				$this -> levelcount = $this -> levelcount + 1;
				//echo $layerleveltrack;
				break;
			case "TITLE" :
				//echo " ".$name;
				//echo "<br />";
				break;
			case "STYLE" :
				//$styletag=0;
				break;
		}
		// clear current tag variable
		$currentTag = "";
	}
	// process data between tags
	function characterData($parser, $data)
	{
		global $layerleveltrack;
		global $stop;
		global $currentTag;
		global $num;
		global $serviceNum;
		global $styletag;
		$serviceNum = 0;
		switch ($currentTag)
		{
			case "TITLE" :
				if ($num > 0) //Titles of layers has begun
				{
					if ($styletag != 1)
					$this -> layertitle[$num] = $data;
					//echo $layertitle[$serviceNum][$num];
				}
				break;
			case "NAME" :
				if ($num > 0)
				{
					if ($styletag != 1)
					{
						$this -> layername[$num] = $data;
					}
				}
				break;
			case "SRS" :
				{
					if (!$this -> srs) // only the first tag
					$this -> srs = $data;
				}
				break;
			case "ABSTRACT" :
				{
					if ($num > 0) //Titles of layers has begun
					{
						$this -> layerAbstract[$num] = urlencode($data);// String is encode so strang chars not will mess the xml up
					} else
					if ($num == 0)
					$this -> layerAbstract[0] = urlencode($data);
				}
				break;
			case "TABLE" :
				{
					if ($num > 0) //Titles of layers has begun
					{
						$this -> layerTable[$num] = $data;
					} else
					if ($num == 0)
					$this -> layerTable[0] = $data;
				}
				break;
		}
	}
}
class postgis extends control
{
	var $pg_search_layer;
	var $pg_search_field;
	var $pg_search_value;
	var $pg_digi_layer;
	var $pg_digi_field;
	var $pg_digi_value;
	var $postgishost;
	var $postgisuser;
	var $postgisdb;
	var $postgispw;
	var $postgisschema;
	var $theGeometry;
	var $connected;
	var $function;
	var $connectString;
	var $pg_digi_snapTolerance;
	var $wfsFilter;
	var $sfsql;
	var $control;
	var $digiGeom;
	var $editArray;
	var $editForAjax;
	var $renderGeometryArray;
	function postgis() //constructor
	{
		global $HTTP_FORM_VARS;
		global $pg_query_function;
		global $postgishost;
		global $postgisport;
		global $postgisuser;
		global $postgisdb;
		global $postgispw;
		global $postgisschema;
		global $snapTolerance;
		$this -> pg_snap_layer = $HTTP_FORM_VARS["pg_snap_layer"];
		$this -> pg_search_layer = $HTTP_FORM_VARS["pg_search_layer"];
		$this -> pg_search_field = $HTTP_FORM_VARS["pg_search_field"];
		$this -> pg_search_value = $HTTP_FORM_VARS["pg_search_value"];
		$this -> pg_digi_layer = $HTTP_FORM_VARS["pg_digi_layer"];
		$this -> pg_digi_field = $HTTP_FORM_VARS["pg_digi_field"];
		$this -> pg_digi_value = $HTTP_FORM_VARS["pg_digi_value"];
		$this -> pg_query_type = $HTTP_FORM_VARS["pg_query_type"];
		$this -> operation = $HTTP_FORM_VARS["operation"];
		$this -> pg_query_function = $pg_query_function;
		$this -> postgishost = trim($postgishost);
		$this -> postgisport = trim($postgisport);
		$this -> postgisuser = trim($postgisuser);
		$this -> postgisdb = trim($postgisdb);
		$this -> postgispw = trim($postgispw);
		$this -> postgisschema = trim($postgisschema);
		$this -> pg_digi_snapTolerance = $HTTP_FORM_VARS["snapTolerance"];

		if ($this -> pg_digi_layer) $this -> digiGeom = $this -> getGeometryColumns($this -> pg_digi_layer,type);

	}
	function fetchRow(& $result,$result_type="PGSQL_ASSOC")
	{
		$row=pg_fetch_array($result,$result_type);
		//$row=$result->fetchRow();//PEAR
		return($row);
	}
	function numRows($result)
	{
		$num=pg_numrows($result);
		//$num=$result->numRows();//PEAR
		return ($num);
	}
	function free(& $result)
	{
		$test=pg_free_result($result);
		if (!$test) {
			//echo "Could not free result resource";
		}
		//$result->free();//PEAR
	}

	function setControlObject(& $controlObject)
	{
		$this -> control = & $controlObject;
	}
	function execQuery($query)
	{
		//echo $query;
		$__conn=$this -> connect();
		if ($__conn)
		{
			pg_exec($__conn,"set client_encoding='utf8'");
			$result = pg_exec($__conn, $query);
			if (!$result) echo $query;
			return ($result);
		}
	}
	function getMetaData($table)
	{
		$__conn=$this -> connect();
		if ($__conn)
		{
			$arr = pg_meta_data($__conn, $table);
			return ($arr);
		}
	}
	function explodeTableName($table){
		preg_match ("/^[\w'-]*\./",$table,$matches);
		$_schema = $matches[0];

		preg_match ("/[\w'-]*$/",$table,$matches);
		$_table = $matches[0];

		if ($_schema) {
			$_schema = str_replace(".","",$_schema);
		}
		return array("schema"=>$_schema,"table"=>$_table);
		
	}
	function connectString()
	{
		if ($this -> postgishost != "")
		$connectString = "host=".$this -> postgishost;
		if ($this -> postgisport != "")
		$connectString = $connectString." port=".$this -> postgisport;
		if ($this -> postgisuser != "")
		$connectString = $connectString." user=".$this -> postgisuser;
		if ($this -> postgispw != "")
		$connectString = $connectString." password=".$this -> postgispw;
		if ($this -> postgisdb != "")
		$connectString = $connectString." dbname=".$this -> postgisdb;
		return ($connectString);
	}
	function connect()
	{
		$db = pg_connect($this -> connectString());
		if (!$db)
		return false;
		elseif ($db)
		{
			return $db;
		}
	}
	function open()
	{
		@ $db = pg_connect($this -> connectString());
		if (!$db)
		return "<p>Could't connect to PostGreSQL server</p>";
		elseif ($db)
		{
			$this -> connected = true;
			return "<p>Connected to PostGreSQL server</p>";
		}
	}
	function close()
	{
		$close = pg_close($this -> connectString());
		if ($close == false)
		echo "Could not close connection";
	}
	function postGISdialog()
	{
		echo $this -> open();
		echo "<table class='postgis' border =0><tr><td width=90>PostGIS host</td><td><input type='text' name='postgishost' value ='"
		.$this -> postgishost
		."'></td>";
		echo "<td>port</td><td><input type='text' name='postgisport' value='"
		.$this -> postgisport
		."'></td></tr>";
		echo "<tr><td>PostGIS DB</td><td><input type='text' name='postgisdb' value='"
		.$this -> postgisdb
		."'</td></tr>";
		echo "<tr><td>PostGIS user</td><td><input type='text' name='postgisuser' value='"
		.$this -> postgisuser
		."'</td></tr>";
		echo "<tr><td>User password</td><td><input type='password' name='postgispw' value='"
		.$this -> postgispw
		."'</td></tr></table>";
		//echo "<p><img SRC ='images/icon_redraw.gif' WIDTH ='19' HEIGHT =19 NAME ='redraw' BORDER ='0' onClick='update();' style='cursor:hand'></p>";
	}
	function getGeometryColumns($table,$field)
        {
                preg_match ("/^[\w'-]*\./",$table,$matches);
                $_schema = $matches[0];

                preg_match ("/[\w'-]*$/",$table,$matches);
                $_table = $matches[0];

                if (!$_schema) {
                        $_schema = $this->postgisschema;
                }
                else {
                        $_schema = str_replace(".","",$_schema);
                }
                $query = "select * from settings.geometry_columns_view where f_table_name='$_table' AND f_table_schema='$_schema'";

                $result = $this -> execQuery($query);
                $row = $this -> fetchRow($result);
                if (!$row)
                return $languageText[selectText];
                elseif ($row) $this -> theGeometry = $row[type];
                if ($field == 'f_geometry_column')
                return $row[f_geometry_column];
                if ($field == 'srid') {
                        return $row['srid'];
                }
                if ($field == 'type') {
                        return $row['type'];
                }
                if ($field == 'tweet') {
                        return $row['tweet'];
                }
                if ($field == 'editable') {
                        return $row['editable'];
                }
                if ($field == 'authentication') {
                        return $row['authentication'];
                }
                if ($field == 'fieldconf') {
                        return $row['fieldconf'];
                }
				if ($field == 'f_table_title') {
                        return $row['f_table_title'];
                }
        		if ($field == 'def') {
                        return $row['def'];
                }
        		if ($field == 'not_querable') {
                        return $row['not_querable'];
                }
				
				
        }
	function insertfeature($geoCoordStr)
	{

		global $keyValueArray;
		$geometryColumn =$this -> getGeometryColumns($this -> pg_digi_layer,f_geometry_column);
		switch ($this -> theGeometry)
		{
			case POINT :
				$pointGeoCoordStr=explode(",",$geoCoordStr);
				$__wkt="POINT($pointGeoCoordStr[0])";
				break;
			case LINESTRING :
				$__wkt="LINESTRING($geoCoordStr)";
				break;
			case POLYGON :
				$geoCoordStrExplode = explode(",", $geoCoordStr);
				$geoCoordStr = $geoCoordStr.",".$geoCoordStrExplode[0];
				$__wkt="POLYGON(($geoCoordStr))";
				break;
			case MULTIPOINT :
				$__wkt="MULTIPOINT($geoCoordStr)";
				break;
			case MULTILINESTRING :
				$__wkt="MULTILINESTRING(($geoCoordStr))";
				break;
			case MULTIPOLYGON :
				$geoCoordStrExplode = explode(",", $geoCoordStr);
				$geoCoordStr = $geoCoordStr.",".$geoCoordStrExplode[0];
				$__wkt="MULTIPOLYGON((($geoCoordStr)))";
				break;
		}
		$__geofactory=new geometryfactory;
		$__geoObj=$__geofactory->createGeometry($__wkt);
		if ($this -> pg_digi_snapTolerance != 0)
		{
			$__values = $this->getCoordsForSnap($__geoObj);
			$__geoObj->snapAllShapes($__values[0],$__values[1],$__values[2]);
		}
		if (!$this -> control -> proj)
		{
			$query =
			"INSERT INTO "
			.$this -> pg_digi_layer
			." (".$keyValueArray['fields']." $geometryColumn) VALUES (".$keyValueArray['values']." ST_GeometryFromText('".$__geoObj->getWKT()."',"
			."-1"
			.")"
			.")";
		} else
		{
			$query =
			"INSERT INTO "
			.$this -> pg_digi_layer
			." (".$keyValueArray['fields']." $geometryColumn) VALUES (".$keyValueArray['values']." ST_Transform(ST_GeometryFromText('".$__geoObj->getWKT()."',"
			.$this -> control -> proj
			."),"
			.$this -> getGeometryColumns(
			$this -> pg_digi_layer,
			srid)
			."))";
		}
		//echo "<!-- hej".$query."-->";
		pg_exec($this -> connect(), $query);
		return ($query);
	}
	function search($table, $field, $value, $zoom)
	{

		$this -> open();
		if ($this -> control -> proj != "")
		{
			$query =
			"SELECT xmin(extent(ST_Transform(the_geom,"
			.$this -> control -> proj
			."))) as minx, ymin(extent(ST_Transform(the_geom,"
			.$this -> control -> proj
			."))) as miny, xmax(extent(ST_Transform(the_geom,"
			.$this -> control -> proj
			."))) as maxx, ymax(extent(ST_Transform(the_geom,"
			.$this -> control -> proj
			."))) as maxy FROM "
			.$table
			." WHERE "
			.$field
			."='"
			.$value
			."'";

			// Get the WKT
			$query2 =
			"SELECT ST_AsText(ST_Transform(the_geom,"
			.$this -> control -> proj
			.")) as the_geom FROM "
			.$table
			." WHERE "
			.$field
			."='"
			.$value
			."'";
		}
		if ($this -> control -> proj == "")
		{
			$query =
			"SELECT xmin(extent(the_geom)) as minx, ymin(extent(the_geom)) as miny, xmax(extent(the_geom)) as maxx, ymax(extent(the_geom)) as maxy FROM "
			.$table
			." WHERE "
			.$field
			."='"
			.$value
			."'";
		}
		$result = pg_exec($this -> connect(), $query);
		$row = pg_fetch_array($result);
		if ($this -> control -> units == "xy") $border = 500 * $zoom;
		elseif ($this -> control -> units == "degrees") $border = 0.01 * $zoom;
		if (sizeof($row) > 0)
		{
			//echo $query; 
			$this -> control -> setMinx($row[minx] - $border);
			$this -> control -> setMiny($row[miny] - $border);
			$this -> control -> setMaxx($row[maxx] + $border);
			$this -> control -> setMaxy($row[maxy] + $border);
			$this -> control -> setproportion();
		}
		return(pg_fetch_array($this -> execQuery($query2)));
	}
	function delete($pg_digi_layer,$pg_digi_field,$pg_digi_value)
	{
		$query =
		"delete from "
		.$pg_digi_layer
		." where "
		.$pg_digi_field
		."='"
		.$pg_digi_value
		."'";
		pg_exec($this -> connect(), $query);
	}
	function digiDialog()
	{
		global $postGisQueryName;
		include ("includes/digi_dialog.php");
	}
	function postgisqueryDialog()
	{
		include ("includes/postgisquery_dialog.php");
	}
	/**
	 * @return array
	 * @param geometry object
	 * @desc Enter description here...
	 */
	function getCoordsForSnap($geoObj)
	{
		$geowidth = $this -> control -> maxx - $this -> control -> minx;
		$pixlength = $geowidth / $this -> control -> width;
		$__geoSnapTolerance = $this -> pg_digi_snapTolerance * $pixlength;
		$geometryColumn =
		$this -> getGeometryColumns(
		$this -> pg_snap_layer,
		f_geometry_column);
		$srid=$this -> getGeometryColumns($this -> pg_snap_layer,srid);
		if (!$this -> control -> proj)
		{
			$query =
			"SELECT ST_AsText($geometryColumn) as geom from "
			.$this -> pg_snap_layer
			." WHERE distance($geometryColumn,"
			." ST_GeometryFromText('".$geoObj->getWKT()."',-1))<$__geoSnapTolerance";

		} else
		{
			$query =
			"SELECT ST_AsText(ST_Transform($geometryColumn,"
			.$this -> control -> proj
			.")) as geom, gid from "
			.$this -> pg_snap_layer
			." WHERE "
			.$geometryColumn
			." && ST_Transform(Expand(ST_GeometryFromText('".$geoObj->getWKT().")',".$this -> control -> proj."),".				$__geoSnapTolerance."),$srid) and "
			." distance(ST_Transform($geometryColumn,"
			.$this -> control -> proj
			."), ST_GeometryFromText('".$geoObj->getWKT()."',"
			.$this -> control -> proj
			."))<$__geoSnapTolerance";

		}
		$result = pg_exec($this -> connect(), $query);
		$num_results = pg_numrows($result);
		for ($i = 0; $i < $num_results; $i ++)
		{
			$__row=pg_fetch_array($result);
			$__wkt[$i]=$__row['geom'];
		}
		if (sizeof($__wkt)>0)
		{
			$__geofactory=new geometryfactory;
			$__geoObjCol=$__geofactory->createGeometryCollection($__wkt);
		}
		if ($__geoObjCol)
		{
			$_array= array($__geoSnapTolerance,$__geoObjCol->getVertices(),$__geoObjCol->getShapes());
		}
		else
		{
			$_array=false;
		}
		return $_array;
	}
	function getPrimeryKey($table)
	{
		$query = "SELECT pg_attribute.attname, format_type(pg_attribute.atttypid, pg_attribute.atttypmod) FROM pg_index, pg_class, pg_attribute WHERE pg_class.oid = '{$table}'::regclass AND indrelid = pg_class.oid AND pg_attribute.attrelid = pg_class.oid AND pg_attribute.attnum = any(pg_index.indkey) AND indisprimary";
		$result = $this->execQuery($query);
		
		if ($this->PDOerror) {
			return NULL;
		}
		if (!is_array($row=$this->fetchRow($result))) { // If $table is view we bet on there is a gid field
			return array("attname"=>"gid");
		}
		else {
			return($row);
		}
	}
	function postgisquery($NewPointArray,$layer,$fields,$pg_query_type,$function,$buffer,$where=0)
	{
		global $HTTP_FORM_VARS;
		global $whereClause;
		$RubberHeight=$HTTP_FORM_VARS["RubberHeight"];
		$RubberWidth=$HTTP_FORM_VARS["RubberWidth"];
		$map_x=$HTTP_FORM_VARS["map_x"];
		$map_y=$HTTP_FORM_VARS["map_y"];
		$__bufferStr1 = "";
		$__bufferStr2 = "";
		global $postGisQuerySubstitute;
		// check if a sunstitute layer is set in conf
		$subLayer=$this->substituteQueryLayer($layer);
		// check if specific fields are set in conf
		$fields=$this->substituteQueryFields($layer,$fields);
		$the_geom = $this->getGeometryColumns($layer, "f_geometry_column");
		$primeryKey = $this->getPrimeryKey($layer);
		if ($pg_query_type == "rectangle"
		&& ($RubberWidth != NaN
		&& $RubberWidth != 0
		&& $RubberHeight != NaN
		&& $RubberHeight != 0))
		{
			$x1 = $map_x;
			$x2 = $map_x - $RubberWidth;
			$y1 = $map_y;
			$y2 = $map_y - $RubberHeight;
			if ($x1 < $x2)
			{
				$minx = $x1;
				$maxx = $x2;
			} else
			{
				$minx = $x2;
				$maxx = $x1;
			}
			if ($y1 < $y2)
			{
				$maxy = $y1;
				$miny = $y2;
			} else
			{
				$maxy = $y2;
				$miny = $y1;
			}
			$geominx = $this -> control -> pixtogeoX($minx);
			$geominy = $this -> control -> pixtogeoY($miny);
			$geomaxx = $this -> control -> pixtogeoX($maxx);
			$geomaxy = $this -> control -> pixtogeoY($maxy);
			$NewPointArray = $geominx." ".$geominy;
			$NewPointArray = $NewPointArray.",".$geomaxx." ".$geominy;
			$NewPointArray = $NewPointArray.",".$geomaxx." ".$geomaxy;
			$NewPointArray = $NewPointArray.",".$geominx." ".$geomaxy;
			$NewPointArray = $NewPointArray.",".$geominx." ".$geominy;
		}
		if ($pg_query_type == "rectangle" && ($RubberWidth == 0 && $RubberHeight == 0))
		{
			$pg_query_type = "point";
			$NewPointArray=$this -> control -> pixtogeoX($map_x)." ".$this -> control -> pixtogeoY($map_y);
		}
		$geometryColumn =
		$this -> getGeometryColumns($layer, f_geometry_column);
		$r = 0;
		$g = 255;
		$b = 0;
		if ($pg_query_type == "rectangle")
		$pg_query_type = "polygon";
		switch ($pg_query_type)
		{
			case "point" :
				$__wkt="POINT($NewPointArray)";
				break;
			case "line" :
				$__wkt="LINESTRING($NewPointArray)";
				break;
			case "polygon" :
				$NewPointArrayExplode = explode(",", $NewPointArray);
				$NewPointArray = $NewPointArray.",".$NewPointArrayExplode[0];
				$__wkt="POLYGON(($NewPointArray))";
				break;
		}
		$__geofactory=new geometryfactory;
		if (!$this -> control -> proj) $__srid=-1;
		else $__srid="EPSG:".$this -> control -> proj;
		$__geoObj=$__geofactory->createGeometry($__wkt,$__srid);
		if ($buffer) {
			$__bufferStr1 = "buffer(";
			$__bufferStr2 = $buffer."),";
		}

		if ($where) {
			$whereStr = " AND ".$where;
		}
        else {
        	$whereStr = "";
        }

		if (!$this -> control -> proj)
		{
			$query =
			"select ST_AsText({$the_geom}) as geometry,{$primeryKey['attname']} as gid,"
			.$fields
			." from "
			.$subLayer
			." where "
			."ST_GeometryFromText('".$__geoObj->getWKT()."',-1) && {$the_geom}"
			." and "
			."ST_".ucfirst($function)
			."(ST_GeometryFromText('".$__geoObj->getWKT()."',"
			."-1"
			."),{$the_geom})"
			.$whereStr;


		} else
		{
			$query =
			"select ST_AsText(ST_Transform({$the_geom},"
			.$this -> control -> proj
			.")) as geometry,{$primeryKey['attname']} as gid,"
			.$fields
			." from "
			.$subLayer
			." where "
			."ST_Transform(".$__bufferStr1."ST_GeometryFromText('".$__geoObj->getWKT()."',"
			.$this -> control -> proj
			."),".$__bufferStr2
			.$this -> getGeometryColumns($layer, srid)
			.") && {$the_geom}"
			." and "
			."ST_".ucfirst($function)
			."(ST_Transform(".$__bufferStr1."ST_GeometryFromText('".$__geoObj->getWKT()."',"
			.$this -> control -> proj
			."),".$__bufferStr2
			.$this -> getGeometryColumns($layer, srid)
			."),{$the_geom})"
			.$whereStr;
			
			//echo $query;
			

		}
		global $depth;
		$this->wfsFilter.="(";
		$this->wfsFilter.=$__geofactory->writeTag("open",null,"Filter",Null,True,True);
		//$depth++;
		$this->wfsFilter.=$__geofactory->writeTag("open",null,"$function",Null,True,True);
		//$depth++;
		$this->wfsFilter.=$__geofactory->writeTag("open",null,"PropertyName",Null,True,False);
		$this->wfsFilter.=$subLayer;
		$this->wfsFilter.=$__geofactory->writeTag("close",null,"PropertyName",Null,False,True);
		$this->wfsFilter.=$__geoObj->getGML();
		//$depth--;
		$this->wfsFilter.=$__geofactory->writeTag("close",null,$function,Null,True,True);
		//$depth--;
		$this->wfsFilter.=$__geofactory->writeTag("close",null,"Filter",Null,True,False);
		$this->wfsFilter.=")\n";
		//$this->wfsFilter=urlencode($this->wfsFilter);
		$this->sfsql=$query;
		// Render the query extent
		$this -> control -> renderGeometry(
		$this -> control ->convertpointarray_geo(
		$__geoObj->getVertices(),
		$units),
		$__geoObj->getGeomType(),
		2,
		$r,
		$g,
		$b);
		$query.=$whereClause;
		$result = $this->execQuery($query);
		//echo "<!-- hello ".$query."-->";
		return ($result);
	}
	function getWFSfilter()
	{
		return($this->wfsFilter);
	}
	function getSfsql()
	{
		return($this->sfsql);
	}
	function queryDump(
	$pg_select_layer,
	$NewPointArray,
	$pg_query_layer,
	$field,
	$type,
	$value,
	$buffer=0,
	$where=0)
	{
		global $HTTP_FORM_VARS;
		global $postGisQueryColor;
		$operation=$HTTP_FORM_VARS["operation"];
		global $postGisQueryUrlEncode;
		$geoTypeForJS = $this -> getGeometryColumns($pg_query_layer, type);
		switch ($type)
		{
			case "EDIT" :
				$result =
				$this -> postgisquery(
				$NewPointArray,
				$pg_query_layer,
				$field,
			'rectangle',
			'intersects');
				break;
			case "POSTGIS" :
				$result =
				$this -> postgisquery(
				$NewPointArray,
				$pg_query_layer,
				$field,
				$this -> pg_query_type,
				$this -> pg_query_function,
				$buffer,
				$where
				);
				break;
			case "FEATURE" :
				$resultTmp =
				$this -> postgisquery(
				$NewPointArray,
				$pg_select_layer,
				$field,
			"point",
			"intersects");
				$row = pg_fetch_array($resultTmp);
				if ($row[gid])
				$result =
				$this -> featureQuery(
				$pg_select_layer,
				$row[gid],
				$pg_query_layer,
				$field);
				break;
			case "SPATIELANALYSIS" :
				$result =
				$this -> featureQuery(
				$pg_select_layer,
				$value,
				$pg_query_layer,
				$field,
				$buffer);
				//echo "<script>alert('$dump');</script>";
				break;
			case "GID" :
				$result = $this -> gidQuery($pg_query_layer, $field, $value);
				break;
			case "FETCHALL" :
				$result = $this -> fetchAll($pg_query_layer);
				break;
		}
		if ($result)
		{
			$num_results = pg_numrows($result);
			$dump = "<msGMLOutput><".$pg_query_layer."_layer>";
			for ($i = 0; $i < $num_results; $i ++)
			{
				$dump = $dump."<".$pg_query_layer."_feature>";
				$row = pg_fetch_array($result,$i,PGSQL_ASSOC);
				foreach ($row as $key=>$value)
				{
					if ($key == "gid")
					$gidKey = $key;
					if ($key != "the_geom"
					&& $key != "geometry")
					{
						$dump = $dump."<".$key.">";
						if ($value == "")
						$dump = $dump."*";
						else
						$dump = $dump.urlencode($value);
						//$dump = $dump.$value;
						$dump = $dump."</".$key.">";
					}
					elseif ($key == "geometry")
					{
						$__wkt[$i] = $value;
						if ($postGisQueryColor[strtoupper($pg_query_layer)] != "")
						$color = $postGisQueryColor[strtoupper($pg_query_layer)];
						else
						$color = "#ffff00";
						$r = hexdec(substr($color, 1, 2));
						$b = hexdec(substr($color, 3, 2));
						$g = hexdec(substr($color, 5, 2));
						$gml_geom = $gml_geom.$output;
						$dump =
						$dump
						."<gml:boundedBy><gml:box srsName=''><gml:coordinates></gml:coordinates></gml:box></gml:boundedBy>";
						$dump =
						$dump
						."<gml:Polygon srsName=' EPSG : 32632 '><gml:outerBoundayIs><gml:LinearRing><gml:coordinates>";
						$dump = $dump.$gml_geom;
						$dump =
						$dump
						."</gml:coordinates></gml:LinearRing></gml:outerBoundayIs></gml:Polygon>";
					}

				}
				$dump = $dump."</".$pg_query_layer."_feature>";


				if ($__wkt)
				{
					$__geoObjCol = geometryfactory::createGeometryCollection($__wkt);
					foreach($__geoObjCol->getGeometryArray() as $__geoObj)
					{
						$this -> renderGeometryArray[] = array($__geoObj -> getWKT(), $brush, $r, $b, $g);
						$this -> control -> renderFromWKT(array($__geoObj -> getWKT()),TRUE,$r,$b,$g);
						foreach($__geoObj->getShapeArray() as $__key=>$__shape)
						{
							$__pixCoord = $this -> control -> convertpointarray_geo($__shape,"");
							/*
							 * Writes out JS vars in html doc for editing function
							 */
							if ($operation == "edit")
							{
								$this->editForAjax["editArray"][$__key]=$__pixCoord;
								$this->editForAjax["gid"]=$row[gid];
								$this->editForAjax["key"][$__key]=$__key;
								$this->editForAjax["geoType"]=$geoTypeForJS;

								$this->editArray[$__key]="editArray[$__key]='$__pixCoord';";
								$this->editArray[$__key].="editKey='$row[gid]';";
								$this->editArray[$__key].="editShapeId[$__key]='$__key';";
								$this->editArray[$__key].="geoType='$geoTypeForJS';";

								//echo $this->editArray;
							}
						}
					}
				}
			}
			$dump = $dump."</".$pg_query_layer."_layer></msGMLOutput>";
			//echo "<!--$dump-->";
			return ($this -> control -> parse($dump));
		}
		else {
			logfile::write($pg_query_layer);
			logfile::write("\n");
		}
	}
	function fetchAll($table)
	{
		$query ="SELECT * FROM ".$table;
		$result = $this -> execQuery($query);
		return ($result);
	}
	function featureQuery($pg_select_layer, $GID, $pg_query_layer, $fields, $buffer=0)
	{

		global $postGisQuerySubstitute;
		// check if a sunstitute layer is set in conf
		$subLayer=$pg_query_layer;
		// check if specific fields are set in conf
		$fields=$this->substituteQueryFields($pg_query_layer,$fields);
		if (!$this -> control -> proj)
		{
			$query =
			"select ST_AsText(".$subLayer.".the_geom) as geometry,gid,"
			.$fields
			." from "
			.$subLayer.",".$pg_select_layer
			." where "
			.$pg_select_layer
			.".gid="
			.$GID
			." and "
			.$subLayer
			.".the_geom && "
			.$pg_select_layer
			.".the_geom and "
			.$this -> pg_query_function
			."("
			.$subLayer
			.".the_geom,"
			.$pg_select_layer
			.".the_geom)";
		}
		else
		{
			$query =
			"select ST_AsText(ST_Transform(".$pg_query_layer.".the_geom,"
			.$this -> control -> proj
			.")) as geometry,".$subLayer.".gid as fid,"
			.$fields
			." from ";
			
			if ($subLayer!=$pg_select_layer)
			$query.= $subLayer.",".$pg_select_layer;
			else
			$query.= $pg_select_layer;
			
			$query.=" where "
			.$pg_select_layer
			.".gid="
			.$GID
			." and "
			.$subLayer
			.".the_geom && "
			.$pg_select_layer
			.".the_geom and "
			.$this -> pg_query_function
			."("
			.$subLayer
			.".the_geom,";
			if ($buffer) $query.="buffer(";
			$query.=$pg_select_layer;
			$query.=".the_geom";
			if ($buffer) $query.=",".$buffer.")";
			$query.=")";
		}
		//echo $query."<br><br>";
		$result = pg_exec($this -> connect(), $query);
		return ($result);
	}
	function gidQuery($pg_query_layer, $fields, $value)
	{
		$subLayer=$this->substituteQueryLayer($pg_query_layer);
		$fields=$this->substituteQueryFields($pg_query_layer,$fields);
		if (!$this -> control -> proj)
		{
			$query ="select ST_AsText(the_geom) as geometry,gid,$fields from $subLayer where gid=$value";
		}
		else
		{
			$query ="select ST_AsText(ST_Transform(the_geom,".$this -> control -> proj.")) as geometry,gid,$fields from $subLayer where gid=$value";
		}
		$result = pg_exec($this -> connect(), $query);
		return ($result);
	}
	function updateFeature($wkt, $gid, $shapeId)
	{
		global $HTTP_FORM_VARS;
		$pg_digi_snapTolerance=$HTTP_FORM_VARS["pg_digi_snapTolerance"];
		$gkey = "gid";
		$the_geom = $this -> getGeometryColumns($this -> pg_digi_layer,f_geometry_column);
		// If polygon when close ring
		if ($this -> theGeometry == "POLYGON"
		|| $this -> theGeometry == "MULTIPOLYGON")
		{
			$wktExplode = explode(",", $wkt);
			$wkt = $wkt.",".$wktExplode[0];
		}
		if (!$this -> control -> proj)
		{
			$query =
			"SELECT ST_AsText($the_geom) as geometry from "
			.$this -> pg_digi_layer
			." WHERE $gkey="
			.$gid;
		} else
		{
			$query =
			"SELECT ST_AsText(ST_Transform($the_geom,"
			.$this -> control -> proj
			.")) as geometry from "
			.$this -> pg_digi_layer
			." WHERE $gkey ="
			.$gid;
		}
		$result = pg_exec($this -> connect(), $query);
		$row = pg_fetch_array($result);
		$__geofactory=new geometryfactory;
		$__geoObj=$__geofactory->createGeometry($row[geometry]);
		$__geoObj->updateShape($wkt,$shapeId);
		if ($this -> pg_digi_snapTolerance != 0)
		{
			$__values = $this->getCoordsForSnap($__geoObj);
			$__geoObj->snapShape($shapeId,$__values[0],$__values[1],$__values[2]);
		}
		if (!$this -> control -> proj)
		{
			$query =
			"update "
			.$this -> pg_digi_layer
			." set "
			.$the_geom
			."="
			."ST_GeometryFromText('".$__geoObj->getWKT()."',-1)"
			." WHERE $gkey ="
			.$gid;
		} else
		{
			$query =
			"update "
			.$this -> pg_digi_layer
			." set "
			.$the_geom
			."="
			."ST_Transform(ST_GeometryFromText('".$__geoObj->getWKT()."',"
			.$this -> control -> proj
			."),"
			.$this -> getGeometryColumns($this -> pg_digi_layer, srid)
			.") WHERE $gkey ="
			.$gid;
		}
		//echo "<!-- hej".$query."-->";
		$result = pg_exec($this -> connect(), $query);
	}
	function substituteQueryLayer($layer)
	{
		global $postGisQuerySubstitute;
		if ($postGisQuerySubstitute[strtoupper($layer)]) $subLayer=$postGisQuerySubstitute[strtoupper($layer)];
		else $subLayer=$layer;
		return($subLayer);
	}
	function substituteQueryFields($layer,$fields)
	{
		global $postGisQueryFieldRow;
		if ($postGisQueryFieldRow[strtoupper($layer)]) $subFields=$postGisQueryFieldRow[strtoupper($layer)];
		else $subFields=$fields;
		return($subFields);
	}
	function referenceTableLookup($refTable,$key,$field,$value)
	{
		$query="SELECT $field FROM $refTable WHERE $key=$value";
		$result=$this->execQuery($query);
		$row = pg_fetch_array($result);
		return ($row[$field]);
	}
	function renderPostGisFeatures($featureArray,$doRender=TRUE)
	{
		global $postGisQueryColor;
		$__geofactory=new geometryfactory;
		$__geoArray=array();

		foreach($featureArray as $value)
		{
			$the_geom = $this -> getGeometryColumns($value[1],f_geometry_column);
			$query="SELECT ST_AsText(ST_Transform($the_geom,".$this -> control -> proj.")) as geometry from ".$value[1]." WHERE gid=".$value[0];
			$result = $this -> execQuery($query);
			$row = postgis::fetchRow($result);
			if ($row['geometry']) {
				$__geoObj = $__geofactory -> createGeometry($row['geometry']);
				$__geoArray[] = $row['geometry'];
			}
			if($doRender)
			{
				if ($postGisQueryColor[strtoupper($value[1])] != "")
				{
					$color = $postGisQueryColor[strtoupper($value[1])];
				}
				else
				{
					$color = "#ffff00";
				}
				$r = hexdec(substr($color, 1, 2));
				$b = hexdec(substr($color, 3, 2));
				$g = hexdec(substr($color, 5, 2));
				$this -> control -> renderFromWKT(array($__geoObj -> getWKT()),TRUE, $r, $b, $g);
			}
		}
		$__geoObjCol=$__geofactory->createGeometryCollection($__geoArray);
		return ($__geoObjCol);
	}
	function getUtmZone($table, $field, $value)
	{
		$query="SELECT X(Centroid(the_geom)) as x FROM $table WHERE $field=$value";
		$result = $this -> execQuery($query);
		$row = postgis::fetchRow($result);
		$zone=floor((180+$row['x'])/6);
		if ($zone<10) $zone="0".$zone;
		return($zone);
	}
}
class mapscript
{
	var $map;
	var $image;
	var $image_url;
	var $layerXml;
	var $mapfileUrl;
	var $layerStatus;
	/**
	 * @return mapscript
	 * @desc contructor
	 */
	function mapscript($layerXml,$mapfileUrl, & $controlObject)
	{
		$this -> control = & $controlObject;
		$this -> layerXml = $layerXml;
		$this -> mapfileUrl = $mapfileUrl;
		$this -> setMapfile();
		$this -> setSize();
	//	$this -> setExtents();
		$this -> setProj();
		$this -> control -> setproportion();
	}
	function setMapfile()
	{
		$this -> map = ms_newMapObj($this -> mapfileUrl);
	}
	function setExtents()
	{
		if (!$this -> control -> getMaxX() == "")
		{
			$this -> map -> setextent(
			$this -> control -> getMinX(),
			$this -> control -> getMinY(),
			$this -> control -> getMaxX(),
			$this -> control -> getMaxY());
			$this -> control -> setproportion();
		} else
		{
			$this -> control -> setMinX($this -> map -> extent -> minx);
			$this -> control -> setMinY($this -> map -> extent -> miny);
			$this -> control -> setMaxX($this -> map -> extent -> maxx);
			$this -> control -> setMaxY($this -> map -> extent -> maxy);
			$this -> control -> setproportion();
			// To get the proportion correction into the map object we set it with new coords
			$this -> map -> setextent(
			$this -> control -> getMinX(),
			$this -> control -> getMinY(),
			$this -> control -> getMaxX(),
			$this -> control -> getMaxY());
		}
	}
	function setSize()
	{
		$this -> map -> set("width", $this -> control -> width);
		$this -> map -> set("height", $this -> control -> height);
	}
	function setLayers()
	{
		global $serviceObject;
		for ($count = 2 + $serviceObject[99] -> interval;
		$count
		<= $serviceObject[99] -> layerCount
		+ $serviceObject[99] -> interval;
		$count ++)
		{
			$temp = "layer_$count";
			global $$temp;
		}
		for ($count = 2 + $serviceObject[99] -> interval;
		$count
		<= $serviceObject[99] -> layerCount
		+ $serviceObject[99] -> interval;
		$count ++)
		{
			$temp = "layer_$count";
			if ($$temp == "on")
			{
				$__layerArray=explode(",",$serviceObject[99]->layername[$count-$serviceObject[99]->interval]);
				for ($__i = 0; $__i < count($__layerArray); $__i++)
				{
					if ($__layerArray[$__i])
					{
						$layer=$this -> map -> getlayerbyname($__layerArray[$__i]);
						$layer -> set("status", MS_ON);
					}

				}
			}
		}
	}
	function setStatus($layer,$status)
	{
		$__layer=$this -> map -> getlayerbyname($layer);
		switch ($status)
		{
			case "on":
				$__layer -> set("status",MS_ON);
				break;
			case "off":
				$__layer -> set("status",MS_OFF);
				break;
			case "default":
				$__layer -> set("status",MS_DEFAULT);
				break;
			case "delete":
				$__layer -> set("status",MS_DELETE);
				break;
		}
	}
	function setProj()
	{
		$proj_params = "init=epsg:".$this -> control -> proj;
	//	$this -> map -> setProjection($proj_params);
	}
	function drawImage()
	{
		$this -> setExtents();
		$image = $this -> map -> draw();
		$image_url = $image -> saveWebImage(MS_PNG, 1, 1, 0);
		return ($image_url);
	}
	function drawLegend()
	{
		$image = $this -> map -> drawLegend();
		$image_url = $image -> saveWebImage(MS_PNG, 0, 0, -1);
		return ($image_url);
	}
	function drawReference()
	{
		$image = $this -> map -> drawReference();
		$image_url = $image -> saveWebImage(MS_PNG, 0, 0, -1);
		return ($image_url);
	}
	function drawScalebar()
	{
		$image = $this -> map -> drawScalebar();
		$image_url = $image -> saveWebImage(MS_PNG, 1, 1, 0);
		return ($image_url);
	}
	function setFilter($layer,$filter)
	{
		$__layer=$this -> map -> getlayerbyname($layer);
		$__layer -> setFilter($filter);
		//echo $__layer ->getFilter();
	}
	function createClass($layer,$name,$expr,$r,$g,$b,$symbol,$size)
	{
		$__layer=$this -> map -> getlayerbyname($layer);
		$__class=ms_newClassObj($__layer);
		$__class->set("name", $name);
		$__class->setExpression($expr);
		$__style=ms_newStyleObj($__class);
		$__style->color->setRGB($r,$g,$b);
		$__style->set("symbol",$symbol);
		$__style->set("size", $size);
	}
}

class wfsclient
{
	var $interval;
	var $serviceNum;
	var $layerUrl;
	var $layertitle;
	var $layerTable;
	var $layername;
	var $layerAttrs;
	var $layerCount;
	var $layerlevel;
	var $levelcount;
	var $queryTitle;
	var $srs;
	var $parser;
	var $layerAbstract;
	var $control;
	var $jsTableCheck;
	var $wfsFilter;
	var $BBox;
	var $subPressServerForm;
	var $parserGml;
	var $wfsUrl;
	var $gmlArray;
	function wfsclient($i, $server, & $controlObject, $subPressServerForm=FALSE) //constructor
	{
		$this -> subPressServerForm=$subPressServerForm;
		$this -> control= & $controlObject;
		if ($server != "")
		{
			$u = $i +1;
			$this -> serviceNum = $i;
			$this -> server = $server;
			$this -> interval = $u * 100;
			$this -> xml();
			$this -> parse();
			$this -> serverForm();
		}
	}
	function setControlObject(& $controlObject)
	{
		$this -> control = & $controlObject;
	}
	function serverForm()
	{
		if (!$this->subPressServerForm) echo "<input type='hidden' name='wfs_$this->serviceNum' value='"
		.$this -> server
		."'>";
	}

	function layercontrol()
	{
		global $HTTP_FORM_VARS;
		$queryLayer=$HTTP_FORM_VARS["queryLayer"];

		//Globalizing variable variables
		for ($count = 1 + $this -> interval; $count <= $this -> layerCount + $this -> interval; $count ++)
		{
			$temp = "wfsLayer_$count";
			global $$temp;
		}
		$layerTree.="<table>";
		$layerTree.="<tr><td align='middle' class='layout-table'>";
		if ($this -> layerAbstract[0]) {
			$layerTree.="<IMG border='0' SRC='images/thmIdOn13x13.gif' WIDTH='13' HEIGHT='13'";
			$layerTree.=" title='".urldecode($this -> layerAbstract[0])."'";
			$layerTree.=">";
		}
		$layerTree.="</td><td class='layout-table'><b>".$this -> layertitle[0]."</b></td><td></td></tr>";

		for ($count = 1 + $this -> interval; $count <= $this -> layerCount + $this -> interval; $count ++)
		{
			$layerTree.= "<tr><td align='middle' class='layout-table'>";
			if ($this -> layerAbstract[$count - $this -> interval]) {
				$layerTree.="<IMG border='0' SRC='images/thmIdOn13x13.gif' WIDTH='13' HEIGHT='13'";
				$layerTree.=" title='".urldecode($this -> layerAbstract[$count - $this -> interval])."'";
				$layerTree.=">";
			}
			$layerTree.= "</td>";
			$layerTree.= "<td class='layout-table'>";
			$layerTree.=$this -> layertitle[$count	- $this -> interval]."</td>";
			$layerTree.= "<td class='layout-table'><input type='checkbox' name='wfsLayer_$count' value='on'";
			$temp = "wfsLayer_$count";
			if ($$temp == "on")
			{
				$layerTree.= " checked";
				$this -> layerUrl = $this -> layerUrl.$this -> layername[$count - $this -> interval].",";
			}
			$layerTree.= "></td>";
		}
		$layerTree.= "</table>";
		// strip the last comma
		$this->layerUrl=substr($this->layerUrl, 0, strlen($this->layerUrl) - 1);
		return ($layerTree);
	}
	function getFeatures()
	{
		$this -> wfsUrl = $this -> server."&request=GETFEATURE&typename=".$this->layerUrl."&filter=".$this->wfsFilter;
		$handle = @fopen($this -> wfsUrl, "r");
		if ($handle)
		{
			while (!feof($handle)) {
				$buffer = fgets($handle, 4096);
				$dump.=$buffer;
			}
			fclose($handle);
		}
		return ($dump);
	}
	function xml()
	{
		$this -> parser = xml_parser_create();
		xml_set_object($this -> parser, $this);
		xml_set_element_handler($this -> parser, "startElement", "endElement");
		xml_set_character_data_handler($this -> parser, "characterData");
	}
	function parse()
	{
		global $data;
		global $num;
		$currentTag = "";
		$serviceNum = $i;
		$file = $this -> server."VERSION=1.0.0&REQUEST=GetCapabilities";
		// open XML file
		$fp = fopen($file, "r");
		while ($data = fread($fp, 100000))
		xml_parse($this -> parser, $data);
		// clean up
		xml_parser_free($this -> parser);
		$this -> layerCount = $num;
		//Number of layer in each service is saved in a array;
		$num = 0; // Ready for a new service
		$layerleveltrack = 0;
	}
	function startElement($parser, $name, $attrs)
	{
		global $layerleveltrack;
		global $styletag;
		global $currentTag;	//used by function characterData when parsing xml data
		global $num; //Each layer is provided with a number
		global $SRS;
		$currentTag = $name;
		$serviceNum = 0;
		// output opening HTML tags
		switch ($currentTag)
		{
			case "LAYER" :

			case "FEATURETYPE" :
				$this -> levelcount = 0;
				if (!$layerleveltrack)
				$layerleveltrack = 0;
				$styletag = 0;
				if (!$num)
				$num = 0;
				$num = $num +1;
				//increase with one for every layertag parsed. keeps track of number of layers
				$layerleveltrack = $layerleveltrack +1;
				//increase with one for every start-layertag parsed
				$this -> layerAttrs[$num]["queryable"] = $attrs[QUERYABLE];
				$this -> layerlevel[$num] = $layerleveltrack;
				//each layer is provided with a level number
				break;
			case "STYLE" :
				$styletag = 1;
				break;

		}
	}
	function endElement($parser, $name)
	{
		global $layerleveltrack;
		global $currentTag;
		global $levelcount;
		global $serviceNum;
		global $num;
		// output closing HTML tags
		switch ($name)
		{
			case "FEATURETYPE" :

			case "LAYER" :
				$layerleveltrack = $layerleveltrack -1;
				//decrease with one for every stop-layertag parsed
				$this -> levelcount = $this -> levelcount + 1;
				break;
			case "STYLE" :
				$styletag = 0;
				break;
		}
		// clear current tag variable
		$currentTag = "";
	}
	// process data between tags
	function characterData($parser, $data)
	{
		global $layerleveltrack;
		global $stop;
		global $currentTag;
		global $num;
		global $serviceNum;
		global $styletag;
		$serviceNum = 0;
		switch ($currentTag)
		{
			case "TITLE" :
				{
					if(!$styletag) $this -> layertitle[$num] = $data;
				}
				break;
			case "NAME" :
				{
					if(!$styletag) $this -> layername[$num] = $data;
				}
				break;
			case "SRS" :
				{
					if (!$this -> srs) // only the first tag
					$this -> srs = $data;
				}
				break;
			case "ABSTRACT" :
				{
					if ($num > 0) //Titles of layers has begun
					{
						$this -> layerAbstract[$num] = addslashes($data);// String is encode so strang chars not will mess the xml up
					} else
					if ($num == 0)
					$this -> layerAbstract[0] = addslashes($data);
				}
				break;
		}
	}
	function setFilter($NewPointArray)
	{
		global $pg_query_type;
		global $pg_query_function;

		global $HTTP_FORM_VARS;
		$tmp_pg_query_type=$pg_query_type;
		$RubberHeight=$HTTP_FORM_VARS["RubberHeight"];
		$RubberWidth=$HTTP_FORM_VARS["RubberWidth"];
		$map_x=$HTTP_FORM_VARS["map_x"];
		$map_y=$HTTP_FORM_VARS["map_y"];

		if ($tmp_pg_query_type == "rectangle"
		&& ($RubberWidth != NaN
		&& $RubberWidth != 0
		&& $RubberHeight != NaN
		&& $RubberHeight != 0))
		{
			$x1 = $map_x;
			$x2 = $map_x - $RubberWidth;
			$y1 = $map_y;
			$y2 = $map_y - $RubberHeight;
			if ($x1 < $x2)
			{
				$minx = $x1;
				$maxx = $x2;
			} else
			{
				$minx = $x2;
				$maxx = $x1;
			}
			if ($y1 < $y2)
			{
				$maxy = $y1;
				$miny = $y2;
			} else
			{
				$maxy = $y2;
				$miny = $y1;
			}
			$geominx = $this -> control -> pixtogeoX($minx);
			$geominy = $this -> control -> pixtogeoY($miny);
			$geomaxx = $this -> control -> pixtogeoX($maxx);
			$geomaxy = $this -> control -> pixtogeoY($maxy);
			$NewPointArray = $geominx." ".$geominy;
			$NewPointArray = $NewPointArray.",".$geomaxx." ".$geominy;
			$NewPointArray = $NewPointArray.",".$geomaxx." ".$geomaxy;
			$NewPointArray = $NewPointArray.",".$geominx." ".$geomaxy;
			$NewPointArray = $NewPointArray.$geominx." ".$geominy;

			// Set the BBox for for spatial filter
			$this->BBox='<gml:Box srsName="http://www.opengis.net/gml/srs/epsg.xml%23'.$this->control->proj.'"><gml:coordinates>'.$geominx.','.$geominy.' '.$geomaxx.','.$geomaxy.'</gml:coordinates></gml:Box>';
		}
		if ($tmp_pg_query_type == "rectangle" && ($RubberWidth == 0 && $RubberHeight == 0))
		{
			$tmp_pg_query_type = "point";
			$NewPointArray=$this -> control -> pixtogeoX($map_x)." ".$this -> control -> pixtogeoY($map_y);
		}

		$r = 0;
		$g = 255;
		$b = 0;

		if ($tmp_pg_query_type == "rectangle")
		{
			$tmp_pg_query_type = "polygon";
		}
		switch ($tmp_pg_query_type)
		{
			case "point" :
				$__wkt="POINT($NewPointArray)";
				break;
			case "line" :
				$__wkt="LINESTRING($NewPointArray)";
				break;
			case "polygon" :
				$NewPointArrayExplode = explode(",", $NewPointArray);
				$NewPointArray = $NewPointArray.",".$NewPointArrayExplode[0];
				$__wkt="POLYGON(($NewPointArray))";
				break;
		}
		$__geofactory = new geometryfactory;
		$__geoObj = $__geofactory->createGeometry($__wkt,$__srid);
		if ($pg_query_type == "rectangle" && ($RubberWidth != NaN && $RubberWidth != 0 && $RubberHeight != NaN && $RubberHeight != 0))
		{
			$__array=explode(",",$this->layerUrl);
			for ($i=0; $i<sizeof($__array); $i++)
			{
				$__filterArray[$i]='
				<ogc:Filter xmlns:gml="http://www.opengis.net/gml" xmlns:ogc="http://www.opengis.net/ogc">
					<ogc:BBOX>
						<ogc:PropertyName>the_geom</ogc:PropertyName>
						'.$this->BBox.'
					</ogc:BBOX>
				</ogc:Filter>
				';
				$__filterArray[$i] = "(".urlencode($__filterArray[$i]).")";
			}
			$this->wfsFilter = implode("",$__filterArray);
		}
		else
		{
			$__array=explode(",",$this->layerUrl);
			for ($i=0; $i<sizeof($__array); $i++)
			{
				$__filterArray[$i] = $this -> createSpatialFilterFromWKT($__wkt,$pg_query_function,$this->layerUrl,$this->control->proj);
				$__filterArray[$i] = "(".urlencode($__filterArray[$i]).")";
			}
			$this->wfsFilter = implode("",$__filterArray);
		}

		// Render the query extent
		$this -> control -> renderGeometry($this -> control ->convertpointarray_geo($__geoObj->getVertices(),$units),$__geoObj->getGeomType(),2,$r,$g,$b);
		return($this -> wfsFilter);
	}
	function createSpatialFilterFromWKT($wkt,$function,$layerUrl,$srid=-1,$oneLine=true)
	{
		switch ($function)
		{
			case "intersects":
				$function = "ogc:Intersects";
				break;
			case "overlaps":
				$function = "ogc:Overlaps";
				break;
			case "within":
				$function = "ogc:Within";
				break;
			case "touches":
				$function = "ogc:Touches";
				break;
		}

		$__geofactory=new geometryfactory;
		if ($srid!=-1) $srid='http://www.opengis.net/gml/srs/epsg.xml%23'.$srid;
		$__geoObj=$__geofactory->createGeometry($wkt,$srid);

		$wfsFilter = '
				<ogc:Filter xmlns:gml="http://www.opengis.net/gml" xmlns:ogc="http://www.opengis.net/ogc">
					<'.$function.'>
						<ogc:PropertyName>geometri</ogc:PropertyName>
						'.$__geoObj->getGML().'
					</'.$function.'>
				</ogc:Filter>

		';
		if ($oneLine) $wfsFilter = gmlConverter::oneLineXML($wfsFilter);
		return($wfsFilter);
	}
}
class gmlParser
{
	var $gmlArray;
	var $gmlSource;
	var $gmlCon;
	var $strForSql;
	var $arr;
	var $geomType;

	function gmlParser($gmlSource,& $gmlCon)
	{
		/*
		include_once("libs/class_xml_check.php");
		$check = new XML_check();
		if($check->check_string($gmlSource)) {
			logfile::write("GML is well-formed\n");
			//print("Elements      : ".$check->get_xml_elements());
			//print("Attributes    : ".$check->get_xml_attributes());
			//print("Size          : ".$check->get_xml_size());
			//print("Text sections : ".$check->get_xml_text_sections());
			//print("Text size     : ".$check->get_xml_text_size());
		}
		else {
			logfile::write("GML is not well-formed. ");
			logfile::write($check->get_full_error()."\n");
			logfile::write("Script terminated\n");
			die();
		}
		 */
		$this -> gmlSource = $gmlSource;
		$this -> gmlCon = & $gmlCon;
		require_once("XML/Unserializer.php");

		$unserializer_options = array ('parseAttributes' => TRUE);

		$unserializer = &new XML_Unserializer($unserializer_options);

		// Serialize the data structure
		$status = $unserializer->unserialize($this -> gmlSource);

		$this -> gmlArray=$unserializer->getUnserializedData();
		logfile::write(date('l jS \of F Y h:i:s A')." GML serialized\n");
		// Check if XML is a ServiceException
		if ($this -> gmlArray['ServiceException']){
			logfile::write("The server returned an exception:\n");
			logfile::write($this -> gmlArray['ServiceException']."\n");
			logfile::write("Script terminated\n");
			die();
		}
	}
	function unserializeGml()
	{
		$wktArr = $this -> gmlCon -> gmlToWKT($this -> gmlSource);
		$allFields = array();
		if ($wktArr[0]){
			ksort($wktArr[0]);
			// Check the geom type of first feature
			$geoObj = geometryfactory::createGeometry($wktArr[0][0],"25832");
			$this -> geomType = $geoObj -> getGeomType();

			// If NOT multi feature, set type to multi
			if ($this -> geomType == "POINT") $this -> geomType = "MULTIPOINT";
			if ($this -> geomType == "LINESTRING") $this -> geomType = "MULTILINESTRING";
			if ($this -> geomType == "POLYGON") $this -> geomType = "MULTIPOLYGON";

			if (sizeof($this -> gmlArray['gml:featureMember'])>1)
			{
				foreach ($this -> gmlArray['gml:featureMember'] as $featureMember)
				{
					foreach ($featureMember as $feature)
					{
						foreach ($feature as $field => $value)
						{
							if (!is_array($value))
							{
								$fieldWithOutDomain = preg_replace("/[a-z]*:/","",$field);
								$fields[] = $fieldWithOutDomain;
								$values[] = "'".pg_escape_string($value)."'";

								//Build field array
								if (!in_array($fieldWithOutDomain,$allFields)) $allFields[] = $fieldWithOutDomain;
							}
						}
						$fieldsStr = implode(",",$fields);
						$valuesStr = implode(",",$values);
						$this -> arr['fields'][] = $fieldsStr;
						$this -> arr['values'][] = $valuesStr;
						$this -> arr['geom'][] = current($wktArr[0]);

						// Reset vars
						$fields = array();
						$values = array();
						$field = "";
						$value = "";
						$fieldsStr = "";
						$valuesStr = "";

					}
					next($wktArr[0]);
				}
			}
			else
			{
				foreach ($this -> gmlArray['gml:featureMember'] as $featureMember)
				{
					foreach ($featureMember as $field => $value)
					{
						if (!is_array($value))
						{
							$fieldWithOutDomain = preg_replace("/[a-z]*:/","",$field);
							$fields[] = $fieldWithOutDomain;
							$values[] = "'".pg_escape_string($value)."'";

							//Build field array
							if (!in_array($fieldWithOutDomain,$allFields)) $allFields[] = $fieldWithOutDomain;
						}
					}
					$fieldsStr = implode(",",$fields);
					$valuesStr = implode(",",$values);
					$this -> arr['fields'][] = $fieldsStr;
					$this -> arr['values'][] = $valuesStr;
					$this -> arr['geom'][] = current($wktArr[0]);
				}
			}
			$this -> strForSql = implode(" character varying,",$allFields);

			logfile::write(date('l jS \of F Y h:i:s A')." GML geometry converted to WKT geometry (".$this -> geomType.")\n");

		}
		else{
			$this -> arr = false;
		}
	}
	function loadInDB(& $postgisObject,$tableName)
	{

		if ($this -> arr) {
			//First we try to drop table
			$dropSql = "DROP TABLE ".$tableName." CASCADE";

			//When we try to delete row from geometry_columns
			$deleteFromGeometryColumns = "DELETE FROM geometry_columns WHERE f_table_name='".$tableName."'";

			//When we insert new row in geometry_columns
			$sqlInsert = "INSERT INTO geometry_columns VALUES ('', '".$postgisObject -> postgisdb."', '".$tableName."', 'the_geom', 2, 25832, '".$this -> geomType."', NULL, NULL, NULL)";

			//Last we create the new table
			$createSql = "
			CREATE TABLE ".$tableName." (
			gid serial NOT NULL,
			".$this -> strForSql."  character varying,
			the_geom geometry,
			CONSTRAINT \"$1\" CHECK ((srid(the_geom) = 25832)),
			CONSTRAINT \"$2\" CHECK (((geometrytype(the_geom) = '".$this -> geomType."'::text) OR (the_geom IS NULL)))
			);";

			// Check if table is already created
			$checkSql = "select * FROM ".$tableName;
			$check = $postgisObject -> execQuery($checkSql);

			// Start of transactions block
			$postgisObject -> execQuery(BEGIN);

			if ($check) // True and we drop the table
			{
				$result = $postgisObject -> execQuery($dropSql);
				$postgisObject -> free($result);
				//echo $tableName." dropped\n";
			}

			$result = $postgisObject -> execQuery($deleteFromGeometryColumns);
			$postgisObject -> free($result);

			$result = $postgisObject -> execQuery($sqlInsert);
			$postgisObject -> free($result);

			$result = $postgisObject -> execQuery($createSql);
			$postgisObject -> free($result);

			//echo "\n";
			$countRows = 0;

			for($i=0;$i<sizeof($this -> arr['fields']);$i++)
			{
				$geoObj = geometryfactory::createGeometry($this -> arr['geom'][$i],"25832");
				if ($geoObj)
				{
					if ($geoObj -> getGeomType() == "POLYGON" || $geoObj -> getGeomType() == "LINESTRING" || $geoObj -> getGeomType() == "POINT")
					{
						$this -> arr['geom'][$i] = $geoObj -> getAsMulti();
					}
				}
				$sqlInsert = "insert into ".$tableName." (".$this -> arr['fields'][$i].",the_geom) values(".$this -> arr['values'][$i].",'SRID=25832;".$this -> arr['geom'][$i]."')";
				// Check if feature has geometry
				if ($this -> arr['geom'][$i]!="()")
				{
					$result = $postgisObject -> execQuery($sqlInsert);
					if ($result AND pg_affected_rows($result) == 1)
					{
						$countRows++;
						$postgisObject -> free($result);
					}
					else {
						logfile::write("Error in #".$i."\n");
						logfile::write("ROLLBACK\n");
						logfile::write($sqlInsert."\n");
						$postgisObject -> execQuery(ROLLBACK);
						logfile::write("Script terminated\n");
						die();
					}
				}
				else {
					logfile::write("#. ".$i." missing geometry.\n");
					logfile::write("ROLLBACK\n");
					$postgisObject -> execQuery(ROLLBACK);
					logfile::write("Script terminated\n");
					die();
					}

				//echo ".";
			}
			$postgisObject -> execQuery(COMMIT); // End of transactions block
		}
		else {
			$sql = "DELETE FROM ".$tableName;
			$result = $postgisObject -> execQuery($sql);
			$postgisObject -> free($result);
			$countRows = "0";
		}
		logfile::write(date('l jS \of F Y h:i:s A')." ".$countRows." features loaded in table '".$tableName."'\n");
	}
	function getHTML()
	{
		return($this->array2table($this -> gmlArray));
	}
	function array2table($array, $recursive = true, $return = true, $null = '&nbsp;')
	{
		// Sanity check
		if (empty($array) || !is_array($array)) {
			return "teteg";
		}

		if (!isset($array[0]) || !is_array($array[0])) {
			$array = array($array);
		}

		// Start the table
		$table = "<table>\n";

		// The header
		$table .= "\t<tr>";
		// Take the keys from the first row as the headings
		foreach (array_keys($array[0]) as $heading) {
			$table .= '<th>' . $heading . '</th>';
		}
		$table .= "</tr>\n";

		// The body
		foreach ($array as $row) {
			$table .= "\t<tr>" ;
			foreach ($row as $cell) {
				$table .= '<td>';

				// Cast objects
				if (is_object($cell)) { $cell = (array) $cell; }

				if ($recursive === true && is_array($cell) && !empty($cell)) {
					// Recursive mode
					$table .= "\n" . $this -> array2table($cell, true, true) . "\n";
				} else {
					$table .= (strlen($cell) > 0) ?
					htmlspecialchars((string) $cell) :
					$null;
				}

				$table .= '</td>';
			}

			$table .= "</tr>\n";
		}

		// End the table
		$table .= '</table>';

		// Method of output
		if ($return === false) {
			return "test";
		} else {
			return $table;
		}
	}


}
class arrayToHtml
{
	var $HTML;
	function do_offset($level){
		$offset = "";             // offset for subarry
		for ($i=1; $i<$level;$i++){
			$offset = $offset . "<td></td>";
		}
		return $offset;
	}

	function show_array($array, $level, $sub){
		if (is_array($array) == 1){          // check if input is an array
			foreach($array as $key_val => $value) {
				$offset = "";
				if (is_array($value) == 1){   // array is multidimensional
					$this -> HTML.= "<tr>";
					$offset = $this -> do_offset($level);
					$this -> HTML.= $offset . "<td>" . $key_val . "</td>";
					$this -> show_array($value, $level+1, 1);
				}
				else{                        // (sub)array is not multidim
					if ($sub != 1){          // first entry for subarray
						$this -> HTML.= "<tr nosub>";
						$offset = $this -> do_offset($level);
					}
					$sub = 0;
					$this -> HTML.= $offset . "<td main ".$sub." width=\"120\">" . $key_val .
	               "</td><td width=\"120\">" . $value . "</td>";
					$this -> HTML.= "</tr>\n";
				}
			} //foreach $array
		}
		else{ // argument $array is not an array
			return;
		}
	}

	function html_show_array($array){
		$this -> HTML.= "<table cellspacing=\"0\" border=\"2\">\n";
		$this -> show_array($array, 1, 0);
		$this -> HTML.= "</table>\n";
	}
	function getHTML()
	{
		return($this -> HTML);
	}
}
class htmlSpecialChars
{
	function ConvertDanishChars($string)
	{
		$string = str_replace("�","&aelig;",$string);
		$string = str_replace("�","&oslash;",$string);
		$string = str_replace("�","&aring;",$string);
		$string = str_replace("�","&AElig;",$string);
		$string = str_replace("�","&Oslash;",$string);
		$string = str_replace("�","&Aring;",$string);
		return($string);
	}
}
class logfile {

	/**
	 *
	 *
	 * @param unknown $the_string
	 * @return unknown
	 */
	function write($the_string) {

		if ( $fh = fopen("log.txt", "a+" ) ) {
			fputs( $fh, $the_string, strlen($the_string) );
			fclose( $fh );
			return true;
		}
		else {
			return false;
		}

	}
}
?>
