<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\geometry;

class MultiLineString extends GeometryFactory
{
    public function __construct(string $wkt, ?string $srid)
    {
        $this->wkt = $wkt;
        $this->srid = $srid;
        $this->geomType = 'MULTILINESTRING';
        $this->shapeArray = $this->deconstructionOfWKT();
    }

    public function construction(): void
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

    public function toGML(): string
    {
        $srid = $this->srid ? ["srsName" => $this->srid] : null;
        $str = "";
        $str .= $this->writeTag("open", "gml", "MultiLineString", $srid, true, true);
        $this->depth++;
        for ($__i = 0; $__i < (sizeof($this->shapeArray)); $__i++) {
            $str .= $this->writeTag("open", "gml", "lineStringMember", null, true, true);
            $this->depth++;
            $str .= $this->convertLineString($this->shapeArray[$__i], false);
            $this->depth--;
            $str .= $this->writeTag("close", "gml", "lineStringMember", null, true, true);
        }
        $this->depth--;
        $str .= $this->writeTag("close", "gml", "MultiLineString", null, true, true);
        return $str;
    }

    public function toGML3(): string
    {
        $srid = $this->srid ? ["srsName" => $this->srid] : null;
        $str = "";
        $str .= $this->writeTag("open", "gml", "MultiLineString", $srid, true, true);
        $this->depth++;
        for ($i = 0; $i < (sizeof($this->shapeArray)); $i++) {
            $str .= $this->writeTag("open", "gml", "lineStringMember", null, true, true);
            $this->depth++;
            $str .= $this->convertLineStringToGML3($this->shapeArray[$i], false);
            $this->depth--;
            $str .= $this->writeTag("close", "gml", "lineStringMember", null, true, true);
        }
        $this->depth--;
        $str .= $this->writeTag("close", "gml", "MultiLineString", null, true, true);
        return $str;
    }
}
