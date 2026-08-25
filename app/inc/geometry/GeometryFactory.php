<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Autoloadable, worker-safe port of the legacy app/libs/phpgeometry_class.php.
 * The shared indentation depth is an instance property (was a file-scope global),
 * so no state leaks between requests under a persistent SAPI.
 */

namespace app\inc\geometry;

use Exception;

/**
 * Factory for WKT-backed geometry objects that render themselves as GML 2.
 */
class GeometryFactory
{
    public ?string $srid = null;
    public string $wkt = '';
    /** @var array<int, bool> */
    public array $isIsland = [];
    /** @var array<int, string> */
    public array $shapeArray = [];
    public string $geomType = '';
    /** Indentation depth shared with subclasses while rendering GML. */
    protected int $depth = 0;

    /**
     * Creates a new geometry object from a WKT string.
     */
    public function createGeometry(string $wkt, ?string $srid = null): Point|LineString|Polygon|MultiPoint|MultiLineString|MultiPolygon|null
    {
        $wkt = str_replace(", ", ",", $wkt); // replace " ," with ","
        preg_match_all("/[a-z]*[A-Z]*/", $wkt, $__typeArray); // Match the type of the geometry
        $__type = $__typeArray[0][0];
        return match ($__type) {
            "MULTIPOLYGON" => new MultiPolygon($wkt, $srid),
            "MULTILINESTRING" => new MultiLineString($wkt, $srid),
            "MULTIPOINT" => new MultiPoint($wkt, $srid),
            "POINT" => new Point($wkt, $srid),
            "LINESTRING" => new LineString($wkt, $srid),
            "POLYGON" => new Polygon($wkt, $srid),
            default => null,
        };
    }

    /**
     * @param array<string> $wktArray
     * @throws Exception
     */
    public function createGeometryCollection(array $wktArray): GeometryCollection
    {
        return new GeometryCollection($wktArray);
    }

    /**
     * Takes a WKT string and returns an array with coords (string) for shapes. Called from a child object.
     *
     * @return array<int, string>
     */
    public function deconstructionOfWKT(): array
    {
        preg_match_all("/[^a-z|(*]*[0-9]/", $this->wkt, $__wktArray); // regex is used to extract coordinates
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
        return $wktArray;
    }

    public function getWKT(): string
    {
        return $this->wkt;
    }

    public function getGeomType(): string
    {
        return $this->geomType;
    }

    /**
     * @param array<string, string>|null $atts
     */
    protected function writeTag(string $type, ?string $ns, ?string $tag, ?array $atts, ?bool $ind, ?bool $n): string
    {
        $str = "";
        if ($ind) {
            for ($i = 0; $i < $this->depth; $i++) {
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
        return $str;
    }

    protected function convertPoint(string $geom, bool $hasSrid = true): string
    {
        $srid = ($hasSrid && $this->srid != null) ? ["srsName" => $this->srid] : null;
        $_str = "";
        $_str .= $this->writeTag("open", "gml", "Point", $srid, true, true);
        $this->depth++;
        $_str .= $this->writeTag("open", "gml", "coordinates", null, true, false);
        $_str .= $this->convertCoordinatesToGML($geom);
        $_str .= $this->writeTag("close", "gml", "coordinates", null, false, true);
        $this->depth--;
        $_str .= $this->writeTag("close", "gml", "Point", null, true, true);
        return $_str;
    }

    protected function convertLineString(string $geom, bool $hasSrid = true): string
    {
        $srid = ($hasSrid && $this->srid != null) ? ["srsName" => $this->srid] : null;
        $_str = "";
        $_str .= $this->writeTag("open", "gml", "LineString", $srid, true, true);
        $this->depth++;
        $_str .= $this->writeTag("open", "gml", "coordinates", null, true, false);
        $_str .= $this->convertCoordinatesToGML($geom);
        $_str .= $this->writeTag("close", "gml", "coordinates", null, false, true);
        $this->depth--;
        $_str .= $this->writeTag("close", "gml", "LineString", null, true, true);
        return $_str;
    }

    protected function convertLineStringToGML3(string $geom, bool $hasSrid = true): string
    {
        $srid = ($hasSrid && $this->srid != null) ? ["srsName" => $this->srid] : null;
        $_str = "";
        $_str .= $this->writeTag("open", "gml", "LineString", $srid, true, true);
        $this->depth++;
        $_str .= $this->writeTag("open", "gml", "posList", null, true, false);
        $_str .= $this->convertCoordinatesToGML3($geom);
        $_str .= $this->writeTag("close", "gml", "posList", null, false, true);
        $this->depth--;
        $_str .= $this->writeTag("close", "gml", "LineString", null, true, true);
        return $_str;
    }

    /**
     * @param array<int, string> $rings
     */
    protected function convertPolygon(array $rings, bool $hasSrid = true): string
    {
        $srid = ($hasSrid && $this->srid != null) ? ["srsName" => $this->srid] : null;
        $_str = "";
        $_str .= $this->writeTag("open", "gml", "Polygon", $srid, true, true);
        $this->depth++;
        $pass = 0;
        foreach ($rings as $ring) {
            $boundTag = $pass == 0 ? "outer" : "inner";
            $_str .= $this->writeTag("open", "gml", $boundTag . "BoundaryIs", null, true, true);
            $this->depth++;
            $_str .= $this->writeTag("open", "gml", "LinearRing", null, true, true);
            $this->depth++;
            $_str .= $this->writeTag("open", "gml", "coordinates", null, true, false);
            $_str .= $this->convertCoordinatesToGML($ring);
            $_str .= $this->writeTag("close", "gml", "coordinates", null, false, true);
            $this->depth--;
            $_str .= $this->writeTag("close", "gml", "LinearRing", null, true, true);
            $this->depth--;
            $_str .= $this->writeTag("close", "gml", $boundTag . "BoundaryIs", null, true, true);
            $pass++;
        }
        $this->depth--;
        $_str .= $this->writeTag("close", "gml", "Polygon", null, true, true);
        return $_str;
    }

    protected function convertCoordinatesToGML(string $_str): string
    {
        $_str = str_replace(" ", "&", $_str);
        $_str = str_replace(",", " ", $_str);
        $_str = str_replace("&", ",", $_str);
        $_str = str_replace("(", "", $_str);
        $_str = str_replace(")", "", $_str);
        return $_str;
    }

    protected function convertCoordinatesToGML3(string $_str): string
    {
        return str_replace(",", " ", $_str);
    }
}
