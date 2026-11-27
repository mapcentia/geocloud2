<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\conf\App;
use app\ows\Legend;
use Codeception\Test\Unit;

/**
 * The pure parts of app\ows\Legend (GET /api/v4/legends): the layer list, the class order that
 * ties a class to MapServer's class index, and the URLs the pictures are fetched from.
 */
class LegendServiceTest extends Unit
{
    protected UnitTester $tester;

    private mixed $savedMapServer;

    protected function _before(): void
    {
        $this->savedMapServer = App::$param['mapServer'] ?? null;
    }

    protected function _after(): void
    {
        if ($this->savedMapServer === null) {
            unset(App::$param['mapServer']);
        } else {
            App::$param['mapServer'] = $this->savedMapServer;
        }
    }

    public function testParseNames(): void
    {
        $this->assertSame(['s.t'], Legend::parseNames('s.t'));
        $this->assertSame(['s.t', 'other', 'æble.grød_1-2'], Legend::parseNames('s.t,other,æble.grød_1-2'));
        foreach (['', 's.t,', ',s.t', 's..t', 's.t.geom', 's t', "s.t'", 's.t;x', 's/t', '.t', 's.'] as $bad) {
            $this->assertNull(Legend::parseNames($bad), $bad);
        }
    }

    public function testSortClassesFollowsSortidAndKeepsStoredOrderOnTies(): void
    {
        $json = json_encode([
            ['name' => 'c', 'sortid' => 30],
            ['name' => 'a1', 'sortid' => 10],
            ['name' => 'b', 'sortid' => '20'],
            ['name' => 'a2', 'sortid' => 10],
            ['name' => 'none'],
        ]);
        $this->assertSame(['none', 'a1', 'a2', 'b', 'c'], array_column(Legend::sortClasses($json), 'name'));
    }

    public function testSortClassesToleratesEmptyAndBrokenInput(): void
    {
        $this->assertSame([], Legend::sortClasses(null));
        $this->assertSame([], Legend::sortClasses(''));
        $this->assertSame([], Legend::sortClasses('not json'));
        $this->assertSame([], Legend::sortClasses('"a string"'));
        $this->assertSame([['name' => 'x']], Legend::sortClasses('[null, {"name": "x"}, 5]'));
    }

    public function testLayerRowPrefersTheGeometryRow(): void
    {
        $rows = [
            ['f_table_schema' => 's', 'f_table_name' => 'other', 'f_geometry_column' => 'the_geom', 'class' => 'x'],
            ['f_table_schema' => 's', 'f_table_name' => 't', 'f_geometry_column' => 'gc2_non_postgis', 'class' => null],
            ['f_table_schema' => 's', 'f_table_name' => 't', 'f_geometry_column' => 'the_geom', 'class' => 'c'],
        ];
        $this->assertSame('c', Legend::layerRow($rows, 's', 't')['class']);
        $this->assertSame('gc2_non_postgis', Legend::layerRow([$rows[1]], 's', 't')['f_geometry_column']);
        $this->assertNull(Legend::layerRow($rows, 's', 'nope'));
        $this->assertNull(Legend::layerRow([], 's', 't'));
    }

    public function testIconUrlFollowsMapServerHost(): void
    {
        unset(App::$param['mapServer']);
        $url = Legend::iconUrl('mydb', 'my_schema.my table', 2);
        $this->assertStringStartsWith('http://127.0.0.1/cgi-bin/mapserv.fcgi?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('legendicon', $q['mode']);
        $this->assertSame('my_schema.my table,2', $q['icon']);
        $this->assertStringEndsWith('/app/wms/mapfiles/mydb_my_schema_wms.map', $q['map']);

        App::$param['mapServer'] = ['host' => 'http://mapserver.internal:8081'];
        $this->assertStringStartsWith('http://mapserver.internal:8081/cgi-bin/mapserv.fcgi?', Legend::iconUrl('mydb', 's.t', 0));
    }

    public function testPictureUrl(): void
    {
        App::$param['mapServer'] = ['host' => 'http://mapserver.internal:8081'];

        // An ordinary layer has no picture: its legend is its classes
        $this->assertNull(Legend::pictureUrl(null, null));
        $this->assertNull(Legend::pictureUrl('', ''));

        // The layer's own legend URL wins
        $this->assertSame('https://example.com/l.png',
            Legend::pictureUrl('https://wms.example.com/ows?layers=a', 'https://example.com/l.png'));

        // External WMS: the URL the mapfile advertises as wms_get_legend_url
        $this->assertSame('https://wms.example.com/ows?LAYER=a&format=image/png&REQUEST=getlegendgraphic',
            Legend::pictureUrl('https://wms.example.com/ows?layers=a&format=image/png', null));

        // QGIS project: asked on the MapServer node, whatever host the stored URL names
        $url = Legend::pictureUrl('http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=/var/www/geocloud2/app/wms/qgsfiles/p.qgs&layers=roads', null);
        $this->assertStringStartsWith('http://mapserver.internal:8081/cgi-bin/qgis_mapserv.fcgi?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('/var/www/geocloud2/app/wms/qgsfiles/p.qgs', $q['map']);
        $this->assertSame('GetLegendGraphic', $q['REQUEST']);
        $this->assertSame('roads', $q['LAYER']);
        $this->assertSame('image/png', $q['FORMAT']);

        // Something that is neither is not fetched
        $this->assertNull(Legend::pictureUrl('file:///etc/passwd', null));
    }
}
