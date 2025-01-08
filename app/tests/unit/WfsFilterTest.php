<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\conf\App;
use app\inc\WfsFilter;
use Codeception\Test\Unit;

class WfsFilterTest extends Unit
{
    protected UnitTester $tester;

    protected $unserializer;

    protected function _before(): void
    {
        set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . "/../../libs/PEAR");
        require_once __DIR__ . "/../../libs/PEAR/XML/Unserializer.php";
        require_once __DIR__ . "/../../libs/PEAR/XML/Serializer.php";
        $unserializer_options = array(
            "parseAttributes" => true,
            "contentName" => "_content",
        );
        $this->unserializer = new XML_Unserializer($unserializer_options);
    }

    public function testPropertyIsEqualTo(): void
    {
        $filter = '
                    <ogc:Filter>
                        <ogc:PropertyIsEqualTo xmlns:ogc="http://www.opengis.net/ogc">
                            <ogc:PropertyName>gid</ogc:PropertyName>
                            <ogc:Literal>1</ogc:Literal>
                        </ogc:PropertyIsEqualTo>
                    </ogc:Filter>
                 ';
        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(\"gid\"='1')", $where);
    }

    public function testPropertyIsNotEqualTo(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc">
                        <ogc:PropertyIsNotEqualTo>
                            <ogc:PropertyName>gid</ogc:PropertyName>
                            <ogc:Literal>1</ogc:Literal>
                        </ogc:PropertyIsNotEqualTo>
                    </ogc:Filter>
                  ';

        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(\"gid\"<>'1')", $where);
    }

    public function testPropertyIsNull(): void
    {
        $filter = '
                    <ogc:Filter xmlns="http://www.opengis.net/ogc">
                        <ogc:PropertyIsNull>
                            <ogc:PropertyName>gid</ogc:PropertyName>
                        </ogc:PropertyIsNull>
                    </ogc:Filter>
                  ';

        $filter = WfsFilter::dropNameSpace($filter);

        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(\"gid\" isnull)", $where);

    }

    public function testPropertyIsNotNull(): void
    {
        $filter = '
                    <ogc:Filter xmlns="http://www.opengis.net/ogc">
                        <ogc:Not>
                            <ogc:PropertyIsNull>
                                <ogc:PropertyName>gid</ogc:PropertyName>
                            </ogc:PropertyIsNull>
                        </ogc:Not>
                    </ogc:Filter>
                  ';

        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(NOT(\"gid\" isnull))", $where);
    }

    public function testPropertyIsBetween(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc">
                        <ogc:PropertyIsBetween>
                            <ogc:PropertyName>gid</ogc:PropertyName>
                                <ogc:LowerBoundary>
                                    <ogc:Literal>1</ogc:Literal>
                                </ogc:LowerBoundary>
                                <ogc:UpperBoundary>
                                    <ogc:Literal>2</ogc:Literal>
                                </ogc:UpperBoundary>
                        </ogc:PropertyIsBetween>
                    </ogc:Filter>
                  ';

        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(\"gid\" > '1' AND \"gid\" < '2')", $where);
    }

    public function testPropertyIsNotBetween(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc">
                        <ogc:Not>
                            <ogc:PropertyIsBetween>
                                <ogc:PropertyName>gid</ogc:PropertyName>
                                    <ogc:LowerBoundary>
                                        <ogc:Literal>1</ogc:Literal>
                                    </ogc:LowerBoundary>
                                    <ogc:UpperBoundary>
                                        <ogc:Literal>2</ogc:Literal>
                                    </ogc:UpperBoundary>
                            </ogc:PropertyIsBetween>
                        </ogc:Not>
                    </ogc:Filter>
                  ';

        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(NOT(\"gid\" > '1' AND \"gid\" < '2'))", $where);
    }

    public function testAndPropertyIsEqualTo(): void
    {
        $filter = '
                    <ogc:Filter>
                        <ogc:And>
                            <ogc:PropertyIsEqualTo xmlns:ogc="http://www.opengis.net/ogc">
                                <ogc:PropertyName>id</ogc:PropertyName>
                                <ogc:Literal>1</ogc:Literal>
                            </ogc:PropertyIsEqualTo>
                            <ogc:PropertyIsEqualTo xmlns:ogc="http://www.opengis.net/ogc">
                                <ogc:PropertyName>field</ogc:PropertyName>
                                <ogc:Literal>2</ogc:Literal>
                            </ogc:PropertyIsEqualTo>
                        </ogc:And>
                    </ogc:Filter>
                 ';
        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("((\"id\"='1') And (\"field\"='2'))", $where);
    }

    public function testAndOrPropertyIsEqualTo(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc">
                        <ogc:And>
                            <ogc:Or>
                                <ogc:PropertyIsEqualTo>
                                    <ogc:PropertyName>gid</ogc:PropertyName>
                                    <ogc:Literal>1</ogc:Literal>
                                </ogc:PropertyIsEqualTo>
                                <ogc:PropertyIsEqualTo>
                                    <ogc:PropertyName>gid</ogc:PropertyName>
                                    <ogc:Literal>2</ogc:Literal>
                                </ogc:PropertyIsEqualTo>
                            </ogc:Or>
                            <ogc:PropertyIsEqualTo>
                                <ogc:PropertyName>code</ogc:PropertyName>
                                    <ogc:Literal>1101</ogc:Literal>
                                </ogc:PropertyIsEqualTo>
                        </ogc:And>
                    </ogc:Filter>
                 ';
        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(((\"gid\"='1') Or (\"gid\"='2')) And \"code\"='1101')", $where);
    }

    public function testPropertyIsLike(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc">
                        <ogc:PropertyIsLike>
                            <ogc:PropertyName>gid</ogc:PropertyName>
                            <ogc:Literal>1</ogc:Literal>
                        </ogc:PropertyIsLike>
                    </ogc:Filter>
                 ';
        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(\"gid\" LIKE '%1%')", $where);
    }

    public function testNotOrPropertyIsEqualTo(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc">
                        <ogc:Not>
                            <ogc:Or>
                                <ogc:PropertyIsEqualTo>
                                    <ogc:PropertyName>gid</ogc:PropertyName>
                                    <ogc:Literal>1</ogc:Literal>
                                </ogc:PropertyIsEqualTo>
                                <ogc:PropertyIsEqualTo>
                                    <ogc:PropertyName>gid</ogc:PropertyName>
                                    <ogc:Literal>2</ogc:Literal>
                                </ogc:PropertyIsEqualTo>
                            </ogc:Or>
                        </ogc:Not>
                    </ogc:Filter>
                 ';
        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(NOT((\"gid\"='1') Or (\"gid\"='2')))", $where);
    }

    public function testOrNotAndOrPropertyIsEqualTo(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc">
                        <ogc:Or>
                            <ogc:Not>
                                <ogc:PropertyIsEqualTo>
                                    <ogc:PropertyName>gid</ogc:PropertyName>
                                    <ogc:Literal>1</ogc:Literal>
                                </ogc:PropertyIsEqualTo>
                            </ogc:Not>
                            <ogc:And>
                                <ogc:PropertyIsEqualTo>
                                    <ogc:PropertyName>gid</ogc:PropertyName>
                                    <ogc:Literal>2</ogc:Literal>
                                </ogc:PropertyIsEqualTo>
                                <ogc:Not>
                                    <ogc:PropertyIsEqualTo>
                                        <ogc:PropertyName>gid</ogc:PropertyName>
                                        <ogc:Literal>3</ogc:Literal>
                                    </ogc:PropertyIsEqualTo>
                                </ogc:Not>
                            </ogc:And>
                        </ogc:Or>
                    </ogc:Filter>   
              ';
        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr);
//        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("((NOT(\"gid\"='3') And \"gid\"='2') Or NOT(\"gid\"='1'))", $where);
    }
    public function testBbox(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc" xmlns:gml="http://www.opengis.net/gml">
                        <ogc:BBOX>
                            <ogc:PropertyName>the_geom</ogc:PropertyName>
                            <gml:Envelope srsName="urn:ogc:def:crs:EPSG::4326">
                                <gml:lowerCorner>1 1</gml:lowerCorner>
                                <gml:upperCorner>2 2</gml:upperCorner>
                            </gml:Envelope>
                        </ogc:BBOX>
                    </ogc:Filter>
              ';
        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr, "25832");
//        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(ST_Intersects(ST_Transform(ST_GeometryFromText('POLYGON((1 1,2 1,2 2,1 2,1 1))',4326),25832),\"the_geom\"))", $where);
    }
    public function testBboxIntersects(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc" xmlns:gml="http://www.opengis.net/gml">
                        <ogc:Intersects xmlns:ogc="http://www.opengis.net/ogc">
                            <ogc:PropertyName xmlns:ogc="http://www.opengis.net/ogc">the_geom</ogc:PropertyName>
                            <gml:Polygon xmlns:gml="http://www.opengis.net/gml" srsName="urn:ogc:def:crs:EPSG::4326">
                                <gml:exterior xmlns:gml="http://www.opengis.net/gml">
                                    <gml:LinearRing xmlns:gml="http://www.opengis.net/gml">
                                        <gml:posList
                                                xmlns:gml="http://www.opengis.net/gml" srsDimension="2">1 1 1 2 2 2 2 1 1 1
                                        </gml:posList>
                                    </gml:LinearRing>
                                </gml:exterior>
                            </gml:Polygon>
                        </ogc:Intersects>
                    </ogc:Filter>
              ';
        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr, "25832");
//        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(ST_Intersects(ST_Transform(ST_GeometryFromText('POLYGON((1 1,2 1,2 2,1 2,1 1))',4326),25832),the_geom))", $where);
    }
    public function testGmlObjectId(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc" xmlns:gml="http://www.opengis.net/gml">
                        <ogc:GmlObjectId gml:id="1"/>
                        <ogc:GmlObjectId gml:id="2"/>
                    </ogc:Filter>
              ';
        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr, null, null, "gid");
//        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(\"gid\"='1' OR \"gid\"='2')", $where);
    }

    public function testFeatureId(): void
    {
        $filter = '
                    <ogc:Filter xmlns:ogc="http://www.opengis.net/ogc" xmlns:gml="http://www.opengis.net/gml">
                        <ogc:FeatureId fid="1"/>
                        <ogc:FeatureId fid="2"/>
                    </ogc:Filter>
              ';
        $filter = WfsFilter::dropNameSpace($filter);
        $this->unserializer->unserialize($filter);
        $arr = $this->unserializer->getUnserializedData();
        $where = WfsFilter::explode($arr, null, null, "gid");
//        codecept_debug($arr);
        codecept_debug($where);
        $this->assertEquals("(\"gid\"='1' OR \"gid\"='2')", $where);
    }
}