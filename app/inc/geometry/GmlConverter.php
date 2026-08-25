<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Autoloadable, worker-safe port of gmlConverter from the legacy
 * app/libs/phpgeometry_class.php: the parser state that used to live in
 * file-scope globals ($currentTag, $lastTag, $tagFlag, $count, $concatCoords)
 * is instance state, reset on every gmlToWKT() call, so nothing leaks between
 * requests under a persistent SAPI (FrankenPHP worker mode). The XML parser is
 * created per call, so an instance is reusable.
 */

namespace app\inc\geometry;

/**
 * Converts GML (2 and 3) geometry to WKT.
 */
class GmlConverter
{
    /** @var array<int, string> */
    public array $wktCoords = [];
    /** @var array<int, string> */
    public array $geomType = [];
    /** @var array<int, string> */
    public array $srid = [];
    /** @var array<int, string> */
    public array $axisOrder = [];

    /** @var array<string> */
    private array $splitTag = [];
    private int $count = 0;
    private ?string $currentTag = null;
    private ?string $lastTag = null;
    private ?string $tagFlag = null;
    private string $concatCoords = '';
    private bool $isIsland = false;

    /**
     * @param array<string>|null $splitTag Tag(s) that separate one geometry from the next
     * @return array{0: array<int, string>, 1: array<int, string>} [wkt strings, srids]
     */
    public function gmlToWKT(string $gml, ?array $splitTag = ["FEATUREMEMBER"]): array
    {
        $gml = preg_replace("/[\w-]*:(?![\w-]*:)/", "", $gml); // This strips name spaces except urn:x-ogc:def:crs:epsg

        // Reset all parser state so the instance (and the request under a
        // persistent SAPI) starts from scratch.
        $this->splitTag = $splitTag ?? [];
        $this->count = 0;
        $this->currentTag = null;
        $this->lastTag = null;
        $this->tagFlag = null;
        $this->concatCoords = '';
        $this->isIsland = false;
        $this->wktCoords = [];
        $this->geomType = [];
        $this->srid = [];
        $this->axisOrder = [];

        $parser = xml_parser_create();
        xml_set_element_handler($parser, $this->startElement(...), $this->endElement(...));
        xml_set_character_data_handler($parser, $this->characterData(...));
        xml_parse($parser, $gml);
        xml_parser_free($parser);

        for ($__i = 0; $__i < sizeof($this->wktCoords); $__i++) {
            $type = $this->geomType[$__i] ?? '';
            if ($type == "MULTIPOINT" || $type == "MULTIPOLYGON" || $type == "MULTILINESTRING") {
                $this->wktCoords[$__i] = substr($this->wktCoords[$__i], 0, strlen($this->wktCoords[$__i]) - 1);
            }
            $this->wktCoords[$__i] = $type . "(" . $this->wktCoords[$__i] . ")";
        }
        return [$this->wktCoords, $this->srid];
    }

    private function appendCoords(string $str): void
    {
        $this->wktCoords[$this->count] = ($this->wktCoords[$this->count] ?? '') . $str;
    }

    /**
     * @param \XMLParser $parser
     * @param array<string, string> $attrs
     */
    private function startElement($parser, string $name, array $attrs): void
    {
        $this->currentTag = $name;
        // Note: srsName ATTRIBUTES are deliberately not captured (legacy behavior) —
        // only an srsName ELEMENT sets srid/axisOrder, see characterData().
        switch ($this->currentTag) {
            case "POINT" :
                $this->geomType[$this->count] = "POINT";
                break;
            case "LINESTRING" :
                $this->geomType[$this->count] = "LINESTRING";
                break;
            case "POLYGON" :
                $this->geomType[$this->count] = "POLYGON";
                break;
            case "MULTIPOINT" :
                $this->geomType[$this->count] = "MULTIPOINT";
                break;
            case "MULTILINESTRING" :
                $this->geomType[$this->count] = "MULTILINESTRING";
                break;
            case "MULTICURVE" : // GML3
                $this->geomType[$this->count] = "MULTILINESTRING";
                break;
            case "MULTISURFACE":
            case "MULTIPOLYGON" :
                $this->geomType[$this->count] = "MULTIPOLYGON";
                break;
            case "MULTIGEOMETRY" :
                $this->geomType[$this->count] = "MULTIGEOMETRY";
                break;
            case "POINTMEMBER":
                $this->appendCoords("(");
                $this->tagFlag = "POINTMEMBER";
                break;
            case "POINTMEMBERS": // ONLY TO DEFAET MAPINFO BUG! POINTMEMBERS (with 's') IS NOT VALID GML
                $this->appendCoords("(");
                $this->tagFlag = "POINTMEMBER";
                break;
            case "SURFACEMEMBER":
            case "POLYGONMEMBER":
                $this->appendCoords("(");
                $this->tagFlag = "POLYGONMEMBER";
                break;
            case "LINESTRINGMEMBER":
                $this->appendCoords("(");
                $this->tagFlag = "LINESTRINGMEMBER";
                break;
            case "CURVEMEMBER": // GML3
                $this->appendCoords("(");
                $this->tagFlag = "LINESTRINGMEMBER";
                break;
            case "INTERIOR":
            case "INNERBOUNDARYIS":
                $this->isIsland = true;
                $this->tagFlag = "INNERBOUNDARYIS";
                break;
            case "EXTERIOR":
            case "OUTERBOUNDARYIS":
                $this->isIsland = false;
                break;
            case "XML_SERIALIZER_TAG":
                if (($this->tagFlag == "POLYGONMEMBER" ||
                        $this->tagFlag == "LINESTRINGMEMBER" ||
                        $this->tagFlag == "CURVEMEMBER" ||
                        $this->tagFlag == "SURFACEMEMBER" ||
                        $this->tagFlag == "POINTMEMBER")
                    &&
                    ($this->lastTag != "POLYGONMEMBER" &&
                        $this->lastTag != "LINESTRINGMEMBER" &&
                        $this->lastTag != "CURVEMEMBER" &&
                        $this->lastTag != "SURFACEMEMBER" &&
                        $this->lastTag != "POINTMEMBER" &&
                        $this->lastTag != "POINTMEMBERS")) { // ONLY TO DEFAET MAPINFO BUG! POINTMEMBERS (with s) IS NOT VALID GML
                    $this->appendCoords("(");
                }
                break;
        }
        $this->lastTag = $this->currentTag;
    }

    /**
     * @param \XMLParser $parser
     */
    private function endElement($parser, string $name): void
    {
        $this->currentTag = $name;
        switch ($this->currentTag) {
            case "INTERIOR":
            case "INNERBOUNDARYIS": // Flag set back to POLYGONMEMBER
                $this->tagFlag = "POLYGONMEMBER";
                break;
            case "SURFACEMEMBER":
            case "LINESTRINGMEMBER":
            case "POINTMEMBER":
            case "POLYGONMEMBER":
                if ($this->lastTag != "XML_SERIALIZER_TAG") {
                    $this->appendCoords("),");
                }
                $this->tagFlag = "";
                break;
            case "CURVEMEMBER": // GML3
                if ($this->lastTag != "XML_SERIALIZER_TAG") {
                    $this->appendCoords("),");
                }
                $this->tagFlag = "";
                break;
            case "POINTMEMBERS": // ONLY TO DEFAET MAPINFO BUG! POINTMEMBERS (with s) IS NOT VALID GML
                if ($this->lastTag != "XML_SERIALIZER_TAG") {
                    $this->appendCoords("),");
                }
                $this->tagFlag = "";
                break;
            // Read the last tag and set the main feature geometry type.
            case "POINT" :
                $this->geomType[$this->count] = "POINT";
                break;
            case "LINESTRING" :
                $this->geomType[$this->count] = "LINESTRING";
                break;
            case "POLYGON" :
                $this->geomType[$this->count] = "POLYGON";
                break;
            case "MULTIPOINT" :
                $this->geomType[$this->count] = "MULTIPOINT";
                break;
            case "MULTILINESTRING" :
                $this->geomType[$this->count] = "MULTILINESTRING";
                break;
            case "MULTICURVE" : // GML3
                $this->geomType[$this->count] = "MULTILINESTRING";
                break;
            case "MULTISURFACE":
            case "MULTIPOLYGON" :
                $this->geomType[$this->count] = "MULTIPOLYGON";
                break;
            case "MULTIGEOMETRY" :
                $this->geomType[$this->count] = "MULTIGEOMETRY";
                break;
            case "COORDINATES":
                if (($this->geomType[$this->count] ?? '') == "POINT" || ($this->geomType[$this->count] ?? '') == "LINESTRING") {
                    $this->appendCoords($this->convertCoordinatesToWKT($this->concatCoords));
                } else if (($this->geomType[$this->count] ?? '') == "POLYGON") {
                    if ($this->isIsland == true) $this->appendCoords(",");
                    $this->appendCoords("(" . $this->convertCoordinatesToWKT($this->concatCoords) . ")");
                }
                $this->concatCoords = "";
                break;
            case "POSLIST": // GML3
                if (($this->geomType[$this->count] ?? '') == "POINT" || ($this->geomType[$this->count] ?? '') == "LINESTRING") {
                    $this->appendCoords($this->convertPostListToWKT($this->concatCoords));
                } else if (($this->geomType[$this->count] ?? '') == "POLYGON") {
                    if ($this->isIsland == true) $this->appendCoords(",");
                    $this->appendCoords("(" . $this->convertPostListToWKT($this->concatCoords) . ")");
                }
                $this->concatCoords = "";
                break;
            case "POS": // GML3
                $this->appendCoords($this->convertPostListToWKT($this->concatCoords));
                $this->concatCoords = "";
                break;
            case "XML_SERIALIZER_TAG":
                if ($this->lastTag == "POLYGON" || $this->lastTag == "LINESTRING" || $this->lastTag == "POINT") {
                    $this->appendCoords("),");
                }
                break;
        }
        if (in_array(strtoupper($this->currentTag), $this->splitTag)) {
            $this->count++;
        }
        $this->lastTag = $this->currentTag;
        $this->currentTag = null;
    }

    /**
     * @param \XMLParser $parser
     */
    private function characterData($parser, string $data): void
    {
        switch ($this->currentTag) {
            case "COORDINATES" :
            case "POS":
            case "POSLIST" : // concat the data in case the 1024 char limit is exceeded
                $this->concatCoords .= $data;
                break;
            case "PROPERTYNAME":
                break;
            case "SRSNAME": // not normal. Used when serializing array to xml
                $this->srid[$this->count] = self::parseEpsgCode($data);
                $this->axisOrder[$this->count] = self::getAxisOrderFromEpsg($data);
                break;
        }
    }

    private function convertCoordinatesToWKT(string $_str): string
    {
        $_str = str_replace(" ", "&", $_str);
        $_str = str_replace(",", " ", $_str);
        $_str = str_replace("&", ",", $_str);
        // If urn EPSG reverse the axis order
        if (($this->axisOrder[$this->count] ?? null) == "latitude") {
            $reversedArr = [];
            $split = explode(",", $_str);
            foreach ($split as $value) {
                $splitCoord = explode(" ", $value);
                $reversedArr[] = $splitCoord[1] . " " . $splitCoord[0];
            }
            $_str = implode(",", $reversedArr);
        }
        return $_str;
    }

    private function convertPostListToWKT(string $_str): string
    {
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
        // If urn EPSG reverse the axis order
        if (($this->axisOrder[$this->count] ?? null) == "latitude") {
            $reversedArr = [];
            $split = explode(",", $newStr);
            foreach ($split as $value) {
                $splitCoord = explode(" ", $value);
                $reversedArr[] = $splitCoord[1] . " " . $splitCoord[0];
            }
            $newStr = implode(",", $reversedArr);
        }
        return $newStr;
    }

    public static function parseEpsgCode(string $epsg): string
    {
        $split = explode(":", $epsg);
        $clean = end($split);
        return preg_replace("/[\w]\./", "", $clean);
    }

    public static function getAxisOrderFromEpsg(string $epsg): string
    {
        $split = explode(":", $epsg);
        return $split[0] == "urn" ? "latitude" : "longitude";
    }
}
