<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\geometry;

class MultiPoint extends GeometryFactory
{
    public function __construct(string $wkt, ?string $srid)
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = 'MULTIPOINT';
        $this->shapeArray = $this->deconstructionOfWKT();
    }

    public function construction(): void
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

    public function toGML(): string
    {
        $srid = $this->srid ? ["srsName" => $this->srid] : null;
        $str = "";
        $str .= $this->writeTag("open", "gml", "MultiPoint", $srid, true, true);
        $this->depth++;
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $str .= $this->writeTag("open", "gml", "pointMember", null, true, true);
            $this->depth++;
            $str .= $this->convertPoint($this->shapeArray[$__i], false);
            $this->depth--;
            $str .= $this->writeTag("close", "gml", "pointMember", null, true, true);
        }
        $this->depth--;
        $str .= $this->writeTag("close", "gml", "MultiPoint", null, true, true);
        return $str;
    }
}
