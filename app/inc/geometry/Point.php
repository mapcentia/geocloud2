<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\geometry;

class Point extends GeometryFactory
{
    public function __construct(string $wkt, ?string $srid)
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = "POINT";
        $this->shapeArray = $this->deconstructionOfWKT();
    }

    /**
     * Puts the deconstructed WKT together again and sets the WKT.
     */
    public function construction(): void
    {
        $this->wkt = $this->geomType . "(" . $this->shapeArray[0] . ")";
    }

    /**
     * Returns WKT as multi feature.
     */
    public function getAsMulti(): string
    {
        return "MULTI" . $this->geomType . "((" . $this->shapeArray[0] . "))";
    }

    public function toGML(): string
    {
        return $this->convertPoint($this->shapeArray[0]);
    }
}
