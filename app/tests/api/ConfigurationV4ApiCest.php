<?php

use Codeception\Util\HttpCode;

/**
 * Exercises the v4 Configurations API:
 *   - app/api/v4/controllers/Configuration.php       (token, database owner only)
 *   - app/api/v4/controllers/ConfigurationPublic.php (anonymous read of published)
 * and that rows are interchangeable with the v2 Configuration API, which the
 * Dashboard keeps using on the same settings.key_value rows.
 */
class ConfigurationV4ApiCest
{
    private const string BASE = '/api/v4/configurations';

    private $date;
    private $password = 'A1abcabcabc';
    private $userId;        // owner screen name (== database)
    private $token;         // owner access token
    private $subToken;      // sub-user access token
    private $sessionCookie; // owner's v2 session, as the Dashboard has
    private $v2Key;         // key of a configuration created through v2

    private $published;     // key of a published configuration
    private $draftA;        // keys of two unpublished configurations
    private $draftB;

    // Unicode, slashes and odd whitespace: must survive byte for byte.
    private $body = "{ \"a\":\"æ/ø\" ,\n\"b\":[1 ,2]}";

    public function __construct()
    {
        $this->date = new DateTime();
    }

    private function asOwner(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function asSub(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subToken);
    }

    private function seeError(ApiTester $I, int $status, ?string $errorCode = null): void
    {
        $I->seeResponseCodeIs($status);
        if ($errorCode !== null) {
            $I->seeResponseContainsJson(['errorCode' => $errorCode]);
        }
    }

    /** @return array<mixed> */
    private function json(ApiTester $I): array
    {
        return json_decode($I->grabResponse(), true);
    }

    private function getOne(ApiTester $I, string $key): array
    {
        $this->asOwner($I);
        $I->sendGET(self::BASE . '/' . $key);
        $I->seeResponseCodeIs(HttpCode::OK);
        return $this->json($I);
    }

    public function shouldPrepareUsersAndTokens(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'conf owner ' . $ts,
            'email' => 'confowner' . $ts . '@example.com',
            'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        // Sub user (created through the owner's session)
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->sessionCookie = $I->capturePHPSESSID();

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->sessionCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'conf sub ' . $ts,
            'email' => 'confsub' . $ts . '@example.com',
            'password' => $this->password,
            'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $subUserId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subToken = json_decode($I->grabResponse())->access_token;
    }

    // ---- management (token, owner) ----------------------------------------

    public function shouldCreateOne(ApiTester $I)
    {
        $this->asOwner($I);
        $I->sendPOST(self::BASE, json_encode([
            'name' => 'My map', 'published' => true, 'description' => 'd', 'body' => $this->body,
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $location = $I->grabHttpHeader('Location');
        $I->assertMatchesRegularExpression('#^/api/v4/configurations/configuration_my_map_[A-Za-z0-9_]+$#', $location);
        $this->published = basename($location);
    }

    public function shouldCreateMany(ApiTester $I)
    {
        $this->asOwner($I);
        $I->sendPOST(self::BASE, json_encode([
            ['name' => 'Draft A', 'published' => false, 'description' => '', 'body' => '{}'],
            ['name' => 'Draft B', 'published' => false, 'description' => '', 'body' => '[]'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $keys = explode(',', basename($I->grabHttpHeader('Location')));
        $I->assertCount(2, $keys);
        [$this->draftA, $this->draftB] = $keys;
        $I->assertStringStartsWith('configuration_draft_a_', $this->draftA);
        $I->assertStringStartsWith('configuration_draft_b_', $this->draftB);
    }

    public function shouldGetOne(ApiTester $I)
    {
        $I->assertSame([
            'key' => $this->published,
            'name' => 'My map',
            'description' => 'd',
            'published' => true,
            'body' => $this->body,
        ], $this->getOne($I, $this->published));
    }

    public function shouldGetList(ApiTester $I)
    {
        $this->asOwner($I);
        $I->sendGET(self::BASE . '/' . $this->published . ',' . $this->draftA);
        $I->seeResponseCodeIs(HttpCode::OK);
        $data = $this->json($I);
        $I->assertTrue(array_is_list($data));
        $I->assertCount(2, $data);
    }

    public function shouldListAll(ApiTester $I)
    {
        $this->asOwner($I);
        $I->sendGET(self::BASE);
        $I->seeResponseCodeIs(HttpCode::OK);
        $data = $this->json($I);
        $I->assertTrue(array_is_list($data));
        $keys = array_column($data, 'key');
        foreach ([$this->published, $this->draftA, $this->draftB] as $key) {
            $I->assertContains($key, $keys);
        }
    }

    public function shouldPatch(ApiTester $I)
    {
        $this->asOwner($I);
        $I->stopFollowingRedirects();
        $I->sendPATCH(self::BASE . '/' . $this->published, json_encode(['name' => 'Renamed']));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->assertSame(self::BASE . '/' . $this->published, $I->grabHttpHeader('Location'));

        $after = $this->getOne($I, $this->published);
        $I->assertSame('Renamed', $after['name']);
        $I->assertSame($this->published, $after['key']);
        $I->assertSame($this->body, $after['body']);
    }

    public function shouldRejectBadBody(ApiTester $I)
    {
        $this->asOwner($I);
        foreach (['{a:1}', 'null'] as $bad) {
            $I->sendPOST(self::BASE, json_encode(['name' => 'Bad', 'published' => false, 'description' => '', 'body' => $bad]));
            $this->seeError($I, HttpCode::BAD_REQUEST, 'INVALID_REQUEST');
        }
        $I->sendPATCH(self::BASE . '/' . $this->published, json_encode(['body' => '1']));
        $this->seeError($I, HttpCode::BAD_REQUEST, 'INVALID_REQUEST');

        $I->assertSame($this->body, $this->getOne($I, $this->published)['body']);
        // Nothing was stored for the rejected POSTs
        $I->sendGET(self::BASE);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNotContains('Bad', array_column($this->json($I), 'name'));
    }

    public function shouldRejectBadFields(ApiTester $I)
    {
        $before = $this->getOne($I, $this->published);

        $this->asOwner($I);
        $I->sendPOST(self::BASE, json_encode(['published' => false, 'description' => '', 'body' => '{}']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendPOST(self::BASE, json_encode(['name' => 'Bad', 'published' => false, 'description' => '', 'body' => '{}', 'key' => 'configuration_mine_1']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendPATCH(self::BASE . '/' . $this->published, json_encode(['owner' => 'x']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendPATCH(self::BASE . '/' . $this->published, json_encode(['published' => 'true']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->assertSame($before, $this->getOne($I, $this->published));
        $I->sendGET(self::BASE);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNotContains('Bad', array_column($this->json($I), 'name'));
    }

    public function shouldRejectBadKey(ApiTester $I)
    {
        $this->asOwner($I);
        $I->sendGET(self::BASE . '/state_snapshot_1');
        $this->seeError($I, HttpCode::BAD_REQUEST, 'INVALID_REQUEST');
        $I->sendGET(self::BASE . '/' . $this->published . '.json');
        $this->seeError($I, HttpCode::BAD_REQUEST, 'INVALID_REQUEST');
    }

    public function shouldReturn404(ApiTester $I)
    {
        $this->asOwner($I);
        $I->sendGET(self::BASE . '/configuration_nope_1');
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');
        $I->sendPATCH(self::BASE . '/configuration_nope_1', json_encode(['name' => 'x']));
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');
        $I->sendGET(self::BASE . '/' . $this->published . ',configuration_nope_1');
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');
    }

    public function shouldForbidSubUser(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendGET(self::BASE);
        $this->seeError($I, HttpCode::FORBIDDEN, 'SUPER_USER_ONLY');
        $I->sendGET(self::BASE . '/' . $this->published);
        $this->seeError($I, HttpCode::FORBIDDEN, 'SUPER_USER_ONLY');
        $I->sendPOST(self::BASE, json_encode(['name' => 'Sub', 'published' => true, 'description' => '', 'body' => '{}']));
        $this->seeError($I, HttpCode::FORBIDDEN, 'SUPER_USER_ONLY');
        $I->sendPATCH(self::BASE . '/' . $this->published, json_encode(['name' => 'Sub']));
        $this->seeError($I, HttpCode::FORBIDDEN, 'SUPER_USER_ONLY');
        $I->sendDELETE(self::BASE . '/' . $this->published);
        $this->seeError($I, HttpCode::FORBIDDEN, 'SUPER_USER_ONLY');

        $I->assertSame('Renamed', $this->getOne($I, $this->published)['name']);
    }

    public function shouldRequireToken(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->sendGET(self::BASE);
        // A missing token is rejected by the v4 front controller before any
        // controller runs; it answers 400 for every token route.
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->assertStringNotContainsString($this->published, $I->grabResponse());
    }

public function shouldRejectNullFields(ApiTester $I)
    {
        $before = $this->getOne($I, $this->published);
        $this->asOwner($I);
        // The Dashboard cannot save a configuration holding nulls
        $I->sendPOST(self::BASE, json_encode(['name' => 'Bad', 'published' => false, 'description' => null, 'body' => '{}']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendPATCH(self::BASE . '/' . $this->published, json_encode(['published' => null]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendPATCH(self::BASE . '/' . $this->published, json_encode(['description' => null]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->assertSame($before, $this->getOne($I, $this->published));
    }

    public function shouldRejectBodiesThatAreNotObjects(ApiTester $I)
    {
        $before = $this->getOne($I, $this->published);
        $good = ['name' => 'Bad', 'published' => false, 'description' => '', 'body' => '{}'];
        $this->asOwner($I);
        foreach (['null', '5', '"x"', '[null]', '[5]', json_encode([$good, null])] as $payload) {
            $I->sendPOST(self::BASE, $payload);
            $this->seeError($I, HttpCode::BAD_REQUEST, 'INVALID_REQUEST');
        }
        foreach (['null', '5', '{}', '[]', '[{"name":"x"}]'] as $payload) {
            $I->sendPATCH(self::BASE . '/' . $this->published, $payload);
            $this->seeError($I, HttpCode::BAD_REQUEST, 'INVALID_REQUEST');
        }
        // One bad item in a list stores none of them
        $I->sendPOST(self::BASE, json_encode([$good, ['name' => 'Bad', 'published' => false, 'description' => '', 'body' => '{a:1}']]));
        $this->seeError($I, HttpCode::BAD_REQUEST, 'INVALID_REQUEST');

        $I->assertSame($before, $this->getOne($I, $this->published));
        $I->sendGET(self::BASE);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNotContains('Bad', array_column($this->json($I), 'name'));
    }

    // ---- anonymous read ----------------------------------------------------

    private function asAnonymous(ApiTester $I): void
    {
        $I->deleteHeader('Authorization');
        $I->deleteHeader('Content-Type');
        $I->haveHttpHeader('Accept', 'application/json');
    }

    private function publicBase(): string
    {
        return self::BASE . '/database/' . $this->userId;
    }

    public function anonShouldListPublishedOnly(ApiTester $I)
    {
        $this->asAnonymous($I);
        $I->sendGET($this->publicBase());
        $I->seeResponseCodeIs(HttpCode::OK);
        $data = $this->json($I);
        $I->assertTrue(array_is_list($data));
        $keys = array_column($data, 'key');
        $I->assertContains($this->published, $keys);
        $I->assertNotContains($this->draftB, $keys);
        $I->assertNotContains($this->draftA, $keys);
    }

    public function anonShouldGetPublished(ApiTester $I)
    {
        $this->asAnonymous($I);
        $I->sendGET($this->publicBase() . '/' . $this->published);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame([
            'key' => $this->published,
            'name' => 'Renamed',
            'description' => 'd',
            'published' => true,
            'body' => $this->body,
        ], $this->json($I));
    }

    public function anonShouldGetJsonFile(ApiTester $I)
    {
        $this->asAnonymous($I);
        $I->sendGET($this->publicBase() . '/' . $this->published . '.json');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertStringStartsWith('application/json', $I->grabHttpHeader('Content-Type'));
        $I->assertSame('attachment; filename=' . $this->published . '.json', $I->grabHttpHeader('Content-Disposition'));
        $I->assertSame($this->body, $I->grabResponse());
    }

    public function anonShouldNotSeeDraft(ApiTester $I)
    {
        $this->asAnonymous($I);
        $I->sendGET($this->publicBase() . '/' . $this->draftB);
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');
        $I->sendGET($this->publicBase() . '/' . $this->draftB . '.json');
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');
    }

    public function anonShouldGet404ForUnknown(ApiTester $I)
    {
        $this->asAnonymous($I);
        $I->sendGET($this->publicBase() . '/' . $this->draftB);
        $draft = $this->json($I);
        $I->sendGET($this->publicBase() . '/configuration_nope_1');
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');
        // An unpublished key must not be distinguishable from one that does not exist
        $I->assertSame($draft, $this->json($I));
    }

    public function anonShouldRejectForeignKey(ApiTester $I)
    {
        $this->asAnonymous($I);
        $I->sendGET($this->publicBase() . '/state_snapshot_1');
        $this->seeError($I, HttpCode::BAD_REQUEST, 'INVALID_REQUEST');
        $I->sendGET($this->publicBase() . '/' . $this->published . ',' . $this->draftB);
        $this->seeError($I, HttpCode::BAD_REQUEST, 'INVALID_REQUEST');
    }

    public function anonShouldSkipForeignRow(ApiTester $I)
    {
        // A row under the prefix that is not a configuration
        $junk = 'configuration_junk_' . $this->date->getTimestamp();
        $this->asOwner($I);
        $I->sendPOST('/api/v4/keyvalue/' . $junk, json_encode(['value' => ['foo' => 1, 'published' => true], 'public' => true]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $this->asAnonymous($I);
        $I->sendGET($this->publicBase());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNotContains($junk, array_column($this->json($I), 'key'));
        $I->sendGET($this->publicBase() . '/' . $junk);
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');
        $I->sendGET($this->publicBase() . '/' . $junk . '.json');
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');

        $this->asOwner($I);
        $I->sendGET(self::BASE);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNotContains($junk, array_column($this->json($I), 'key'));
        $I->sendDELETE('/api/v4/keyvalue/' . $junk);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

public function anonShouldFollowPublishedFlag(ApiTester $I)
    {
        $this->asOwner($I);
        $I->stopFollowingRedirects();
        $I->sendPATCH(self::BASE . '/' . $this->published, json_encode(['published' => false]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $this->asAnonymous($I);
        $I->sendGET($this->publicBase() . '/' . $this->published . '.json');
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');
        $I->sendGET($this->publicBase());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNotContains($this->published, array_column($this->json($I), 'key'));

        $this->asOwner($I);
        $I->sendPATCH(self::BASE . '/' . $this->published, json_encode(['published' => true]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $this->asAnonymous($I);
        $I->sendGET($this->publicBase() . '/' . $this->published . '.json');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame($this->body, $I->grabResponse());
    }

    public function subUserShouldNotPlantConfiguration(ApiTester $I)
    {
        // A sub-user can write any key through the keyvalue API; such a row
        // must not become a configuration.
        $planted = 'configuration_planted_' . $this->date->getTimestamp();
        $this->asSub($I);
        $I->sendPOST('/api/v4/keyvalue/' . $planted, json_encode([
            'value' => ['name' => 'Planted', 'published' => true, 'description' => '', 'body' => '{"x":1}', 'key' => $planted],
            'public' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $this->asAnonymous($I);
        $I->sendGET($this->publicBase());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNotContains($planted, array_column($this->json($I), 'key'));
        $I->sendGET($this->publicBase() . '/' . $planted . '.json');
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');

        $this->asOwner($I);
        $I->sendGET(self::BASE);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNotContains($planted, array_column($this->json($I), 'key'));
        $I->sendGET(self::BASE . '/' . $planted);
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');

        $this->asSub($I);
        $I->sendDELETE('/api/v4/keyvalue/' . $planted);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    public function anonShouldGet404ForDatabaseWithoutConfigurations(ApiTester $I)
    {
        // A database that exists but is not a GC2 database must look like one that does not exist
        $this->asAnonymous($I);
        $I->sendGET(self::BASE . '/database/postgres');
        $this->seeError($I, HttpCode::NOT_FOUND, 'DATABASE_NOT_FOUND');
        $I->sendGET(self::BASE . '/database/template0');
        $this->seeError($I, HttpCode::NOT_FOUND, 'DATABASE_NOT_FOUND');
        // A trailing newline is not part of a database name
        $I->sendGET($this->publicBase() . '%0A');
        $this->seeError($I, HttpCode::NOT_FOUND, 'DATABASE_NOT_FOUND');
    }

    public function anonShouldGet404ForUnknownDatabase(ApiTester $I)
    {
        $this->asAnonymous($I);
        $I->sendGET(self::BASE . '/database/no_such_db_' . $this->date->getTimestamp());
        $this->seeError($I, HttpCode::NOT_FOUND, 'DATABASE_NOT_FOUND');
        $I->sendGET(self::BASE . '/database/no_such_db_' . $this->date->getTimestamp() . '/' . $this->published . '.json');
        $this->seeError($I, HttpCode::NOT_FOUND, 'DATABASE_NOT_FOUND');
    }

    public function anonShouldIgnoreToken(ApiTester $I)
    {
        $this->asOwner($I);
        $I->sendGET($this->publicBase() . '/' . $this->draftB);
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');
        $I->sendGET($this->publicBase());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNotContains($this->draftB, array_column($this->json($I), 'key'));
    }

    public function anonShouldNotWrite(ApiTester $I)
    {
        $this->asAnonymous($I);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST($this->publicBase(), json_encode(['name' => 'x', 'published' => true, 'description' => '', 'body' => '{}']));
        $this->seeError($I, HttpCode::NOT_ACCEPTABLE, 'NOT_ACCEPTABLE'); // how v4 rejects a verb the controller does not declare
        $I->sendDELETE($this->publicBase() . '/' . $this->published);
        $this->seeError($I, HttpCode::NOT_ACCEPTABLE, 'NOT_ACCEPTABLE'); // how v4 rejects a verb the controller does not declare
        $this->getOne($I, $this->published);
    }

    public function anonPreflight(ApiTester $I)
    {
        $this->asAnonymous($I);
        $I->haveHttpHeader('Origin', 'https://vidi.example.com');
        $I->haveHttpHeader('Access-Control-Request-Method', 'GET');
        $I->sendOPTIONS($this->publicBase() . '/' . $this->published . '.json');
        $I->seeResponseCodeIsSuccessful();
        $I->assertSame('', $I->grabResponse());
        $I->seeHttpHeader('Access-Control-Allow-Origin');

        // A preflight for a verb the route does not offer is refused
        $I->haveHttpHeader('Access-Control-Request-Method', 'DELETE');
        $I->sendOPTIONS($this->publicBase() . '/' . $this->published);
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
        $I->deleteHeader('Origin');
        $I->deleteHeader('Access-Control-Request-Method');
    }

    // ---- interchange with v2 (the Dashboard) -------------------------------

    private function asDashboard(ApiTester $I): void
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->sessionCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
    }

    public function v2ShouldSeeV4Row(ApiTester $I)
    {
        $this->asDashboard($I);
        $I->sendGET('/api/v2/configuration/' . $this->userId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
        $items = array_column($this->json($I)['data'], 'value', 'key');
        // Unpublished v4 rows are listed for the owner too
        $I->assertArrayHasKey($this->draftB, $items);
        $I->assertArrayHasKey($this->published, $items);
        $value = json_decode($items[$this->published], true);
        // The column is jsonb, which orders keys itself; only the set matters
        $I->assertEqualsCanonicalizing(['name', 'published', 'description', 'body', 'key'], array_keys($value));
        $I->assertSame($this->body, $value['body']);
        $I->assertSame($this->published, $value['key']);
        $I->assertTrue($value['published']);
    }

    public function v2ShouldServeV4RowAsFile(ApiTester $I)
    {
        $this->asAnonymous($I);
        $I->sendGET('/api/v2/configuration/' . $this->userId . '/' . $this->published . '.json');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame($this->body, $I->grabResponse());
    }

    public function v2ShouldSaveV4Row(ApiTester $I)
    {
        // What the Dashboard does on save: PUT all four fields
        $this->asDashboard($I);
        $I->sendPUT('/api/v2/configuration/' . $this->userId . '/' . $this->draftB, json_encode([
            'name' => 'Draft B', 'published' => false, 'description' => 'saved in dashboard', 'body' => '[1,2]',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['success' => true]);
        $I->deleteHeader('Cookie');

        $after = $this->getOne($I, $this->draftB);
        $I->assertSame('saved in dashboard', $after['description']);
        $I->assertSame('[1,2]', $after['body']);
        $I->assertFalse($after['published']);
    }

    public function v4ShouldSeeV2Row(ApiTester $I)
    {
        $this->asDashboard($I);
        $I->sendPOST('/api/v2/configuration/' . $this->userId, json_encode([
            'name' => 'From v2', 'published' => true, 'description' => '', 'body' => '{"x": 1}',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->v2Key = $this->json($I)['data']['key'];
        $I->deleteHeader('Cookie');

        $I->assertSame([
            'key' => $this->v2Key, 'name' => 'From v2', 'description' => '', 'published' => true, 'body' => '{"x": 1}',
        ], $this->getOne($I, $this->v2Key));

        $this->asAnonymous($I);
        $I->sendGET($this->publicBase() . '/' . $this->v2Key . '.json');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame('{"x": 1}', $I->grabResponse());
    }

public function v4ShouldAddressV2KeyWithEmailName(ApiTester $I)
    {
        // v2 lets an e-mail-shaped name through to the key, so it holds '+', '@' and '-'
        $this->asDashboard($I);
        $I->sendPOST('/api/v2/configuration/' . $this->userId, json_encode([
            'name' => 'a+b@x-y.dk', 'published' => true, 'description' => '', 'body' => '{"mail": 1}',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $key = $this->json($I)['data']['key'];
        $I->deleteHeader('Cookie');
        $I->assertStringStartsWith('configuration_a+b@x-ydk_', $key);

        $I->assertSame('{"mail": 1}', $this->getOne($I, $key)['body']);
        $this->asAnonymous($I);
        $I->sendGET($this->publicBase() . '/' . $key . '.json');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame('{"mail": 1}', $I->grabResponse());
        $I->sendGET($this->publicBase() . '/' . rawurlencode($key));
        $I->seeResponseCodeIs(HttpCode::OK);

        $this->asOwner($I);
        $I->stopFollowingRedirects();
        $I->sendPATCH(self::BASE . '/' . $key, json_encode(['description' => 'mail']));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->sendGET($I->grabHttpHeader('Location'));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame('mail', $this->json($I)['description']);
        $I->sendDELETE(self::BASE . '/' . $key);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    public function v4PatchShouldKeepV2Body(ApiTester $I)
    {
        $this->asOwner($I);
        $I->stopFollowingRedirects();
        $I->sendPATCH(self::BASE . '/' . $this->v2Key, json_encode(['description' => 'edited in v4']));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);

        $this->asDashboard($I);
        $I->sendGET('/api/v2/configuration/' . $this->userId . '/' . $this->v2Key);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
        $data = $this->json($I)['data'];
        $I->assertSame($this->v2Key, $data['key']);
        $value = json_decode($data['value'], true);
        $I->assertSame('edited in v4', $value['description']);
        $I->assertSame('{"x": 1}', $value['body']);
        $I->assertSame('From v2', $value['name']);
        $I->assertTrue($value['published']);
        $I->assertSame($this->v2Key, $value['key']);
    }

    public function v4RowShouldBeStoredLikeV2Row(ApiTester $I)
    {
        // No owner on the row, exactly as the Dashboard leaves it
        foreach ([$this->published, $this->v2Key] as $key) {
            $this->asOwner($I);
            $I->sendGET('/api/v4/keyvalue/' . $key);
            $I->seeResponseCodeIs(HttpCode::OK);
            $row = $this->json($I);
            $I->assertNull($row['owner']);
            $I->assertFalse($row['public']);
        }
    }

    // ---- delete and cleanup ------------------------------------------------

    public function shouldNotDeleteOnBadList(ApiTester $I)
    {
        $this->asOwner($I);
        $I->sendDELETE(self::BASE . '/' . $this->draftA . ',configuration_nope_1');
        $this->seeError($I, HttpCode::NOT_FOUND, 'CONFIGURATION_NOT_FOUND');
        $this->getOne($I, $this->draftA);
    }

    public function shouldDeleteRepeated(ApiTester $I)
    {
        $this->asOwner($I);
        $I->sendDELETE(self::BASE . '/' . $this->draftA . ',' . $this->draftA);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET(self::BASE . '/' . $this->draftA);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldCleanUp(ApiTester $I)
    {
        $this->asOwner($I);
        $I->sendDELETE(self::BASE . '/' . $this->published . ',' . $this->draftB . ',' . $this->v2Key);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET(self::BASE);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame([], $this->json($I));
    }
}
