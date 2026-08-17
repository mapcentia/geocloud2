<?php

use Codeception\Util\HttpCode;

/**
 * The v2 and v4 SQL APIs deliberately serialize JSON/JSONB columns differently, and this
 * suite locks that contract in:
 *   - v2 returns the raw column text — a JSON *string*.
 *   - v4 returns the decoded value — a nested object/array.
 * The difference comes from the `convert_types` default: v2 defaults it to false, v4 to true.
 * Both report the same underlying column type (jsonb) in the response schema.
 */
class SqlJsonFormatApiCest
{
    private $date;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $cookie;

    // A jsonb value that is both an object and contains a nested array, so a string result
    // and a decoded result are unambiguously distinguishable.
    private $query = "SELECT '{\"a\":1,\"b\":[2,3]}'::jsonb AS j";
    private $expected = ['a' => 1, 'b' => [2, 3]];

    public function __construct()
    {
        $this->date = new DateTime();
    }

    public function shouldPrepare(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'sqljson_' . $ts, 'email' => 'sqljson' . $ts . '@example.com', 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        // v4 auth: OAuth bearer token.
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        // v2 auth: owner session cookie.
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->cookie = $I->capturePHPSESSID();
    }

    // v4 decodes JSONB into a nested object/array (convert_types defaults to true).
    public function shouldReturnNestedObjectInV4(ApiTester $I)
    {
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v4/sql', json_encode(['q' => $this->query, 'format' => 'json']));
        $I->seeResponseCodeIs(HttpCode::OK);

        $data = json_decode($I->grabResponse(), true);
        $I->assertSame('jsonb', $data['schema']['j']['type']);
        $value = $data['data'][0]['j'];
        $I->assertIsArray($value, 'v4 must return the JSONB column decoded, not as a string');
        $I->assertSame($this->expected, $value);
    }

    // v2 returns the JSONB column as its raw text — a string (convert_types defaults to false).
    public function shouldReturnStringInV2(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->cookie);
        $I->sendPOST('/api/v2/sql/' . $this->userId, json_encode(['q' => $this->query, 'format' => 'json']));
        $I->seeResponseCodeIs(HttpCode::OK);

        $data = json_decode($I->grabResponse(), true);
        $I->assertSame('jsonb', $data['schema']['j']['type']);
        $value = $data['data'][0]['j'];
        $I->assertIsString($value, 'v2 must return the JSONB column as a raw JSON string');
        // The string is valid JSON that decodes to the same structure v4 returns directly.
        $I->assertSame($this->expected, json_decode($value, true));
    }
}
