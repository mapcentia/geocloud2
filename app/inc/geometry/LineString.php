<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\geometry;

class LineString extends GeometryFactory
{
    public function __construct(string $wkt, ?string $srid)
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = 'LINESTRING';
        $this->shapeArray = $this->deconstructionOfWKT();
    }

    public function construction(): void
    {
        $this->wkt = $this->geomType . "(" . $this->shapeArray[0] . ")";
    }

    public function getAsMulti(): string
    {
        return "MULTI" . $this->geomType . "((" . $this->shapeArray[0] . "))";
    }

    public function toGML(): string
    {
        return $this->convertLineString($this->shapeArray[0]);
    }
}
