<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\geometry;

class Polygon extends GeometryFactory
{
    public function __construct(string $wkt, ?string $srid)
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = 'POLYGON';
        $this->shapeArray = $this->deconstructionOfWKT();
    }

    /**
     * Puts the deconstructed WKT together again and sets the WKT.
     */
    public function construction(): void
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

    public function getAsMulti(): string
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

    public function toGML(): string
    {
        return $this->convertPolygon($this->shapeArray);
    }
}
