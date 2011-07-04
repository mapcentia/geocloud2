<?

/*
This preliminary script is part of OpenSVGMapServer project, see
http://www.carto.net/opensvgmapserver
distributed under GNU GPL license
info: Nedjo Rogers, nedjo@miningwatch.org
*/

function geometrytype($geom){
  $pos = strpos($geom, "(");
  $geomType = substr($geom, 0, $pos);
  return $geomType;
}

function dropShapeName($str) {
  return strchr($str,"(");
}

function dropFirstLastChrs($str) {
  $strLen=strlen($str);
  return (substr($str,1,($strLen)-2));
}

function addSpacesMulti($str) {
  $str = str_replace(")),((", ")) , ((", $str);
  return $str;
}

function addSpacesSingle($str) {
  $str = str_replace("),(", ") , (", $str);
  return $str;
}

function explodeGeom($str) {
  $strs = explode(" , ", $str);
  return $strs;
}

function writeTag($type,$ns,$tag,$atts,$ind,$n){
  global $depth;
  if($ind!=False){
    for($i=0;$i<$depth;$i++){
      echo "  ";
    }
  }
  if($ns!=null){
    $tag=$ns.":".$tag;
  }
  echo "<";
  if($type=="close"){
    echo "/";
  }
  echo $tag;
  if(!empty($atts)){
    foreach ($atts as $key => $value) {
      echo ' '.$key.'="'.$value.'"';
    }
  }
  if($type=="selfclose"){
    echo "/";
  }
  echo ">";
  if($n==True){
    echo "\n";
  }
}

function convertCoordinatesToGML($str){
  $str = str_replace(" ","&",$str);
  $str = str_replace(","," ",$str);
  $str = str_replace("&",",",$str);
  $str = str_replace("(","",$str);
  $str = str_replace(")","",$str);
  return $str;
}

function convertGeom($geom){
  $geomType=strtoupper(geometrytype($geom));
  $geom=dropFirstLastChrs(dropShapeName($geom));
  switch ($geomType) {
    case "POINT":
      convertPoint($geom);
      break;
    case "LINESTRING":
      convertLineString($geom);
      break;
    case "POLYGON":
      convertPolygon($geom);
      break;
    case "MULTILINESTRING":
      convertMultiLineString($geom);
      break;
    case "MULTIPOLYGON":
      convertMultiPolygon($geom);
      break;
    case "GEOMETRYCOLLECTION":
      convertGeometryCollection($geom);
      break;
  }
}

function convertMultiPoint($geom) {
  global $depth;
  $geom=addSpacesMulti($geom);
  writeTag("open","gml","MultiPoint",Null,True,True);
  $depth++;
  $lines=explodeGeom($geom);
  foreach ($points as $point) {
    writeTag("open","gml","pointMember",Null,True,True);
    $depth++;
    convertPoint(dropFirstLastChrs($point));
    $depth--;
    writeTag("close","gml","pointMember",Null,True,True);
  }
  $depth--;
  writeTag("close","gml","MultiPoint",Null,True,True);
}

function convertPoint($geom){
  global $depth;
  writeTag("open","gml","Point",Null,True,True);
  $depth++;
  writeTag("open","gml","coordinates",Null,True,False);
  echo convertCoordinatesToGML($geom);
  writeTag("close","gml","coordinates",Null,False,True);
  $depth--;
  writeTag("close","gml","Point",Null,True,True);
}

function convertMultiLineString($geom) {
  global $depth;
  $geom=addSpacesSingle($geom);
  writeTag("open","gml","MultiLineString",Null,True,True);
  $depth++;
  $lines=explodeGeom($geom);
  foreach ($lines as $line) {
    writeTag("open","gml","lineStringMember",Null,True,True);
    $depth++;
    convertLineString(dropFirstLastChrs($line));
    $depth--;
    writeTag("close","gml","lineStringMember",Null,True,True);
  }
  $depth--;
  writeTag("close","gml","MultiLineString",Null,True,True);
}

function convertLineString($geom){
  global $depth;
  writeTag("open","gml","LineString",Null,True,True);
  $depth++;
  writeTag("open","gml","coordinates",Null,True,False);
  echo convertCoordinatesToGML($geom);
  writeTag("close","gml","coordinates",Null,False,True);
  $depth--;
  writeTag("close","gml","LineString",Null,True,True);
}

function convertMultiPolygon($geom) {
  global $depth;
  $geom=addSpacesMulti($geom);
  writeTag("open","gml","MultiPolygon",Null,True,True);
  $depth++;
  $polys=explodeGeom($geom);
  foreach ($polys as $poly) {
    writeTag("open","gml","polygonMember",Null,True,True);
    $depth++;
    convertPolygon(dropFirstLastChrs($poly));
    $depth--;
    writeTag("close","gml","polygonMember",Null,True,True);
  }
  $depth--;
  writeTag("close","gml","MultiPolygon",Null,True,True);
}

function convertPolygon($geom){
  global $depth;
  $geom=addSpacesSingle($geom);
  $rings=explodeGeom($geom);
  writeTag("open","gml","Polygon",Null,True,True);
  $depth++;
  $pass=0;
  foreach ($rings as $ring) {
    $ring=dropFirstLastChrs($ring);
    if($pass==0){
      $boundTag="outer";
    }
    else{
      $boundTag="inner";
    }
    writeTag("open","gml","".$boundTag."BoundaryIs",Null,True,True);
    $depth++;
    writeTag("open","gml","LinearRing",Null,True,True);
    $depth++;
    writeTag("open","gml","coordinates",Null,True,False);
    echo convertCoordinatesToGML($ring);
    writeTag("close","gml","coordinates",Null,False,True);
    $depth--;
    writeTag("close","gml","LinearRing",Null,True,True);
    $depth--;
    writeTag("close","gml","".$boundTag."BoundaryIs",Null,True,True);
    $pass++;
  }
  $depth--;
  writeTag("close","gml","Polygon",Null,True,True);
}

function convertGeometryCollection($geom){
  global $depth;
  $searchstr = array(",POINT",",LINESTRING",",POLYGON",",MULTIPOINT",",MULTILINESTRING",",MULTIPOLYGON");
  $replacestr = array(" , POINT"," , LINESTRING"," , POLYGON"," , MULTIPOINT"," , MULTILINESTRING"," , MULTIPOLYGON");
  $geom = str_replace($searchstr,$replacestr,$geom);
  writeTag("open","gml","MultiGeometry",Null,True,True);
  $depth++;
  $geoms=explodeGeom($geom);
  foreach ($geoms as $geom) {
    writeTag("open","gml","geometryMember",Null,True,True);
    $depth++;
    convertGeom($geom);
    $depth--;
    writeTag("close","gml","geometryMember",Null,True,True);
  }
  $depth--;
  writeTag("close","gml","MultiGeometry",Null,True,True);
}

?>