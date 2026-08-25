<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\geometry;

class GeometryCollection extends GeometryFactory
{
    /** @var array<int|string, GeometryFactory|null> */
    public array $geometryArray = [];

    /**
     * @param array<int|string, string> $wktArray
     */
    public function __construct(array $wktArray)
    {
        foreach ($wktArray as $key => $value) {
            $this->geometryArray[$key] = $this->createGeometry($value);
        }
    }

    /**
     * @return array<int|string, GeometryFactory|null>
     */
    public function getGeometryArray(): array
    {
        return $this->geometryArray;
    }
}
