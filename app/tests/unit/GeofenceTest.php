<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\UserFilter;
use app\models\Geofence;
use Codeception\Test\Unit;

class GeofenceTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testAuthorizeShouldFilterRules()
    {
        $rules = [
            [
                "username" => "silke",
                "layer" => "*",
                "service" => "*",
                "iprange" => "*",
                "request" => "*",
                "schema" => "*",
                "access" => "limit",
                "read_filter" => null,
                "write_filter" => "userid=1",
            ],
            [
                "username" => "*",
                "layer" => "*",
                "service" => "*",
                "iprange" => "*",
                "request" => "*",
                "schema" => "*",
                "access" => "deny",
                "read_filter" => null,
                "write_filter" => null,
            ],
        ];

        $userFilter = new UserFilter("silke", "*", "*", "*", "*", "test.test");
        $geofence = new Geofence($userFilter);
        $response = $geofence->authorize($rules);

        $this->assertContains("limit", $response);
        $this->assertContains("userid=1", $response["filters"]);
//        print_r($response);
//        die();
    }
}