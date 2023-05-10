<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\libs;

use Exception;


/**
 * Class GeometryFactory
 * @package app\libs
 */
class GeometryFactory
{
    /**
     * @var string
     */
    public $srid;

    /**
     * @var string
     */
    public $wkt;

    /**
     * @var array<bool>
     */
    public $isIsland;

    /**
     * @var array<string>
     */
    public $shapeArray;

    /**
     * @var string
     */
    public $geomType;

    function __construct()
    {
    }

    /**
     * @param string $wkt test
     * @param string|null $srid
     * @return Point|LineString|MultiLineString|MultiPoint|MultiPolygon|Polygon|null
     * @desc Creates a new geometry object from a wkt string
     */
    function createGeometry(string $wkt, string $srid = null): ?object //creates a new geometry object. Factory function
    {
        $wkt = str_replace(", ", ",", $wkt);// replace " ," with ","
        preg_match_all("/[a-z]*[A-Z]*/", $wkt, $__typeArray);//Match the type of the geometry
        $__type = $__typeArray[0][0];
        switch ($__type) {
            case "MULTIPOLYGON":
                $geometryObject = new MultiPolygon($wkt, $srid);
                break;
            case "MULTILINESTRING":
                $geometryObject = new MultiLineString($wkt, $srid);
                break;
            case "MULTIPOINT":
                $geometryObject = new MultiPoint($wkt, $srid);
                break;
            case "POINT":
                $geometryObject = new Point($wkt, $srid); //point is a key word
                break;
            case "LINESTRING":
                $geometryObject = new LineString($wkt, $srid);
                break;
            case "POLYGON":
                $geometryObject = new Polygon($wkt, $srid);
                break;
            default:
                $geometryObject = null;
                break;
        }
        return ($geometryObject);
    }

    /**
     * Enter description here...
     *
     * @param array<string> $wktArray
     * @return GeometryCollection
     * @throws Exception
     */
    function createGeometryCollection(array $wktArray): GeometryCollection
    {
        return new GeometryCollection($wktArray);
    }

    /**
     * Take a WKT string and returns a array with coords(string) for shapes. Called from a child object
     * @return array<string>
     */
    function deconstructionOfWKT(): array
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
        return ($wktArray);
    }

    /**
     * @return string
     */
    function getWKT(): string
    {
        return $this->wkt;
    }

    /**
     * @return string
     */
    function getGeomType(): string
    {
        return $this->geomType;
    }

    /**
     * @param string $type
     * @param string|null $ns
     * @param string|null $tag
     * @param array<string>|null $atts
     * @param bool|null $ind
     * @param bool|null $n
     * @return string
     */
    function writeTag(string $type, ?string $ns, ?string $tag, ?array $atts, ?bool $ind, ?bool $n): string
    {
        $str = "";
        global $depth;
        if ($ind) {
            for ($i = 0; $i < $depth; $i++) {
                $str = $str . "  ";
            }
        }
        if ($ns != null) {
            $tag = $ns . ":" . $tag;
        }
        $str .= "<";
        if ($type == "close") {
            $str = $str . "/";
        }
        $str = $str . $tag;
        if (!empty($atts)) {
            foreach ($atts as $key => $value) {
                $str = $str . ' ' . $key . '="' . $value . '"';
            }
        }
        if ($type == "selfclose") {
            $str = $str . "/";
        }
        $str = $str . ">";
        if ($n) {
            $str = $str . "\n";
        }
        return ($str);
    }

    /**
     * @param string $geom
     * @param bool $hasSrid
     * @return string
     */
    function convertPoint(string $geom, bool $hasSrid = true): string
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

    /**
     * @param string $geom
     * @param bool $hasSrid
     * @return string
     */
    function convertLineString(string $geom, $hasSrid = true): string
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

    /**
     * @param string $geom
     * @param bool $hasSrid
     * @return string
     */
    function convertLineStringToGML3(string $geom, bool $hasSrid = true): string
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
     * @param array<string> $rings
     * @param bool $hasSrid
     * @return string
     */
    function convertPolygon(array $rings, bool $hasSrid = true): string
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

    /**
     * @param string $_str
     * @return string
     */
    function convertCoordinatesToGML(string $_str): string
    {
        $_str = str_replace(" ", "&", $_str);
        $_str = str_replace(",", " ", $_str);
        $_str = str_replace("&", ",", $_str);
        $_str = str_replace("(", "", $_str);
        $_str = str_replace(")", "", $_str);
        return $_str;
    }

    /**
     * @param string $_str
     * @return string
     */
    function convertCoordinatesToGML3(string $_str): string
    {
        $_str = str_replace(",", " ", $_str);
        return ($_str);
    }
}

class Point extends GeometryFactory
{

    function __construct(string $wkt, string $srid)// constructor. wkt is set
    {
        parent::__construct();
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = "POINT";
        $this->shapeArray = parent::deconstructionOfWKT();

    }

    /**
     *
     */
    function construction(): void// puts the deconstructed wkt together again and sets the wkt
    {
        $__newWkt = $this->geomType . "(" . $this->shapeArray[0] . ")";
        $this->wkt = $__newWkt;
    }

    /**
     * @return string
     */
    function getAsMulti(): string// return wkt as multi feature
    {
        $wkt = "MULTI" . $this->geomType . "(";
        $wkt = $wkt . "(" . $this->shapeArray[0] . ")";
        $wkt = $wkt . ")";
        return $wkt;
    }

    /**
     * @return string
     */
    function toGML(): string
    {
        $_str = "";
        $_str .= $this->convertPoint($this->shapeArray[0]);
        return $_str;
    }
}

class LineString extends GeometryFactory
{
    /**
     * LineString constructor.
     * @param string $wkt
     * @param string $srid
     */
    function __construct(string $wkt, string $srid)
    {
        parent::__construct();
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = 'LINESTRING';
        $this->shapeArray = parent::deconstructionOfWKT();

    }

    function construction(): void
    {
        $__newWkt = $this->geomType . "(" . $this->shapeArray[0] . ")";
        $this->wkt = $__newWkt;
    }

    /**
     * @return string
     */
    function getAsMulti(): string
    {
        $wkt = "MULTI" . $this->geomType . "(";
        $wkt = $wkt . "(" . $this->shapeArray[0] . ")";
        $wkt = $wkt . ")";
        return $wkt;
    }

    /**
     * @return string
     */
    function toGML(): string
    {
        $_str = "";
        $_str .= $this->convertLineString($this->shapeArray[0]);
        return $_str;
    }
}

class Polygon extends GeometryFactory
{
    /**
     * polygon constructor.
     * @param string $wkt
     * @param string $srid
     */
    function __construct(string $wkt, string $srid)// constructor. wkt is set
    {
        parent::__construct();
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = 'POLYGON';
        $this->shapeArray = parent::deconstructionOfWKT();

    }

    /**
     *
     */
    function construction(): void// puts the deconstructed wkt together again and sets the wkt
    {
        $__wktArray = [];
        $__newWkt = $this->geomType . "(";
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $__wktArray[$__i] = "(" . $this->shapeArray[$__i] . ")";
        }
        $__newWkt = $__newWkt . implode(",", $__wktArray);
        $__newWkt = $__newWkt . ")";
        $this->wkt = $__newWkt;
    }

    /**
     * @return string
     */
    function getAsMulti(): string
    {
        $wktArray = [];
        $wkt = "MULTI" . $this->geomType . "(";
        for ($i = 0; $i < (sizeof($this->shapeArray)); $i++) {
            $wktArray[$i] = "((" . $this->shapeArray[$i] . "))";
        }
        $wkt = $wkt . implode(",", $wktArray);
        $wkt = $wkt . ")";
        return $wkt;
    }

    /**
     * @return string
     */
    function toGML(): string
    {
        $_str = "";
        $_str .= $this->convertPolygon($this->shapeArray);
        return $_str;
    }
}

class MultiPoint extends GeometryFactory
{
    /**
     * multipoint constructor.
     * @param string $wkt
     * @param string $srid
     */
    function __construct(string $wkt, string $srid)// constructor. wkt is set
    {
        parent::__construct();
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = 'MULTIPOINT';
        $this->shapeArray = parent::deconstructionOfWKT();

    }

    function construction(): void
    {
        $__wktArray = [];
        $__newWkt = $this->geomType . "(";
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $__wktArray[$__i] = $this->shapeArray[$__i];
        }
        $__newWkt = $__newWkt . implode(",", $__wktArray);
        $__newWkt = $__newWkt . ")";
        $this->wkt = $__newWkt;
    }

    /**
     * @return string
     */
    function toGML(): string
    {
        global $depth;
        if ($this->srid) {
            $srid = array("srsName" => $this->srid);
        } else {
            $srid = null;
        }
        $str = "";
        $str .= $this->writeTag("open", "gml", "MultiPoint", $srid, True, True);
        $depth++;
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $str .= $this->writeTag("open", "gml", "pointMember", Null, True, True);
            $depth++;
            $str .= $this->convertPoint($this->shapeArray[$__i], FALSE);
            $depth--;
            $str .= $this->writeTag("close", "gml", "pointMember", Null, True, True);
        }
        $depth--;
        $str .= $this->writeTag("close", "gml", "MultiPoint", Null, True, True);
        return $str;
    }
}

class MultiLineString extends GeometryFactory
{

    /**
     * MultiLineString constructor.
     * @param string $wkt
     * @param string $srid
     */
    function __construct(string $wkt, string $srid)
    {
        parent::__construct();
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = 'MULTILINESTRING';
        $this->shapeArray = parent::deconstructionOfWKT();
    }

    /**
     *
     */
    function construction(): void
    {
        $__wktArray = [];
        $wkt = $this->geomType . "(";
        for ($i = 0; $i < (sizeof($this->shapeArray)); $i++) {
            $__wktArray[$i] = "(" . $this->shapeArray[$i] . ")";
        }
        $wkt = $wkt . implode(",", $__wktArray);
        $wkt = $wkt . ")";
        $this->wkt = $wkt;
    }

    /**
     * @return string
     */
    function toGML(): string
    {
        global $depth;
        if ($this->srid) {
            $srid = array("srsName" => $this->srid);
        } else $srid = NULL;
        $str = "";
        $str .= $this->writeTag("open", "gml", "MultiLineString", $srid, True, True);
        $depth++;
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $str .= $this->writeTag("open", "gml", "lineStringMember", Null, True, True);
            $depth++;
            $str .= $this->convertLineString($this->shapeArray[$__i], FALSE);
            $depth--;
            $str .= $this->writeTag("close", "gml", "lineStringMember", Null, True, True);
        }
        $depth--;
        $str .= $this->writeTag("close", "gml", "MultiLineString", Null, True, True);
        return $str;
    }

    /**
     * @return string
     */
    function toGML3(): string
    {
        global $depth;
        if ($this->srid) {
            $srid = array("srsName" => $this->srid);
        } else $srid = NULL;
        $str = "";
        $str .= $this->writeTag("open", "gml", "MultiLineString", $srid, True, True);
        $depth++;
        for ($i = 0; $i < (sizeof($this->shapeArray)); $i++) {
            $str .= $this->writeTag("open", "gml", "lineStringMember", Null, True, True);
            $depth++;
            $str .= $this->convertLineStringToGML3($this->shapeArray[$i], FALSE);
            $depth--;
            $str .= $this->writeTag("close", "gml", "lineStringMember", Null, True, True);
        }
        $depth--;
        $str .= $this->writeTag("close", "gml", "MultiLineString", Null, True, True);
        return $str;
    }
}

class MultiPolygon extends GeometryFactory
{

    /**
     * MultiPolygon constructor.
     * @param string $wkt
     * @param string $srid
     */
    function __construct(string $wkt, string $srid)
    {
        parent::__construct();
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = 'MULTIPOLYGON';
        $this->shapeArray = parent::deconstructionOfWKT();
    }

    /**
     *
     */
    function construction(): void
    {
        $__wktArray = [];
        $__newWkt = $this->geomType . "(";
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

    /**
     * @return string
     */
    function toGML(): string
    {
        global $depth;
        if ($this->srid) $srid = array("srsName" => $this->srid);
        else $srid = NULL;
        $str = "";
        $polys = array();
        $i = 0;
        while ($this->shapeArray[$i]) {
            if ($this->isIsland[$i + 1]) {
                $_rings = array($this->shapeArray[$i]);
                while ($this->isIsland[$i + 1]) {
                    array_push($_rings, $this->shapeArray[$i + 1]);
                    $i++;
                }
                array_push($polys, $_rings);
                $i++;
            } else {
                array_push($polys, array($this->shapeArray[$i]));
                $i++;
            }
        }
        $str = $str . $this->writeTag("open", "gml", "MultiPolygon", $srid, True, True);
        $depth++;
        foreach ($polys as $__array) {
            $str = $str . $this->writeTag("open", "gml", "polygonMember", Null, True, True);
            $depth++;
            $str = $str . $this->convertPolygon($__array, FALSE);
            $depth--;
            $str = $str . $this->writeTag("close", "gml", "polygonMember", Null, True, True);
        }
        $depth--;
        $str = $str . $this->writeTag("close", "gml", "MultiPolygon", Null, True, True);
        return $str;
    }
}

class GeometryCollection extends GeometryFactory
{
    /**
     * @var array<mixed>
     */
    public $geometryArray;

    /**
     * GeometryCollection constructor.
     * @param array<mixed> $wktArray
     */
    function __construct(array $wktArray)
    {
        parent::__construct();
        foreach ($wktArray as $key => $value) {
            $this->geometryArray[$key] = parent::createGeometry($value);
        }
    }

    /**
     * @return array<mixed>
     */
    function getGeometryArray(): array
    {
        return $this->geometryArray;
    }
}

class gmlConverter
{
    /**
     * @var resource
     */
    public $parser;

    /**
     * @var string
     */
    public $geomType;

    /**
     * @var string
     */
    public $wkt;

    /**
     * @var bool
     */
    public $isIsland;

    /**
     * @var array<string>
     */
    public $wktCoords;

    /**
     * @var bool
     */
    public $isPreviousIsland;

    /**
     * @var array|string[]|null
     */
    public $splitTag;

    /**
     * @var string
     */
    public $srid;

    /**
     * @var string
     */
    public $axisOrder;

    /**
     * gmlConverter constructor.
     */
    function __construct()
    {
        $this->parser = xml_parser_create();
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, "startElement", "endElement");
        xml_set_character_data_handler($this->parser, "characterData");
    }

    /**
     * @param string $gml
     * @param array|string[]|null $splitTag
     * @return array<mixed>
     */
    public function gmlToWKT(string $gml, ?array $splitTag = array("FEATUREMEMBER")): array
    {
        $gml = preg_replace("/[\w-]*:(?![\w-]*:)/", "", $gml); // This strips name spaces except urn:x-ogc:def:crs:epsg
        global $count;
        $this->splitTag = $splitTag;
        $count = 0;
        xml_parse($this->parser, $gml);
        xml_parser_free($this->parser);
        for ($__i = 0; $__i < sizeof($this->wktCoords); $__i++) {
            if ($this->geomType[$__i] == "MULTIPOINT" || $this->geomType[$__i] == "MULTIPOLYGON" || $this->geomType[$__i] == "MULTILINESTRING") {
                $this->wktCoords[$__i] = substr($this->wktCoords[$__i], 0, strlen($this->wktCoords[$__i]) - 1);
            }
            $this->wktCoords[$__i] = $this->geomType[$__i] . "(" . $this->wktCoords[$__i] . ")";
        }
        return array($this->wktCoords, $this->srid);
    }

    /**
     * @param resource $parser
     * @param string $name
     * @param array<string> $attrs
     */
    private function startElement($parser, string $name, array $attrs): void
    {
        global $currentTag;    //used by function characterData when parsing xml data
        global $lastTag; // Last tag parsed
        global $tagFlag; // Flag which can be set to current tag
        global $count;

        $currentTag = $name;
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
            case "MULTISURFACE":
            case "MULTIPOLYGON" :
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
            case "SURFACEMEMBER":
            case "POLYGONMEMBER":
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
            case "INTERIOR":
            case "INNERBOUNDARYIS":
                $this->isIsland = true;
                $tagFlag = "INNERBOUNDARYIS";
                break;
            case "EXTERIOR":
            case "OUTERBOUNDARYIS":
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

    /**
     * @param resource $parser
     * @param string $name
     */
    private function endElement($parser, string $name): void
    {
        global $concatCoords;
        global $currentTag;
        global $lastTag;
        global $tagFlag;
        global $count;

        $currentTag = $name;
        switch ($currentTag) {
            case "INTERIOR":
            case "INNERBOUNDARYIS": // Flag set back to POLYGONMEMBER
                $tagFlag = "POLYGONMEMBER";
                break;
            case "SURFACEMEMBER":
            case "LINESTRINGMEMBER":
            case "POINTMEMBER":
            case "POLYGONMEMBER":
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
            case "MULTISURFACE":
            case "MULTIPOLYGON" :
                $this->geomType[$count] = "MULTIPOLYGON";
                break;
            case "MULTIGEOMETRY" :
                $this->geomType[$count] = "MULTIGEOMETRY";
                break;
            case "COORDINATES":
                if ($this->geomType[$count] == "POINT") {
                    $this->wktCoords[$count] .= $this->convertCoordinatesToWKT($concatCoords);
                } else if ($this->geomType[$count] == "LINESTRING") {
                    $this->wktCoords[$count] .= $this->convertCoordinatesToWKT($concatCoords);
                } else if ($this->geomType[$count] == "POLYGON") {
                    if ($this->isIsland == true) $this->wktCoords[$count] .= ",";
                    $this->wktCoords[$count] .= "(" . $this->convertCoordinatesToWKT($concatCoords) . ")";
                }
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

    /**
     * @param resource $parser
     * @param string $data
     */
    private function characterData($parser, string $data): void
    {
        global $concatCoords;
        global $currentTag;
        global $count;
        switch ($currentTag) {
            case "COORDINATES" :
                $concatCoords .= $data; // concat the data in case of the 1024 char limit is exceeded
                break;
            case "POS":
            case "POSLIST" : //GML3 Hvis epsg kode i tag, skal den være _CONTENT
                $concatCoords .= $data; // concat the data in case of the 1024 char limit is exceeded
                break;
            case "PROPERTYNAME";
                break;
            case "SRSNAME"; // not normal. Used when serializing array to xml
                $this->srid[$count] = self::parseEpsgCode($data);
                $this->axisOrder[$count] = self::getAxisOrderFromEpsg($data);
                break;
        }
    }

    /**
     * @param string $_str
     * @return string
     */
    private function convertCoordinatesToWKT(string $_str): string
    {
        global $count;
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

    /**
     * @param string $_str
     * @return string
     */
    private function convertPostListToWKT(string $_str): string
    {
        global $count;
        $arr = explode(" ", trim($_str));
        $i = 1;
        $newStr = "";
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

    /**
     * @param string $epsg
     * @return string
     */
    public static function parseEpsgCode(string $epsg): string
    {
        $split = explode(":", $epsg);
        $clean = end($split);
        $clean = preg_replace("/[\w]\./", "", $clean);
        return $clean;
    }

    /**
     * @param string $epsg
     * @return string
     */
    public static function getAxisOrderFromEpsg(string $epsg): string
    {
        $split = explode(":", $epsg);
        if ($split[0] == "urn") {
            $first = "latitude";
        } else {
            $first = "longitude";
        }

        return $first;
    }
}
