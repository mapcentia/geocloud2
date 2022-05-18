<?php

use app\inc\UserFilter;
use app\models\Geofence;
use Codeception\Test\Unit;

class GeofenceTest extends Unit
{
    /**
     * @var \UnitTester
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
                "ipaddress" => "*",
                "request" => "*",
                "schema" => "*",
                "access" => "limit",
                "read_filter" => null,
                "write_filter" => "userid=1",
                "read_spatial_filter" => null,
                "write_spatial_filter" => null,
            ],
            [
                "username" => "*",
                "layer" => "*",
                "service" => "*",
                "ipaddress" => "*",
                "request" => "*",
                "schema" => "*",
                "access" => "deny",
                "read_filter" => null,
                "write_filter" => null,
                "read_spatial_filter" => null,
                "write_spatial_filter" => null,
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