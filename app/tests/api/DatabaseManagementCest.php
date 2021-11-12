<?php

class DatabaseManagementCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userAuthCookie;
    private $userApiKey;
    private $subUserAuthCookie;
    private $subUserName;
    private $subUserEmail;
    private $userId;
    private $subUserId;
    private $subUserApiKey;


    private $userName1;
    private $userEmail1;
    private $userAuthCookie1;
    private $subUserAuthCookie1;
    private $subUserName1;
    private $subUserEmail1;
    private $userId1;
    private $subUserId1;

    private $userName2;
    private $userEmail2;
    private $userAuthCookie2;
    private $subUserAuthCookie2;
    private $subUserName2;
    private $subUserEmail2;
    private $userId2;
    private $subUserId2;

    public function __construct()
    {
        $buildId = getenv("BUILD_ID") ?? $this->date = new DateTime();
        $this->date = new DateTime();

        $this->userName = 'Database test super user name ' . ($buildId ?: $this->date->getTimestamp());
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'databasetest' . ($buildId ?: $this->date->getTimestamp()) . '@example.com';
        $this->subUserName = 'Database test sub user name ' . ($buildId ?: $this->date->getTimestamp());
        $this->subUserEmail = 'databasesubtest' . ($buildId ?: $this->date->getTimestamp()) . '@example.com';

        $this->userName1 = 'Another database test super user name ' . ($buildId ?: $this->date->getTimestamp());
        $this->userEmail1 = 'anotherdatabasetest' . ($buildId ?: $this->date->getTimestamp()) . '@example.com';
        $this->subUserName1 = 'Database test sub user name ' . ($buildId ?: $this->date->getTimestamp());
        $this->subUserEmail1 = 'anotherdatabasesubtest' . ($buildId ?: $this->date->getTimestamp()) . '@example.com';

        $this->userName2 = 'Second another database test super user name ' . ($buildId ?: $this->date->getTimestamp());
        $this->userEmail2 = 'secondanotherdatabasetest' . ($buildId ?: $this->date->getTimestamp()) . '@example.com';
        $this->subUserName2 = 'Second database test sub user name ' . ($buildId ?: $this->date->getTimestamp());
        $this->subUserEmail2 = 'anotherdatabasesubtest' . ($buildId ?: $this->date->getTimestamp()) . '@example.com';
    }

    public function shouldPrepareForTestFirstUser(\ApiTester $I)
    {
        // Create a super and subuser
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->userName,
            'email' => $this->userEmail,
            'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->userId = $response->data->screenname;

        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId,
            'password' => $this->password,
            'schema' => 'public',
        ]));
        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->userAuthCookie = $sessionCookie;
        $response = json_decode($I->grabResponse());
        $this->userApiKey = $response->data->api_key;


        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->subUserName,
            'email' => $this->subUserEmail,
            'password' => $this->password,
            'subuser' => true,
            'createschema' => true
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->subUserId = $response->data->screenname;
    }

    public function shouldStartSessionWithFirstSubuser(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->subUserId,
            'password' => $this->password,
            'schema' => 'public',
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->subUserAuthCookie = $sessionCookie;
        $response = json_decode($I->grabResponse());
        $this->subUserApiKey = $response->data->api_key;

    }

    public function shouldPrepareForTestSecondUser(\ApiTester $I)
    {
        // Create another super and subuser
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->userName1,
            'email' => $this->userEmail1,
            'password' => $this->password,
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->userId1 = $response->data->screenname;

        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId1,
            'password' => $this->password,
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->userAuthCookie1 = $sessionCookie;

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie1);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->subUserName1,
            'email' => $this->subUserEmail1,
            'password' => $this->password,
            'subuser' => true,
            'createschema' => true
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->subUserId1 = $response->data->screenname;

        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->subUserId1,
            'password' => $this->password,
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->subUserAuthCookie1 = $sessionCookie;
    }

    public function shouldPrepareForTestThirdUser(\ApiTester $I)
    {
        // Create another super and subuser
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->userName2,
            'email' => $this->userEmail2,
            'password' => $this->password,
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->userId2 = $response->data->screenname;

        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId2,
            'password' => $this->password,
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->userAuthCookie2 = $sessionCookie;

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie2);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->subUserName2,
            'email' => $this->subUserEmail2,
            'password' => $this->password,
            'subuser' => true,
            'createschema' => true
        ]));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->subUserId2 = $response->data->screenname;

        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->subUserId2,
            'password' => $this->password,
        ]));

        $sessionCookie = $I->capturePHPSESSID();
        $I->assertFalse(empty($sessionCookie));
        $this->subUserAuthCookie2 = $sessionCookie;
    }

    public function shouldListSchemasForSuperUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/database/schemas');

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'data' => [
                ['schema' => $this->subUserId],
                ['schema' => 'public']
            ]
        ]);
    }

    public function shouldListDatabasesForSubUserUsingName(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/database/search?userIdentifier=' . urlencode($this->subUserName));

        $response = json_decode($I->grabResponse());
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->assertEquals(2, sizeof($response->databases));
    }

    public function shouldListDatabasesForSubUserUsingNameAndSearchingForUsersWithSameEmail(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendGET('/api/v2/database/search?userIdentifier=' . urlencode($this->subUserEmail1));

        $response = json_decode($I->grabResponse());

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->assertEquals(2, sizeof($response->databases));

        $I->assertEquals($response->databases[0]->parentdb, $this->userId1);
        $I->assertEquals($response->databases[1]->parentdb, $this->userId2);
    }

    public function shouldSetBasicAuthPasswordForSubUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->subUserAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/controllers/setting/pw', "pw=" . $this->password);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Password saved',
        ]);
    }

    public function shouldSetBasicAuthPasswordForSuperUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->subUserAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/controllers/setting/pw', "pw=" . $this->password);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Password saved',
        ]);
    }

    public function shouldUploadGmlFile(\ApiTester $I)
    {
        $data = ["key" => "value"];
        $files = [
            "file" => '/var/www/geocloud2/app/tests/_data/Parkeringsomraade.gml',
        ];
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendPOST('/controllers/upload/vector', $data, $files);
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'File uploaded',
        ]);
    }

    public function shouldProcessGmlFile(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendGET('/controllers/upload/processvector?srid=25832&file=Parkeringsomraade.gml&name=Parkeringsomraade&type=polygon&encoding=UTF8&ignoreerrors=false&overwrite=false&append=false&delete=false');
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'type' => 'POLYGON',
        ]);
    }

    // *************************************
    // Start of testing read/write access
    // *************************************

    // Super user SQL API request to unprotected data source from outside session
    public function shouldGetDataFromSqlApiAsSuperUserOutsideSession(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/sql/' . $this->userId, json_encode(
            [
                'q' => 'SELECT * FROM public.parkeringsomraade LIMIT 1',
                'key' => 'dymmy'
            ]
        ));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
        ]);
    }

    // Super user WFS-t request to unprotected data source from outside session
    public function shouldGetDataFromWfstAsSuperUserOutsideSession(\ApiTester $I)
    {
        $I->sendGET('/wfs/' . $this->userId . '/public/25832?SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&TYPENAME=parkeringsomraade');
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsXml();
        $I->seeXmlResponseMatchesXpath('/wfs:FeatureCollection/gml:featureMember');
    }

    // Set Read and Write protection on the layer
    public function shouldChangeTheAuthenticationLevelFromWriteToReadwrite(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendPUT('/controllers/layer/records/public.parkeringsomraade.the_geom', json_encode([
            'data' => [
                "authentication" => "Read/write",
                "_key_" => "public.parkeringsomraade.the_geom",
            ],
        ]));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Row updated',
        ]);
    }

    // Super user SQL API request to protected data source from outside session
    public function shouldNotGetDataFromSqlApiAsSuperUserOutsideSession(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/sql/' . $this->userId, json_encode(
            [
                'q' => 'SELECT * FROM public.parkeringsomraade LIMIT 1',
                'key' => 'dymmy'
            ]
        ));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
        ]);
    }

    // WFS-t tests start on protected layers

    // Super user WFS-t request to protected data source from outside session
    public function shouldNotGetDataFromWfstAsSuperUserOutsideSession(\ApiTester $I)
    {
        $I->sendGET('/wfs/' . $this->userId . '/public/25832?SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&TYPENAME=parkeringsomraade');
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::UNAUTHORIZED);
    }

    // Sub user WFS-t request to protected data source from outside session
    public function shouldNotGetDataFromWfstAsSubUserOutsideSession(\ApiTester $I)
    {
        $I->sendGET('/wfs/' . $this->subUserId . "@" . $this->userId . '/public/25832?SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&TYPENAME=parkeringsomraade');
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsXml();
        $I->canSeeResponseContains('ServiceExceptionReport');
    }

    // Super user WFS-t request to protected data source from inside session
    public function shouldGetDataFromWfstAsSuperUserInsideSession(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendGET('/wfs/' . $this->userId . '/public/25832?SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&TYPENAME=parkeringsomraade');
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsXml();
        $I->seeXmlResponseMatchesXpath('/wfs:FeatureCollection/gml:featureMember');
    }

    // Sub user WFS-t request to protected data source from inside session
    public function shouldNotGetDataFromWfstAsSubUserInsideSession(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->subUserAuthCookie);
        $I->sendGET('/wfs/' . $this->subUserId . "@" . $this->userId . '/public/25832?SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&TYPENAME=parkeringsomraade');
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsXml();
        $I->canSeeResponseContains('ServiceExceptionReport');
    }

    // WFS-t tests end

    // Super user SQL API request to protected data source with API key
    public function shouldGetDataFromSqlApiAsSuperUserWithApiKey(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/sql/' . $this->userId, json_encode(
            [
                'q' => 'SELECT * FROM public.parkeringsomraade LIMIT 1',
                'key' => $this->userApiKey
            ]
        ));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
        ]);
    }

    // Super user SQL API request to protected data source from inside session
    public function shouldGetDataFromSqlApiAsSuperUserInsideSession(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendPOST('/api/v2/sql/' . $this->userId, json_encode(
            [
                'q' => 'SELECT * FROM public.parkeringsomraade LIMIT 1',
            ]
        ));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
        ]);
    }


    // Sub user SQL API request to protected data source from outside session
    public function shouldNotGetDataFromSqlApiAsSubUserOutsideSession(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/sql/' . $this->userId, json_encode(
            [
                'q' => 'SELECT * FROM public.parkeringsomraade LIMIT 1',
                'key' => 'dymmy'
            ]
        ));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
        ]);
    }


    // Sub user SQL API request to protected data source from inside session
    public function shouldNotGetDataFromSqlApiAsSubUserInsideSession(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->subUserAuthCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/sql/' . $this->subUserId . "@" . $this->userId, json_encode(
            [
                'q' => 'SELECT * FROM public.parkeringsomraade LIMIT 1',
            ]
        ));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
        ]);
    }


    // Set read privileges on data source to sub user
    public function shouldGiveReadPrivilegesToSubUser(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->userAuthCookie);
        $I->sendPUT('/controllers/layer/privileges', json_encode([
            'data' => [
                "subuser" => $this->subUserId,
                "privileges" => "read",
                "_key_" => "public.parkeringsomraade.the_geom",
            ],
        ]));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
            'message' => 'Privileges updates',
        ]);
    }

    // Sub user SQL API request to protected data source with wrong API key
    public function shouldNotGetDataFromSqlApiAsSubUserWithWrongApiKey(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/sql/' . $this->subUserId . "@" . $this->userId, json_encode(
            [
                'q' => 'SELECT * FROM public.parkeringsomraade LIMIT 1',
                'key' => 'dymmy'
            ]
        ));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::FORBIDDEN);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => false,
        ]);
    }

    // Sub user SQL API request to protected data source with right API key
    public function shouldGetDataFromSqlApiAsSubUserWithApiKey(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/sql/' . $this->subUserId . "@" . $this->userId, json_encode(
            [
                'q' => 'SELECT * FROM public.parkeringsomraade LIMIT 1',
                'key' => $this->subUserApiKey,
            ]
        ));
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
        ]);
    }

    // Sub user SQL API request to protected data source from within session
    public function shouldGetDataFromSqlApiAsSubUserFromWithinSession(\ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->subUserAuthCookie);
        $I->sendPOST('/api/v2/sql/' . $this->subUserId . "@" . $this->userId, json_encode(
            [
                'q' => 'SELECT * FROM public.parkeringsomraade LIMIT 1',
            ]
        ));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'success' => true,
        ]);
    }

    // Sub user WFS-t request to protected data source from inside session
    public function shouldGetDataFromWfstAsSubUserInsideSession(\ApiTester $I)
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->subUserAuthCookie);
        $I->sendGET('/wfs/' . $this->subUserId . "@" . $this->userId . '/public/25832?SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&TYPENAME=parkeringsomraade');
        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsXml();
        $I->seeXmlResponseMatchesXpath('/wfs:FeatureCollection/gml:featureMember');
    }

    // *************************************
    // End of testing read/write access
    // *************************************

}
