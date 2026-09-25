<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\WfsPaging;
use Codeception\Test\Unit;

/**
 * Pure logic behind the scheduler's automatic WFS 2.0.0 paging
 * (app/scripts/get.php getCmdWfsPaging): when to page, page URLs, the
 * numberMatched/numberReturned bookkeeping and the sortBy heuristic.
 */
class WfsPagingTest extends Unit
{
    protected UnitTester $tester;

    private const string URL = 'https://wfs.example.com/geoserver/wfs?service=WFS&version=2.0.0&request=GetFeature&typeNames=topp:states';

    public function testDetectsWfs200GetFeature(): void
    {
        $p = WfsPaging::detect(self::URL);
        $this->assertNotNull($p);
        $this->assertSame('topp:states', $p->typeNames);
        $this->assertSame(WfsPaging::DEFAULT_PAGE_SIZE, $p->pageSize);
        $this->assertNull($p->sortBy);
    }

    public function testParameterNamesAndValuesAreCaseInsensitive(): void
    {
        $p = WfsPaging::detect('https://x/wfs?SERVICE=wfs&VERSION=2.0.0&REQUEST=getfeature&TYPENAMES=a:b&COUNT=500');
        $this->assertNotNull($p);
        $this->assertSame('a:b', $p->typeNames);
        $this->assertSame(500, $p->pageSize);
        // WFS 2.0 also accepts the singular typeName
        $this->assertSame('a:b', WfsPaging::detect('https://x/wfs?service=WFS&version=2.0.0&request=GetFeature&typeName=a:b')?->typeNames);
    }

    public function testDoesNotPageOtherVersionsRequestsOrExplicitStartIndex(): void
    {
        $this->assertNull(WfsPaging::detect('https://x/wfs?service=WFS&version=1.1.0&request=GetFeature&typeName=a:b'));
        $this->assertNull(WfsPaging::detect('https://x/wfs?service=WFS&version=1.0.0&request=GetFeature&typeName=a:b'));
        $this->assertNull(WfsPaging::detect('https://x/wfs?service=WFS&version=2.0.0&request=GetCapabilities'));
        $this->assertNull(WfsPaging::detect('https://x/wms?service=WMS&version=2.0.0&request=GetFeature'));
        $this->assertNull(WfsPaging::detect(self::URL . '&startIndex=0'), 'an explicit startIndex means the caller pages');
        $this->assertNull(WfsPaging::detect('https://x/files/data.zip'));
        $this->assertNull(WfsPaging::detect('grid,gid|' . self::URL), 'grid notation keeps the bbox paging');
    }

    public function testExplicitSortByIsKept(): void
    {
        $p = WfsPaging::detect(self::URL . '&sortBy=STATE_NAME');
        $this->assertSame('STATE_NAME', $p->sortBy);
    }

    public function testPageUrlAppendsPagingParameters(): void
    {
        $p = WfsPaging::detect(self::URL);
        $this->assertSame(self::URL . '&startIndex=0&count=10000', $p->pageUrl(0));
        $this->assertSame(self::URL . '&startIndex=20000&count=10000', $p->pageUrl(20000));

        $withSort = WfsPaging::detect(self::URL . '&count=100')->withSortBy('gml_id');
        // count/startIndex/sortBy already in the URL are replaced by the canonical paging parameters
        $this->assertSame(self::URL . '&startIndex=300&count=100&sortBy=gml_id', $withSort->pageUrl(300));
    }

    /**
     * Some WFS 2.0.0 servers reject sortBy outright, so a job can turn it off
     * (jobs.use_sortby, --useSortBy). Then no page URL may carry a sortBy —
     * not one the job URL asked for, and not one get.php derived from
     * DescribeFeatureType afterwards.
     */
    public function testSortByCanBeTurnedOffForServersThatRejectIt(): void
    {
        $p = WfsPaging::detect(self::URL . '&sortBy=STATE_NAME', useSortBy: false);
        $this->assertNotNull($p);
        $this->assertFalse($p->useSortBy);
        $this->assertNull($p->sortBy, 'a sortBy in the job URL is dropped too');
        $this->assertSame(self::URL . '&startIndex=0&count=10000', $p->pageUrl(0));

        // get.php calls withSortBy() with whatever DescribeFeatureType yielded;
        // with sorting off it must not come back.
        $derived = $p->withSortBy('gml_id');
        $this->assertNull($derived->sortBy);
        $this->assertStringNotContainsString('sortBy', $derived->pageUrl(10000));

        // Everything else about the paging is unchanged.
        $this->assertSame(self::URL . '&startIndex=10000&count=10000', $p->pageUrl(10000));
        $this->assertSame('topp:states', $p->typeNames);
    }

    public function testSortByIsOnUnlessTurnedOff(): void
    {
        $this->assertTrue(WfsPaging::detect(self::URL)->useSortBy, 'sorting stays on by default');
        $this->assertSame('gml_id', WfsPaging::detect(self::URL)->withSortBy('gml_id')->sortBy);
        $this->assertSame('STATE_NAME', WfsPaging::detect(self::URL . '&sortBy=STATE_NAME', useSortBy: true)->sortBy);
    }

    public function testPageUrlDoesNotDuplicateCountWhenReplacing(): void
    {
        // A count already in the URL is the page size; pageUrl must not send two different counts.
        $p = WfsPaging::detect(self::URL . '&count=100');
        $this->assertSame(1, substr_count($p->pageUrl(0), 'count='), $p->pageUrl(0));
    }

    public function testParsesFeatureCollectionCounts(): void
    {
        $head = '<?xml version="1.0"?><wfs:FeatureCollection xmlns:wfs="http://www.opengis.net/wfs/2.0" numberMatched="12345" numberReturned="10000" timeStamp="2026-09-16T10:00:00Z">';
        $this->assertSame(['matched' => 12345, 'returned' => 10000], WfsPaging::parseCounts($head));
        $this->assertSame(['matched' => null, 'returned' => 3], WfsPaging::parseCounts('<wfs:FeatureCollection numberReturned="3" numberMatched="unknown">'));
        $this->assertSame(['matched' => null, 'returned' => null], WfsPaging::parseCounts('<ows:ExceptionReport>boom</ows:ExceptionReport>'));
    }

    public function testIsLastPage(): void
    {
        $size = 100;
        $this->assertTrue(WfsPaging::isLastPage(startIndex: 0, returned: 0, matched: null, pageSize: $size), 'empty page');
        $this->assertTrue(WfsPaging::isLastPage(startIndex: 0, returned: 40, matched: null, pageSize: $size), 'short page');
        $this->assertFalse(WfsPaging::isLastPage(startIndex: 0, returned: 100, matched: null, pageSize: $size), 'full page, unknown total');
        $this->assertTrue(WfsPaging::isLastPage(startIndex: 200, returned: 100, matched: 300, pageSize: $size), 'reached numberMatched');
        $this->assertFalse(WfsPaging::isLastPage(startIndex: 100, returned: 100, matched: 300, pageSize: $size));
        $this->assertTrue(WfsPaging::isLastPage(startIndex: 0, returned: null, matched: null, pageSize: $size), 'no counts at all: cannot continue safely');
    }

    public function testPicksIdLikePropertyFromDescribeFeatureType(): void
    {
        $xsd = <<<'XML'
<?xml version="1.0"?>
<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:gml="http://www.opengis.net/gml/3.2">
  <xsd:complexType name="statesType">
    <xsd:complexContent>
      <xsd:extension base="gml:AbstractFeatureType">
        <xsd:sequence>
          <xsd:element name="the_geom" type="gml:MultiSurfacePropertyType" minOccurs="0"/>
          <xsd:element name="STATE_NAME" type="xsd:string" minOccurs="0"/>
          <xsd:element name="objectid" type="xsd:int"/>
          <xsd:element name="STATE_FIPS" type="xsd:string"/>
        </xsd:sequence>
      </xsd:extension>
    </xsd:complexContent>
  </xsd:complexType>
</xsd:schema>
XML;
        $this->assertSame('objectid', WfsPaging::pickSortProperty($xsd));
    }

    public function testSortPropertyPrefersExactIdNamesOverSuffixMatches(): void
    {
        $xsd = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema"><xsd:element name="owner_id" type="xsd:int"/><xsd:element name="fid" type="xsd:long"/></xsd:schema>';
        $this->assertSame('fid', WfsPaging::pickSortProperty($xsd));
        $xsd = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema"><xsd:element name="name" type="xsd:string"/><xsd:element name="bygning_id" type="xsd:string"/></xsd:schema>';
        $this->assertSame('bygning_id', WfsPaging::pickSortProperty($xsd));
    }

    public function testNoSortPropertyWhenNothingIdLike(): void
    {
        $xsd = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema"><xsd:element name="geom" type="gml:PointPropertyType"/><xsd:element name="name" type="xsd:string"/></xsd:schema>';
        $this->assertNull(WfsPaging::pickSortProperty($xsd));
        $this->assertNull(WfsPaging::pickSortProperty('not xml at all'));
    }

    public function testDescribeFeatureTypeUrl(): void
    {
        $p = WfsPaging::detect(self::URL . '&outputFormat=GML3&count=50');
        $this->assertSame(
            'https://wfs.example.com/geoserver/wfs?service=WFS&version=2.0.0&request=DescribeFeatureType&typeNames=topp%3Astates',
            $p->describeFeatureTypeUrl()
        );
    }
}
