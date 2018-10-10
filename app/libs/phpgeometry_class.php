<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

class geometryfactory
{
    /**
     * @return Geometry object
     * @param string $wkt test
     * @param int $srid
     * @desc Creates a new geometry object from a wkt string
     */
    function createGeometry($wkt, $srid = NULL)//creates a new geometry object. Factory function
    {
        $wkt = str_replace(", ", ",", $wkt);// replace " ," with ","
        preg_match_all("/[a-z]*[A-Z]*/", $wkt, $__typeArray);//Match the type of the geometry
        $__type = $__typeArray[0][0];
        switch ($__type) {
            case "MULTIPOLYGON":
                $geometryObject = new multipolygon($wkt, $srid);
                break;
            case "MULTILINESTRING":
                $geometryObject = new multilinestring($wkt, $srid);
                break;
            case "MULTIPOINT":
                $geometryObject = new multipoint($wkt, $srid);
                break;
            case "POINT":
                $geometryObject = new _point($wkt, $srid); //point is a key word
                break;
            case "LINESTRING":
                $geometryObject = new linestring($wkt, $srid);
                break;
            case "POLYGON":
                $geometryObject = new polygon($wkt, $srid);
                break;
        }
        return ($geometryObject);
    }

    /**
     * Enter description here...
     *
     * @param unknown_type $wktArray
     * @return unknown
     */
    function createGeometryCollection($wktArray)
    {
        $geometryCollection = new geometryCollection($wktArray);
        return ($geometryCollection);
    }

    function deconstructionOfWKT() // Take a WKT string and returns a array with coords(string) for shapes. Called from a child object
    {
        preg_match_all("/[^a-z|(*]*[0-9]/", $this->wkt, $__wktArray); // regex is used exstract coordinates
        $wktArray = $__wktArray[0];
        if ($this->getGeomType() == "MULTIPOLYGON" || $this->getGeomType() == "POLYGON") {
            preg_match_all("/[^a-z|)]*[0-9]/", $this->wkt, $__array); // regex is used to find island shapes
            for ($__i = 0; $__i < (sizeof($__array[0])); $__i++) {
                if (substr($__array[0][$__i], 0, 2) == ",(" && substr($__array[0][$__i], 2, 1) != "(") {
                    $this->isIsland[$__i] = true;
                } else {
                    $this->isIsland[$__i] = false;
                }
            }
        }
        if ($this->getGeomType() == "MULTIPOINT")// if multipoint when split the string again
        {
            preg_match_all("/[^a-z|,]*[0-9]/", $wktArray[0], $__array); // regex is used exstract coordinates
            $wktArray = $__array[0];
        }
        return ($wktArray);
    }

    function snapCoords($coordStr, $snapTolerance, $snapLayerStr, $shapeArray = array())
    {
        $__snap = false;
        $__newCoordStr = explode(",", $coordStr);
        $__snapLayerArray = explode(",", $snapLayerStr);
        $i = 0;
        foreach ($__newCoordStr as $__v)// each point from the coordStr string is looped
        {
            $__snapToleranceTmp = $snapTolerance;// asign the value to a tmp var, so it can be restored
            $__oneCoord = explode(" ", $__v);
            $__oneCoordTmp = explode(" ", $__v);// save the original coord in a tmp for line snap
            $u = 0;
            // first check for vertex snap
            if (sizeof($__snapLayerArray)) {
                foreach ($__snapLayerArray as $__u)// each possible pointsnap point is evaluated
                {
                    $__snapLayerCoord = explode(" ", $__u);
                    $diffX = $__snapLayerCoord[0] - $__oneCoord[0];
                    $diffY = $__snapLayerCoord[1] - $__oneCoord[1];
                    $diff = sqrt(pow($diffX, 2) + pow($diffY, 2));
                    //calculation of distance between the two point
                    if ($diff <= $__snapToleranceTmp)// true if in snap tolerance
                    {
                        //echo "snap"."<br>";
                        $__snapToleranceTmp = $diff;
                        //decrease of snap tolerance, so only the nearest point is used
                        $__snap = true; // point is snapped
                        $__newSnapLayerCoord[0] = $__snapLayerCoord[0];
                        $__newSnapLayerCoord[1] = $__snapLayerCoord[1];
                    }
                }
            }
            if ($__snap == true) //true if evaluated point is snapped
            {
                $__oneCoord[0] = $__newSnapLayerCoord[0];
                //New value of digi point
                $__oneCoord[1] = $__newSnapLayerCoord[1];
                $__snap = false; // so the next digi point is not snapped
            }
            // second check for line snap
            if (sizeof($shapeArray)) {
                foreach ($shapeArray as $__u)// each possible line snap is evaluated
                {
                    //echo "<script>alert('$__u');</script>";
                    $__lineSegments = explode(",", $__u);
                    for ($__i = 0; $__i < (sizeof($__lineSegments) - 1); $__i++) {
                        $__l1 = explode(" ", $__lineSegments[$__i]);// first coord in line segment
                        $__l2 = explode(" ", $__lineSegments[$__i + 1]);// second coord in line segment
                        $diff = $this->distancePointToLine($__oneCoordTmp, $__l1, $__l2);
                        //echo "<script>alert('tmp ".$__snapToleranceTmp."');</script>";
                        //echo "<script>alert('lineafstand ".$diff[0]."');</script>";
                        if ($diff[0] < $__snapToleranceTmp && $diff[0] != FALSE)// true if in snap tolerance
                        {
                            //echo "<script>alert('".$diff[0]."');</script>"."<br>";
                            $__snapToleranceTmp = $diff[0];
                            //decrease of snap tolerance, so only the nearest point is used
                            $__snap = true; // point is snapped
                            $newCoord = $diff[1];
                            $__newSnapLayerCoord[0] = $newCoord[0];
                            $__newSnapLayerCoord[1] = $newCoord[1];
                        }
                    }
                }
                if ($__snap == true) //true if evaluated point is snapped
                {
                    $__oneCoord[0] = $__newSnapLayerCoord[0];
                    //New value of digi point
                    $__oneCoord[1] = $__newSnapLayerCoord[1];
                    $__snap = false; // so the next digi point is not snapped
                }
            }
            $__newCoordStr[$i] = implode(" ", $__oneCoord);
            $i++;
        }
        $coordStr = implode(",", $__newCoordStr);
        return ($coordStr);
    }

    /**
     * Enter description here...
     *
     * @return unknown
     */
    function getVertices()//get a string with all vertices of geometry
    {
        $verticeStr = "";
        foreach ($this->shapeArray as $__value) {
            $verticeStr = $verticeStr . $__value . ",";
        }
        // remove the last comma
        $verticeStr = substr($verticeStr, 0, strlen($verticeStr) - 1);
        return ($verticeStr);
    }

    function updateShape($coorStr, $shapeId)// updates the geometry on shape level. Takes a string with coords and a shape id
    {
        for ($__i = 0; $__i < sizeof($this->shapeArray); $__i++) {
            if ($__i == $shapeId) {
                $this->shapeArray[$__i] = $coorStr;
                $__check = true;
            }
        }
        //echo "<script>alert(\"".$coorStr."\")</script>";
        if (!$__check) {
            $this->shapeArray[$this->getNumOfShapes()] = $coorStr;
        }
        $this->construction();
    }

    function snapShape($shapeId, $snapTolerance, $snapLayerStr, $shapeArray = array())// snaps one shape with shapeId of multifeature
    {
        $__newShape = $this->snapCoords($this->shapeArray[$shapeId], $snapTolerance, $snapLayerStr, $shapeArray);
        $this->updateShape($__newShape, $shapeId);
    }

    function snapAllShapes($snapTolerance, $snapLayerStr, $shapeArray = array())// snaps all shapes of multifeature or just like snap the hole geometry
    {
        foreach ($this->shapeArray as $__key => $__shape) {
            $__newShape = $this->snapCoords($this->shapeArray[$__key], $snapTolerance, $snapLayerStr, $shapeArray);
            $this->updateShape($__newShape, $__key);
        }
    }

    function getWKT()
    {
        return ($this->wkt);
    }

    function getGML($ver = "2")
    {
        switch ($ver) {
            case "2":
                $_gml = $this->toGML();
                break;
            case "3":
                $_gml = $this->toGML3();
                break;

        }
        return ($_gml);
    }

    function getShapeArray()
    {
        return ($this->shapeArray);
    }

    function getGeomType()
    {
        return ($this->geomType[$count]);
    }

    function getNumOfShapes()
    {
        return sizeOf($this->getShapeArray());
    }

    function writeTag($type, $ns, $tag, $atts, $ind, $n)
    {
        $_str = "";
        global $depth;
        if ($ind != False) {
            for ($i = 0; $i < $depth; $i++) {
                $_str = $_str . "  ";
            }
        }
        if ($ns != null) {
            $tag = $ns . ":" . $tag;
        }
        $_str .= "<";
        if ($type == "close") {
            $_str = $_str . "/";
        }
        $_str = $_str . $tag;
        if (!empty($atts)) {
            foreach ($atts as $key => $value) {
                $_str = $_str . ' ' . $key . '="' . $value . '"';
            }
        }
        if ($type == "selfclose") {
            $_str = $_str . "/";
        }
        $_str = $_str . ">";
        if ($n == True) {
            $_str = $_str . "\n";
        }
        return ($_str);
    }

    /**
     * @return array
     * @param array $p
     * @param array $l1
     * @param array $l2
     * @desc Caculate the distance between a point and a line with two points and the point of perpendicular projection
     */
    function distancePointToLine($p, $l1, $l2)
    {
        $u = ((($l2[0] - $l1[0]) * ($l1[1] - $p[1])) - (($l1[0] - $p[0]) * ($l2[1] - $l1[1])));
        $l = sqrt(pow(($l2[0] - $l1[0]), 2) + pow(($l2[1] - $l1[1]), 2));
        if ($l) $a = ($u / $l);
        if ($a < 0) $a = $a * -1;
        $diffX = $l1[0] - $l2[0];
        $diffY = $l1[1] - $l2[1];
        $l = sqrt(pow($diffX, 2) + pow($diffY, 2));
        if ($l) $r = (($p[0] - $l1[0]) * ($l2[0] - $l1[0]) + ($p[1] - $l1[1]) * ($l2[1] - $l1[1])) / pow($l, 2);
        $newCoord[0] = $l1[0] + $r * ($l2[0] - $l1[0]);
        $newCoord[1] = $l1[1] + $r * ($l2[1] - $l1[1]);
        //Set the bounding box of line segment
        if ($l1[0] >= $l2[0]) {
            $__maxX = $l1[0];
            $__minX = $l2[0];
        } else {
            $__maxX = $l2[0];
            $__minX = $l1[0];
        }
        if ($l1[1] >= $l2[1]) {
            $__maxY = $l1[1];
            $__minY = $l2[1];
        } else {
            $__maxY = $l2[1];
            $__minY = $l1[1];
        }
        // If the point of perpendicular projection is outside bbox then set distance to FALSE
        if ($newCoord[0] > $__maxX || $newCoord[0] < $__minX || $newCoord[1] > $__maxY || $newCoord[1] < $__minY) {
            $a = FALSE;
            //echo "<script>alert('outside');</script>";
        }
        return (array($a, $newCoord));
    }

    function convertPoint($geom, $hasSrid = TRUE)
    {
        global $depth;
        if (($hasSrid) && ($this->srid != NULL)) $srid = array("srsName" => $this->srid);
        else $srid = NULL;
        $_str = "";
        $_str = $_str . $this->writeTag("open", "gml", "Point", $srid, True, True);
        $depth++;
        $_str = $_str . $this->writeTag("open", "gml", "coordinates", NULL, True, False);
        $_str = $_str . $this->convertCoordinatesToGML($geom);
        $_str = $_str . $this->writeTag("close", "gml", "coordinates", Null, False, True);
        $depth--;
        $_str = $_str . $this->writeTag("close", "gml", "Point", Null, True, True);
        return ($_str);
    }

    function convertPointToKml($geom, $extrude, $tessellate, $altitudeMode)
    {
        global $depth;
        $_str .= $this->writeTag("open", null, "Point", null, true, true);
        $depth++;
        $_str .= $this->writeTag("open", null, "extrude", null, true, false);
        $_str .= $extrude;
        $_str .= $this->writeTag("close", null, "extrude", null, false, true);
        $_str .= $this->writeTag("open", null, "tessellate", null, true, false);
        $_str .= $tessellate = 1;
        $_str .= $this->writeTag("close", null, "tessellate", null, false, true);
        $_str .= $this->writeTag("open", null, "altitudeMode", null, true, false);
        $_str .= $altitudeMode;
        $_str .= $this->writeTag("close", null, "altitudeMode", null, false, true);
        $_str .= $this->writeTag("open", null, "coordinates", null, true, false);
        $depth++;
        $_str .= $this->convertCoordinatesToKML($geom);
        $depth--;
        $_str .= $this->writeTag("close", null, "coordinates", null, false, true);
        $depth--;
        $_str .= $this->writeTag("close", null, "Point", null, true, true);
        return ($_str);
    }

    function convertLineString($geom, $hasSrid = TRUE)
    {
        global $depth;
        if (($hasSrid) && ($this->srid != NULL)) $srid = array("srsName" => $this->srid);
        else $srid = NULL;
        $_str = "";
        $_str .= $this->writeTag("open", "gml", "LineString", $srid, True, True);
        $depth++;
        $_str .= $this->writeTag("open", "gml", "coordinates", Null, True, False);
        $_str .= $this->convertCoordinatesToGML($geom);
        $_str .= $this->writeTag("close", "gml", "coordinates", Null, False, True);
        $depth--;
        $_str .= $this->writeTag("close", "gml", "LineString", Null, True, True);
        return ($_str);
    }

    function convertLineStringToKML($geom, $extrude, $tessellate, $altitudeMode)
    {
        global $depth;
        $_str .= $this->writeTag("open", null, "LineString", null, true, true);
        $depth++;
        $_str .= $this->writeTag("open", null, "extrude", null, true, false);
        $_str .= $extrude;
        $_str .= $this->writeTag("close", null, "extrude", null, false, true);
        $_str .= $this->writeTag("open", null, "tessellate", null, true, false);
        $_str .= $tessellate = 1;
        $_str .= $this->writeTag("close", null, "tessellate", null, false, true);
        $_str .= $this->writeTag("open", null, "altitudeMode", null, true, false);
        $_str .= $altitudeMode;
        $_str .= $this->writeTag("close", null, "altitudeMode", null, false, true);
        $_str .= $this->writeTag("open", null, "coordinates", null, true, false);
        $depth++;
        $_str .= $this->convertCoordinatesToKML($geom);
        $depth--;
        $_str .= $this->writeTag("close", null, "coordinates", null, false, true);
        $depth--;
        $_str .= $this->writeTag("close", null, "LineString", null, true, true);
        return ($_str);
    }

    function convertLineStringGML3($geom, $hasSrid = TRUE)
    {
        global $depth;
        if (($hasSrid) && ($this->srid != NULL)) $srid = array("srsName" => $this->srid);
        else $srid = NULL;
        $_str = "";
        $_str .= $this->writeTag("open", "gml", "LineString", $srid, True, True);
        $depth++;
        $_str .= $this->writeTag("open", "gml", "posList", Null, True, False);
        $_str .= $this->convertCoordinatesToGML3($geom);
        $_str .= $this->writeTag("close", "gml", "posList", Null, False, True);
        $depth--;
        $_str .= $this->writeTag("close", "gml", "LineString", Null, True, True);
        return ($_str);
    }

    /**
     * @return unknown
     * @param unknown $rings
     * @param unknown $hasSrid
     * @desc Enter description here...
     */
    function convertPolygon($rings, $hasSrid = TRUE)
    {
        global $depth;
        if (($hasSrid) && ($this->srid != NULL)) $srid = array("srsName" => $this->srid);
        else $srid = NULL;
        $_str = "";
        $_str = $_str . $this->writeTag("open", "gml", "Polygon", $srid, True, True);
        $depth++;
        $pass = 0;
        foreach ($rings as $ring) {
            if ($pass == 0) {
                $boundTag = "outer";
            } else {
                $boundTag = "inner";
            }
            $_str = $_str . $this->writeTag("open", "gml", "" . $boundTag . "BoundaryIs", Null, True, True);
            $depth++;
            $_str = $_str . $this->writeTag("open", "gml", "LinearRing", Null, True, True);
            $depth++;
            $_str = $_str . $this->writeTag("open", "gml", "coordinates", Null, True, False);
            $_str = $_str . $this->convertCoordinatesToGML($ring);
            $_str = $_str . $this->writeTag("close", "gml", "coordinates", Null, False, True);
            $depth--;
            $_str = $_str . $this->writeTag("close", "gml", "LinearRing", Null, True, True);
            $depth--;
            $_str = $_str . $this->writeTag("close", "gml", "" . $boundTag . "BoundaryIs", Null, True, True);
            $pass++;
        }
        $depth--;
        $_str = $_str . $this->writeTag("close", "gml", "Polygon", Null, True, True);
        return ($_str);
    }

    function convertPolygonToKML($rings, $extrude, $tessellate, $altitudeMode)
    {
        global $depth;
        $_str = "";
        $_str = $_str . $this->writeTag("open", NULL, "Polygon", $srid, True, True);
        $depth++;
        $pass = 0;
        foreach ($rings as $ring) {
            if ($pass == 0) {
                $boundTag = "outer";
            } else {
                $boundTag = "inner";
            }
            $_str = $_str . $this->writeTag("open", NULL, "" . $boundTag . "BoundaryIs", Null, True, True);
            $depth++;
            $_str .= $this->writeTag("open", null, "extrude", null, true, false);
            $_str .= $extrude;
            $_str .= $this->writeTag("close", null, "extrude", null, false, true);
            $_str .= $this->writeTag("open", null, "tessellate", null, true, false);
            $_str .= $tessellate = 1;
            $_str .= $this->writeTag("close", null, "tessellate", null, false, true);
            $_str .= $this->writeTag("open", null, "altitudeMode", null, true, false);
            $_str .= $altitudeMode;
            $_str .= $this->writeTag("close", null, "altitudeMode", null, false, true);
            $_str = $_str . $this->writeTag("open", NULL, "LinearRing", Null, True, True);
            $depth++;
            $_str = $_str . $this->writeTag("open", NULL, "coordinates", Null, True, False);
            $_str = $_str . $this->convertCoordinatesToKML($ring);
            $_str = $_str . $this->writeTag("close", NULL, "coordinates", Null, False, True);
            $depth--;
            $_str = $_str . $this->writeTag("close", NULL, "LinearRing", Null, True, True);
            $depth--;
            $_str = $_str . $this->writeTag("close", NULL, "" . $boundTag . "BoundaryIs", Null, True, True);
            $pass++;
        }
        $depth--;
        $_str = $_str . $this->writeTag("close", NULL, "Polygon", Null, True, True);
        return ($_str);
    }

    function convertCoordinatesToGML($_str)
    {
        $_str = str_replace(" ", "&", $_str);
        $_str = str_replace(",", " ", $_str);
        $_str = str_replace("&", ",", $_str);
        $_str = str_replace("(", "", $_str);
        $_str = str_replace(")", "", $_str);
        return ($_str);
    }

    function convertCoordinatesToGML3($_str)
    {
        $_str = str_replace(",", " ", $_str);
        return ($_str);
    }

    function convertCoordinatesToKML($_str)
    {
        $_str = str_replace(" ", "&", $_str);
        $_str = str_replace(",", " ", $_str);
        $_str = str_replace("&", ",", $_str);
        $_str = str_replace("(", "", $_str);
        $_str = str_replace(")", "", $_str);
        return ($_str);
    }

    function getExtent()
    {
        $__coordArray = explode(",", $this->getVertices());
        $__max_x = 0;
        $__max_y = 0;
        $__min_x = 0;
        $__min_y = 0;

        foreach ($__coordArray as $value) {
            $__coord = explode(" ", $value);
            if ($__max_x == 0)
                $__max_x = $__coord[0];
            if ($__max_y == 0)
                $__max_y = $__coord[1];
            if ($__min_x == 0)
                $__min_x = $__coord[0];
            if ($__min_y == 0)
                $__min_y = $__coord[1];
            if ($__max_x < $__coord[0])
                $__max_x = $__coord[0];
            if ($__min_x > $__coord[0])
                $__min_x = $__coord[0];
            if ($__max_y < $__coord[1])
                $__max_y = $__coord[1];
            if ($__min_y > $__coord[1])
                $__min_y = $__coord[1];
        }
        $__width = $__max_x - $__min_x;
        $__height = $__max_y - $__min_y;
        $wkt = "POLYGON(($__min_x $__min_y,$__min_x $__max_y,$__max_x $__max_y,$__max_x $__min_y,$__min_x $__min_y))";
        $extentObj = geometryfactory::createGeometry($wkt, $this->srid);
        return ($extentObj);
    }

    function getMinX()
    {
        $__extent = $this->getExtent();
        $__coordArray = explode(",", $__extent->getVertices());
        $__pointArray = explode(" ", $__coordArray[0]);
        return ($__pointArray[0]);
    }

    function getMaxX()
    {
        $__extent = $this->getExtent();
        $__coordArray = explode(",", $__extent->getVertices());
        $__pointArray = explode(" ", $__coordArray[2]);
        return ($__pointArray[0]);
    }

    function getMinY()
    {
        $__extent = $this->getExtent();
        $__coordArray = explode(",", $__extent->getVertices());
        $__pointArray = explode(" ", $__coordArray[0]);
        return ($__pointArray[1]);
    }

    function getMaxY()
    {
        $__extent = $this->getExtent();
        $__coordArray = explode(",", $__extent->getVertices());
        $__pointArray = explode(" ", $__coordArray[2]);
        return ($__pointArray[1]);
    }

    function getCenter()
    {
        $__coordX = $this->getMinX() + (($this->getMaxX() - $this->getMinX()) / 2);
        $__coordY = $this->getMinY() + (($this->getMaxY() - $this->getMinY()) / 2);
        $wkt = "POINT(" . $__coordX . " " . $__coordY . ")";
        $pointObj = geometryfactory::createGeometry($wkt, $this->srid);
        return ($pointObj);
    }

}

class _point extends geometryfactory
{
    var $wkt;
    var $srid;
    var $shapeArray;
    var $geomType;

    function _point($wkt, $srid)// constructor. wkt is set
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType[$count] = 'POINT';
        $this->shapeArray = parent::deconstructionOfWKT($this->wkt);

    }

    function construction()// puts the deconstructed wkt together again and sets the wkt
    {
        $__newWkt = $this->geomType[$count] . "(" . $this->shapeArray[0] . ")";
        $this->wkt = $__newWkt;
    }

    function getAsMulti()// return wkt as multi feature
    {
        $__newWkt = "MULTI" . $this->geomType[$count] . "(";
        $__newWkt = $__newWkt . "(" . $this->shapeArray[0] . ")";
        $__newWkt = $__newWkt . ")";
        $wkt = $__newWkt;
        return ($wkt);
    }

    function toGML()
    {
        global $depth;
        $_str = "";
        $_str .= $this->convertPoint($this->shapeArray[0]);
        return ($_str);
    }

    function toKML($extrude = 0, $tessellate = 1, $altitudeMode = "clampToGround")
    {
        global $depth;
        $_str = "";
        $_str .= $this->convertPointToKml($this->shapeArray[0], $extrude, $tessellate, $altitudeMode);
        return ($_str);
    }
}

class linestring extends geometryfactory
{
    var $wkt;
    var $srid;
    var $shapeArray;
    var $geomType;

    function linestring($wkt, $srid)// constructor. wkt is set
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType[$count] = 'LINESTRING';
        $this->shapeArray = parent::deconstructionOfWKT($this->wkt);

    }

    function construction()// puts the deconstructed wkt together again and sets the wkt
    {
        $__newWkt = $this->geomType[$count] . "(" . $this->shapeArray[0] . ")";
        $this->wkt = $__newWkt;
    }

    function getAsMulti()// return wkt as multi feature
    {
        $__newWkt = "MULTI" . $this->geomType[$count] . "(";
        $__newWkt = $__newWkt . "(" . $this->shapeArray[0] . ")";
        $__newWkt = $__newWkt . ")";
        $wkt = $__newWkt;
        return ($wkt);
    }

    function toGML()
    {
        global $depth;
        $_str = "";
        $_str .= $this->convertLineString($this->shapeArray[0]);
        return ($_str);
    }
}

class polygon extends geometryfactory
{
    var $wkt;
    var $srid;
    var $shapeArray;
    var $geomType;

    function polygon($wkt, $srid)// constructor. wkt is set
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType[$count] = 'POLYGON';
        $this->shapeArray = parent::deconstructionOfWKT($this->wkt);

    }

    function construction()// puts the deconstructed wkt together again and sets the wkt
    {
        $__newWkt = $this->geomType[$count] . "(";
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $__wktArray[$__i] = "(" . $this->shapeArray[$__i] . ")";
        }
        $__newWkt = $__newWkt . implode(",", $__wktArray);
        $__newWkt = $__newWkt . ")";
        $this->wkt = $__newWkt;
    }

    function getAsMulti()// return wkt as multi feature
    {
        $__newWkt = "MULTI" . $this->geomType[$count] . "(";
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $__wktArray[$__i] = "((" . $this->shapeArray[$__i] . "))";
        }
        $__newWkt = $__newWkt . implode(",", $__wktArray);
        $__newWkt = $__newWkt . ")";
        $wkt = $__newWkt;
        return ($wkt);
    }

    function toGML()
    {
        global $depth;
        $_str = "";
        $_str .= $this->convertPolygon($this->shapeArray);
        return ($_str);
    }
}

class multipoint extends geometryfactory
{
    var $wkt;
    var $srid;
    var $shapeArray;
    var $geomType;

    function multipoint($wkt, $srid)// constructor. wkt is set
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType[$count] = 'MULTIPOINT';
        $this->shapeArray = parent::deconstructionOfWKT($this->wkt);

    }

    function construction()// puts the deconstructed wkt together again and sets the wkt
    {
        $__newWkt = $this->geomType[$count] . "(";
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $__wktArray[$__i] = $this->shapeArray[$__i];
        }
        $__newWkt = $__newWkt . implode(",", $__wktArray);
        $__newWkt = $__newWkt . ")";
        $this->wkt = $__newWkt;
    }

    function toGML()
    {
        global $depth;
        if ($this->srid) $srid = array("srsName" => $this->srid);
        else $srid = NULL;
        $_str = "";
        $_str .= $this->writeTag("open", "gml", "MultiPoint", $srid, True, True);
        $depth++;
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $_str .= $this->writeTag("open", "gml", "pointMember", Null, True, True);
            $depth++;
            $_str .= $this->convertPoint($this->shapeArray[$__i], FALSE);
            $depth--;
            $_str .= $this->writeTag("close", "gml", "pointMember", Null, True, True);
        }
        $depth--;
        $_str .= $this->writeTag("close", "gml", "MultiPoint", Null, True, True);
        return ($_str);
    }
}

class multilinestring extends geometryfactory
{
    var $wkt;
    var $srid;
    var $shapeArray;
    var $geomType;

    function multilinestring($wkt, $srid)// constructor. wkt is set
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType[$count] = 'MULTILINESTRING';
        $this->shapeArray = parent::deconstructionOfWKT($this->wkt);
    }

    function construction()// puts the deconstructed wkt together again and sets the wkt
    {
        $__newWkt = $this->geomType[$count] . "(";
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $__wktArray[$__i] = "(" . $this->shapeArray[$__i] . ")";
        }
        $__newWkt = $__newWkt . implode(",", $__wktArray);
        $__newWkt = $__newWkt . ")";
        $this->wkt = $__newWkt;
    }

    function toGML()
    {
        global $depth;
        if ($this->srid) $srid = array("srsName" => $this->srid);
        else $srid = NULL;
        $_str = "";
        $_str .= $this->writeTag("open", "gml", "MultiLineString", $srid, True, True);
        $depth++;
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $_str .= $this->writeTag("open", "gml", "lineStringMember", Null, True, True);
            $depth++;
            $_str .= $this->convertLineString($this->shapeArray[$__i], FALSE);
            $depth--;
            $_str .= $this->writeTag("close", "gml", "lineStringMember", Null, True, True);
        }
        $depth--;
        $_str .= $this->writeTag("close", "gml", "MultiLineString", Null, True, True);
        return ($_str);
    }

    function toGML3()
    {
        global $depth;
        if ($this->srid) $srid = array("srsName" => $this->srid);
        else $srid = NULL;
        $_str = "";
        $_str .= $this->writeTag("open", "gml", "MultiLineString", $srid, True, True);
        $depth++;
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $_str .= $this->writeTag("open", "gml", "lineStringMember", Null, True, True);
            $depth++;
            $_str .= $this->convertLineStringGML3($this->shapeArray[$__i], FALSE);
            $depth--;
            $_str .= $this->writeTag("close", "gml", "lineStringMember", Null, True, True);
        }
        $depth--;
        $_str .= $this->writeTag("close", "gml", "MultiLineString", Null, True, True);
        return ($_str);
    }

    function toKML($extrude = 0, $tessellate = 1, $altitudeMode = "clampToGround")
    {
        global $depth;
        $_str .= $this->writeTag("open", null, "MultiGeometry", null, true, true);
        $depth++;
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $_str .= $this->convertLineStringToKml($this->shapeArray[$__i], $extrude, $tessellate, $altitudeMode);
        }
        $depth--;
        $_str .= $this->writeTag("close", null, "MultiGeometry", null, true, true);
        return ($_str);
    }
}

class multipolygon extends geometryfactory
{
    var $wkt;
    var $srid;
    var $shapeArray;
    var $geomType;
    var $isIsland;
    var $gml;

    function multipolygon($wkt, $srid)// constructor. wkt is set
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType[$count] = 'MULTIPOLYGON';
        $this->shapeArray = parent::deconstructionOfWKT($this->wkt);
    }

    function construction()// puts the deconstructed wkt together again and sets the wkt
    {
        $__newWkt = $this->geomType[$count] . "(";
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            switch ($this->isIsland[$__i])//check if a shape is an island of another
            {
                case false:
                    if ($this->isIsland[$__i + 1] == true)// what is the next one?
                        $__wktArray[$__i] = "((" . $this->shapeArray[$__i] . ")";
                    else
                        $__wktArray[$__i] = "((" . $this->shapeArray[$__i] . "))";
                    break;
                case true:
                    if ($this->isIsland[$__i + 1] == true)
                        $__wktArray[$__i] = "(" . $this->shapeArray[$__i] . ")";
                    else
                        $__wktArray[$__i] = "(" . $this->shapeArray[$__i] . "))";
                    break;
            }
        }
        $__newWkt = $__newWkt . implode(",", $__wktArray);
        $__newWkt = $__newWkt . ")";
        $this->wkt = $__newWkt;
    }

    function toGML()
    {
        global $depth;
        if ($this->srid) $srid = array("srsName" => $this->srid);
        else $srid = NULL;
        $_str = "";
        $_polys = array();
        $__i = 0;
        while ($this->shapeArray[$__i]) {
            if ($this->isIsland[$__i + 1]) {
                $_rings = array($this->shapeArray[$__i]);
                while ($this->isIsland[$__i + 1]) {
                    array_push($_rings, $this->shapeArray[$__i + 1]);
                    $__i++;
                }
                array_push($_polys, $_rings);
                $__i++;
            } else {
                array_push($_polys, array($this->shapeArray[$__i]));
                $__i++;
            }
        }
        $_str = $_str . $this->writeTag("open", "gml", "MultiPolygon", $srid, True, True);
        $depth++;
        foreach ($_polys as $__array) {
            $_str = $_str . $this->writeTag("open", "gml", "polygonMember", Null, True, True);
            $depth++;
            $_str = $_str . $this->convertPolygon($__array, FALSE);
            $depth--;
            $_str = $_str . $this->writeTag("close", "gml", "polygonMember", Null, True, True);
        }
        $depth--;
        $_str = $_str . $this->writeTag("close", "gml", "MultiPolygon", Null, True, True);
        return ($_str);
    }

    function toKML($extrude = 0, $tessellate = 1, $altitudeMode = "clampToGround")
    {
        global $depth;
        $_str = "";
        $_polys = array();
        $__i = 0;
        while ($this->shapeArray[$__i]) {
            if ($this->isIsland[$__i + 1]) {
                $_rings = array($this->shapeArray[$__i]);
                while ($this->isIsland[$__i + 1]) {
                    array_push($_rings, $this->shapeArray[$__i + 1]);
                    $__i++;
                }
                array_push($_polys, $_rings);
                $__i++;
            } else {
                array_push($_polys, array($this->shapeArray[$__i]));
                $__i++;
            }
        }
        $_str = $_str .= $this->writeTag("open", null, "MultiGeometry", null, true, true);
        $depth++;
        foreach ($_polys as $__array) {
            //$_str=$_str.$this->writeTag("open","gml","polygonMember",Null,True,True);
            //$depth++;
            $_str = $_str . $this->convertPolygonToKML($__array, $extrude, $tessellate, $altitudeMode);
            //$depth--;
            //$_str=$_str.$this->writeTag("close","gml","polygonMember",Null,True,True);
        }
        $depth--;
        $_str = $_str . $this->writeTag("close", NULL, "MultiGeometry", Null, True, True);
        return ($_str);
    }
}

class geometryCollection extends geometryfactory
{
    var $geometryArray;

    function geometryCollection($wktArray)
    {
        foreach ($wktArray as $__key => $__value) {
            $this->geometryArray[$__key] = parent::createGeometry($__value);
        }
    }

    function getVertices()
    {
        foreach ($this->geometryArray as $__geometry) {
            $__verticeStr = "";

            foreach ($__geometry->getShapeArray() as $__value) {
                $__verticeStr = $__verticeStr . $__value . ",";
            }
            $verticeStr = $verticeStr . $__verticeStr;
        }
        // remove the last comma
        $verticeStr = substr($verticeStr, 0, strlen($verticeStr) - 1);
        return ($verticeStr);
    }

    function getShapes()
    {
        $__shapeArray = array();
        foreach ($this->geometryArray as $__geometry) {


            foreach ($__geometry->getShapeArray() as $__value) {
                array_push($__shapeArray, $__value);
            }
        }
        $shapeArray = $__shapeArray;
        return ($shapeArray);
    }

    function getGeometryArray()
    {
        return ($this->geometryArray);
    }
}

class gmlConverter
{
    var $parser;
    var $geomType;
    var $wkt;
    var $isIsland;
    var $wktCoords;
    var $isPreviousIsland;
    var $splitTag;
    var $srid;
    var $axisOrder;

    function gmlConverter()
    {
        $this->xml();
    }

    function xml()
    {
        $this->parser = xml_parser_create();
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, "startElement", "endElement");
        xml_set_character_data_handler($this->parser, "characterData");
    }

    /**
     * @return array
     * @param string $gml
     * @param string $splitTag
     * @desc Enter description here...
     */
    function gmlToWKT($gml, $splitTag = array("FEATUREMEMBER"))
    {
        $gml = preg_replace("/^(?!urn:).+:/", "", $gml, 1); // This strips name spaces except urn:x-ogc:def:crs:epsg

        global $count;
        // Clean up messy gml. Remove spaces and tabs.
        //$gml= oneLineGML($gml);
        $this->splitTag = $splitTag;
        $count = 0;
        $currentTag = "";
        xml_parse($this->parser, $gml);
        // clean up
        xml_parser_free($this->parser);
        for ($__i = 0; $__i < sizeof($this->wktCoords); $__i++) {
            if ($this->geomType[$__i] == "MULTIPOINT" || $this->geomType[$__i] == "MULTIPOLYGON" || $this->geomType[$__i] == "MULTILINESTRING") {
                $this->wktCoords[$__i] = substr($this->wktCoords[$__i], 0, strlen($this->wktCoords[$__i]) - 1);
            }
            $this->wktCoords[$__i] = $this->geomType[$__i] . "(" . $this->wktCoords[$__i] . ")";
        }
        return (array($this->wktCoords, $this->srid));
    }

    function startElement($parser, $name, $attrs)
    {
        global $currentTag;    //used by function characterData when parsing xml data
        global $lastTag; // Last tag parsed
        global $tagFlag; // Flag which can be set to current tag
        global $count;

        $currentTag = $name;
        //echo $count;
        if ($attrs['SRSNAME'] != "") {
            //$this->srid[$count]=$this->parseEpsgCode($attrs['SRSNAME']);
            //$this->axisOrder = $this->getAxisOrderFromEpsg($attrs['SRSNAME']);
        }
        switch ($currentTag) {
            case "POINT" :
                $this->geomType[$count] = "POINT";
                break;
            case "LINESTRING" :
                $this->geomType[$count] = "LINESTRING";
                break;
            case "POLYGON" :
                $this->geomType[$count] = "POLYGON";
                break;
            case "MULTIPOINT" :
                $this->geomType[$count] = "MULTIPOINT";
                break;
            case "MULTILINESTRING" :
                $this->geomType[$count] = "MULTILINESTRING";
                break;
            case "MULTICURVE" : // GML3
                $this->geomType[$count] = "MULTILINESTRING";
                break;
            case "MULTIPOLYGON" :
                $this->geomType[$count] = "MULTIPOLYGON";
                break;
            case "MULTISURFACE" :
                $this->geomType[$count] = "MULTIPOLYGON";
                break;
            case "MULTIGEOMETRY" :
                $this->geomType[$count] = "MULTIGEOMETRY";
                break;
            case "POINTMEMBER":
                $this->wktCoords[$count] .= "(";
                $tagFlag = "POINTMEMBER";
                break;
            case "POINTMEMBERS": // ONLY TO DEFAET MAPINFO BUG! POINTMEMBERS (with 's') IS NOT VALID GML
                $this->wktCoords[$count] .= "(";
                $tagFlag = "POINTMEMBER";
                break;
            case "POLYGONMEMBER":
                $this->wktCoords[$count] .= "(";
                $tagFlag = "POLYGONMEMBER";
                break;
            case "SURFACEMEMBER":
                $this->wktCoords[$count] .= "(";
                $tagFlag = "POLYGONMEMBER";
                break;
            case "LINESTRINGMEMBER":
                $this->wktCoords[$count] .= "(";
                $tagFlag = "LINESTRINGMEMBER";
                break;
            case "CURVEMEMBER": // GML3
                $this->wktCoords[$count] .= "(";
                $tagFlag = "LINESTRINGMEMBER";
                break;
            case "INNERBOUNDARYIS":
                $this->isIsland = true;
                $tagFlag = "INNERBOUNDARYIS";
                break;
            case "INTERIOR":
                $this->isIsland = true;
                $tagFlag = "INNERBOUNDARYIS";
                break;
            case "OUTERBOUNDARYIS":
                $this->isIsland = false;
                break;
            case "EXTERIOR":
                $this->isIsland = false;
                break;
            case "XML_SERIALIZER_TAG":
                if (($tagFlag == "POLYGONMEMBER" ||
                        $tagFlag == "LINESTRINGMEMBER" ||
                        $tagFlag == "CURVEMEMBER" ||
                        $tagFlag == "SURFACEMEMBER" ||
                        $tagFlag == "POINTMEMBER")
                    &&
                    ($lastTag != "POLYGONMEMBER" &&
                        $lastTag != "LINESTRINGMEMBER" &&
                        $lastTag != "CURVEMEMBER" &&
                        $lastTag != "SURFACEMEMBER" &&
                        $lastTag != "POINTMEMBER" &&
                        $lastTag != "POINTMEMBERS")) { // ONLY TO DEFAET MAPINFO BUG! POINTMEMBERS (with s) IS NOT VALID GML
                    $this->wktCoords[$count] .= "(";
                }
                break;

        }
        $lastTag = $currentTag;
    }

    function endElement($parser, $name)
    {
        global $concatCoords;
        global $currentTag;
        global $lastTag;
        global $tagFlag;
        global $count;

        $currentTag = $name;
        //echo $currentTag."\n";
        //
        switch ($currentTag) {
            case "INNERBOUNDARYIS": // Flag set back to POLYGONMEMBER
                $tagFlag = "POLYGONMEMBER";
                break;

            case "INTERIOR": // Flag set back to POLYGONMEMBER
                $tagFlag = "POLYGONMEMBER";
                break;

            case "POLYGONMEMBER":
                if ($lastTag != "XML_SERIALIZER_TAG") {
                    $this->wktCoords[$count] .= "),";
                }
                $tagFlag = "";
                break;

            case "SURFACEMEMBER":
                if ($lastTag != "XML_SERIALIZER_TAG") {
                    $this->wktCoords[$count] .= "),";
                }
                $tagFlag = "";
                break;

            case "LINESTRINGMEMBER":
                if ($lastTag != "XML_SERIALIZER_TAG") {
                    $this->wktCoords[$count] .= "),";
                }
                $tagFlag = "";
                break;
            case "CURVEMEMBER": // GML3
                if ($lastTag != "XML_SERIALIZER_TAG") {
                    $this->wktCoords[$count] .= "),";
                }
                $tagFlag = "";
                break;
            case "POINTMEMBER":
                if ($lastTag != "XML_SERIALIZER_TAG") {
                    $this->wktCoords[$count] .= "),";
                }
                $tagFlag = "";
                break;
            case "POINTMEMBERS": // ONLY TO DEFAET MAPINFO BUG! POINTMEMBERS (with s) IS NOT VALID GML
                if ($lastTag != "XML_SERIALIZER_TAG") {
                    $this->wktCoords[$count] .= "),";
                }
                $tagFlag = "";
                break;
            //Read the last tag and set the main feature geometry type.
            case "POINT" :
                $this->geomType[$count] = "POINT";
                break;
            case "LINESTRING" :
                $this->geomType[$count] = "LINESTRING";
                break;
            case "POLYGON" :
                $this->geomType[$count] = "POLYGON";
                break;
            case "MULTIPOINT" :
                $this->geomType[$count] = "MULTIPOINT";
                break;
            case "MULTILINESTRING" :
                $this->geomType[$count] = "MULTILINESTRING";
                break;
            case "MULTICURVE" : // GML3
                $this->geomType[$count] = "MULTILINESTRING";
                break;
            case "MULTIPOLYGON" :
                $this->geomType[$count] = "MULTIPOLYGON";
                break;
            case "MULTISURFACE" :
                $this->geomType[$count] = "MULTIPOLYGON";
                break;
            case "MULTIGEOMETRY" :
                $this->geomType[$count] = "MULTIGEOMETRY";
                break;
            //case $this->splitTag :
            //$count++;
            //break;
            case "COORDINATES":

                if ($this->geomType[$count] == "POINT") {
                    $this->wktCoords[$count] .= $this->convertCoordinatesToWKT($concatCoords);
                } else if ($this->geomType[$count] == "LINESTRING") {
                    $this->wktCoords[$count] .= $this->convertCoordinatesToWKT($concatCoords);
                } else if ($this->geomType[$count] == "POLYGON") {
                    if ($this->isIsland == true) $this->wktCoords[$count] .= ",";
                    $this->wktCoords[$count] .= "(" . $this->convertCoordinatesToWKT($concatCoords) . ")";
                }
                //echo "test: ".($concatCoords)."\n";
                $concatCoords = "";
                break;
            case "POSLIST": //GML3 Hvis epsg kode i tag, skal den være _CONTENT

                if ($this->geomType[$count] == "POINT") {
                    $this->wktCoords[$count] .= $this->convertPostListToWKT($concatCoords);
                } else if ($this->geomType[$count] == "LINESTRING") {
                    $this->wktCoords[$count] .= $this->convertPostListToWKT($concatCoords);
                } else if ($this->geomType[$count] == "POLYGON") {
                    if ($this->isIsland == true) $this->wktCoords[$count] .= ",";
                    $this->wktCoords[$count] .= "(" . $this->convertPostListToWKT($concatCoords) . ")";
                }
                //echo "test: ".($concatCoords)."\n";
                $concatCoords = "";
                break;
            case "POS": //GML3 Hvis epsg kode i tag, skal den være _CONTENT
                $this->wktCoords[$count] .= $this->convertPostListToWKT($concatCoords);
                $concatCoords = "";
                break;
            case "XML_SERIALIZER_TAG":
                if ($lastTag == "POLYGON" || $lastTag == "LINESTRING" || $lastTag == "POINT") {
                    $this->wktCoords[$count] .= "),";
                }
                break;
        }
        if (in_array(strtoupper($currentTag), $this->splitTag)) {
            $count++;
        }
        $lastTag = $currentTag;
        $currentTag = null;
    }

    function characterData($parser, $data)
    {
        global $concatCoords;
        global $currentTag;
        global $count;
        switch ($currentTag) {
            case "COORDINATES" :
                $concatCoords .= $data; // concat the data in case of the 1024 char limit is exceeded
                break;
            case "POSLIST" : //GML3 Hvis epsg kode i tag, skal den være _CONTENT
                $concatCoords .= $data; // concat the data in case of the 1024 char limit is exceeded
                break;
            case "POS" : //GML3 Hvis epsg kode i tag, skal den være _CONTENT
                $concatCoords .= $data; // concat the data in case of the 1024 char limit is exceeded
                break;
            case "PROPERTYNAME";
                $this->filterPropertyName[$data] = $count;
                break;
            case "SRSNAME"; // not normal. Used when serializing array to xml
                $this->srid[$count] = $this->parseEpsgCode($data);
                $this->axisOrder[$count] = $this->getAxisOrderFromEpsg($data);
                break;
        }
    }

    function convertCoordinatesToWKT($_str)
    {
        global $count;
        ob_start();
        print_r($this->axisOrder);
        $data = ob_get_clean();
        $_str = str_replace(" ", "&", $_str);
        $_str = str_replace(",", " ", $_str);
        $_str = str_replace("&", ",", $_str);
        // If urn EPSG reverse the axixOrder
        if ($this->axisOrder[$count] == "latitude") {
            $split = explode(",", $_str);
            foreach ($split as $value) {
                $splitCoord = explode(" ", $value);
                $reversedArr[] = $splitCoord[1] . " " . $splitCoord[0];
            }
            $_str = implode(",", $reversedArr);

        }
        return ($_str);
    }

    function convertPostListToWKT($_str)
    {
        global $count;
        ob_start();
        print_r($this->axisOrder);
        $data = ob_get_clean();
        $arr = explode(" ", trim($_str));
        $i = 1;
        foreach ($arr as $value) {
            $newStr .= $value;
            if (is_int($i / 2)) {
                $newStr .= ",";
            } else {
                $newStr .= " ";
            }
            $i++;
        }
        $newStr = substr($newStr, 0, strlen($newStr) - 1);
        // If urn EPSG reverse the axixOrder
        if ($this->axisOrder[$count] == "latitude") {
            $split = explode(",", $newStr);
            foreach ($split as $value) {
                $splitCoord = explode(" ", $value);
                $reversedArr[] = $splitCoord[1] . " " . $splitCoord[0];
            }
            $newStr = implode(",", $reversedArr);

        }
        return ($newStr);
    }

    function parseEpsgCode($epsg)
    {
        //if (strtoupper(substr($epsg, 0, 5)=="EPSG:")) $epsg=substr($epsg, 5,strlen($epsg));
        //preg_match_all("/[0-9]*$/",$epsg,$arr);
        //$clean=$arr[0][0];

        $split = explode(":", $epsg);
        ob_start();
        print_r($split);
        $data = ob_get_clean();
        $clean = end($split);
        $clean = preg_replace("/[\w]\./", "", $clean);
        return $clean;
    }

    function getAxisOrderFromEpsg($epsg)
    {
        $split = explode(":", $epsg);
        if ($split[0] == "urn") {
            $first = "latitude";
        } else {
            $first = "longitude";
        }

        return ($first);
    }

    function oneLineXML($gml)
    {
        $gml = ereg_replace("\t", " ", $gml);
        $gml = ereg_replace("\r", " ", $gml);
        $gml = ereg_replace("\n", " ", $gml);
        $gml = ereg_replace(">[[:space:]]+", ">", $gml);
        $gml = ereg_replace("[[:space:]]+<", "<", $gml);
        return ($gml);
    }
}
