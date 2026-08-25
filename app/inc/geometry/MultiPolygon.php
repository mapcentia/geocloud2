<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\geometry;

class MultiPolygon extends GeometryFactory
{
    public function __construct(string $wkt, ?string $srid)
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = 'MULTIPOLYGON';
        $this->shapeArray = $this->deconstructionOfWKT();
    }

    public function construction(): void
    {
        $__wktArray = [];
        $__newWkt = $this->geomType . "(";
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            switch ($this->isIsland[$__i]) { // check if a shape is an island of another
                case false:
                    if (!empty($this->isIsland[$__i + 1])) { // what is the next one?
                        $__wktArray[$__i] = "((" . $this->shapeArray[$__i] . ")";
                    } else {
                        $__wktArray[$__i] = "((" . $this->shapeArray[$__i] . "))";
                    }
                    break;
                case true:
                    if (!empty($this->isIsland[$__i + 1])) {
                        $__wktArray[$__i] = "(" . $this->shapeArray[$__i] . ")";
                    } else {
                        $__wktArray[$__i] = "(" . $this->shapeArray[$__i] . "))";
                    }
                    break;
            }
        }
        $__newWkt = $__newWkt . implode(",", $__wktArray);
        $__newWkt = $__newWkt . ")";
        $this->wkt = $__newWkt;
    }

    public function toGML(): string
    {
        $srid = $this->srid ? ["srsName" => $this->srid] : null;
        $str = "";
        $polys = [];
        $i = 0;
        while (!empty($this->shapeArray[$i])) {
            if (!empty($this->isIsland[$i + 1])) {
                $_rings = [$this->shapeArray[$i]];
                while (!empty($this->isIsland[$i + 1])) {
                    $_rings[] = $this->shapeArray[$i + 1];
                    $i++;
                }
                $polys[] = $_rings;
                $i++;
            } else {
                $polys[] = [$this->shapeArray[$i]];
                $i++;
            }
        }
        $str = $str . $this->writeTag("open", "gml", "MultiPolygon", $srid, true, true);
        $this->depth++;
        foreach ($polys as $__array) {
            $str = $str . $this->writeTag("open", "gml", "polygonMember", null, true, true);
            $this->depth++;
            $str = $str . $this->convertPolygon($__array, false);
            $this->depth--;
            $str = $str . $this->writeTag("close", "gml", "polygonMember", null, true, true);
        }
        $this->depth--;
        $str = $str . $this->writeTag("close", "gml", "MultiPolygon", null, true, true);
        return $str;
    }
}
