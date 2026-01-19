<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\ClaimAcl;
use Codeception\Test\Unit;

class ClaimAclTest extends Unit
{
    protected UnitTester $tester;

    protected ClaimAcl $auth;
    protected stdClass $claims;
    protected function _before(): void
    {
        $this->claims = json_decode('{
                      "exp": 1767907125,
                      "iat": 1767906825,
                      "aud": "gc2",
                      "sub": "62efd2a9-c6bf-44e6-9002-38d526c90fb5",
                      "typ": "ID",
                      "azp": "gc2",
                      "sid": "872f68e6-cb98-cb0f-9e60-9853d2ad81a5",
                      "acr": "1",
                      "database": "*",
                      "email_verified": true,
                      "organization": [
                        "Geopartner"
                      ],
                      "name": "Rene Giovanni Borella",
                      "groups": [
                        "_Sec_GISCAD",
                        "_sec_GPkort_Read",
                        "_sec_GPkort_User",
                        "_sec_GPkort_Write"
                      ],
                      "preferred_username": "martin",
                      "given_name": "Rene",
                      "family_name": "hoegh",
                      "email": "mh@mapcentia.com",
                      "superuser": "*",
                      "something_else": ["role1", "role2"],
                      "something_more": "me"
                    }');

        $customMap = [
            "groups->random_group" => [
                "__membership" => ["*"],
                "__read" => [],
                "__write" => []
            ],
            "something_else->role2" => [
                "__membership" => ["rgb@geopartner.dk"], // eller ["*"]
                "__write" => ["sdchema.table1"],
            ],
            "something_else->role3" => [
                "__membership" => ["rgb@geopartner.dk"], // eller ["*"]
                "__read" => ["schema.table1"],
            ],
            "something_more->me" => [
                "__membership" => ["rgb@geopartner.dk"], // eller ["*"]
                "__read" => ["schema.table1"],
            ],

        ];
        $this->auth = new ClaimAcl($customMap);
    }
    protected function _after(): void
    {
    }

    public function testTokenAuth(): void {

        $perm = $this->auth->allTablePermissions($this->claims);
        $perm2 = $this->auth->allMembershipKeys($this->claims);
        $perm1 = $this->auth->permissionsForTable($this->claims, "schema.table1"); // read=true hvis claim-path findes + membership

        codecept_debug($perm);
        codecept_debug($perm2);
//        codecept_debug($perm1);
//        $perm = $this->auth->permissions($this->claims, "groups->_Sec_GISCAD");
//        codecept_debug($perm);

    }
}