<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\Authorization;
use Codeception\Test\Unit;

class TokenAuthTest extends Unit
{
    protected UnitTester $tester;

    protected Authorization $auth;
    protected function _before(): void
    {
        $claims = json_decode('{
                      "exp": 1767907125,
                      "iat": 1767906825,
                      "jti": "adab4ac4-5536-1fe6-3dc4-f70af2dcf471",
                      "iss": "https://keycloak.geopartner.dk/realms/geopartner-stage",
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
                      "preferred_username": "rgb@geopartner.dk",
                      "given_name": "Rene",
                      "family_name": "Giovanni Borella",
                      "email": "rgb@geopartner.dk",
                      "superuser": "*"
                    }');

        $customMap = [
            "groups->_Sec_GISCAD" => [
                "__membership" => ["user1"],
                "__read" => [],
                "__write" => []
            ],
            "groups->_sec_GPkort_Read" => [],
            "groups->_sec_GPkort_User" => [],
            "groups->_sec_GPkort_Write" => [],
            "groups->something_else" => []
        ];
        $this->auth = new Authorization(claims: $claims, user: new \app\models\User(), customMap: $customMap );
    }
    protected function _after(): void
    {
    }

    public function testTokenAuth(): void {

        $this->auth->set();

    }
}