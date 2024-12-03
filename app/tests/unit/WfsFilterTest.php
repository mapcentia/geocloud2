<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use Codeception\Test\Unit;

class WfsFilterTest extends Unit
{
    protected UnitTester $tester;

    protected function _before(): void
    {
    }
    public function testNotFilter(): void {
        include_once dirname(__DIR__) . '/../wfs/server.php';

        $xml = '<ogc:PropertyIsEqualTo xmlns:ogc="http://www.opengis.net/ogc">
  <ogc:PropertyName>gid</ogc:PropertyName>
  <ogc:Literal>1</ogc:Literal>
</ogc:PropertyIsEqualTo>';

    }

}