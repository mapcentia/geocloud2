<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use Codeception\Util\HttpCode;

/**
 * GET /api/v4/symbols/[name] (app/api/v4/controllers/Symbol.php): the built-in
 * MapServer symbols with the geometry a client draws previews from. Checks the
 * bare-array list, the one/many id forms, the error codes, that a sub-user may
 * read it, and that a token is required.
 */
class SymbolV4ApiCest
{
    private const NAMES = [
        'triangle', 'circle', 'square', 'star', 'hatch1', 'dashed1', 'arrow', 'arrow2', 'continue',
        'dashed-line-short', 'dashed-line-long', 'dash-dot', 'dash-dot-dot', 'dot-dot',
    ];

    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $subUserId;
    private $subToken;

    private function asSuper(ApiTester $I): void
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

    public function shouldPrepareUsersAndTokens(ApiTester $I)
    {
        $ts = time();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "symbol super $ts", 'email' => "symbolsuper$ts@example.com", 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        // Sub-user, created through the super-user's session
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sessionCookie = $I->capturePHPSESSID();

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "symbol sub $ts", 'email' => "symbolsub$ts@example.com",
            'password' => $this->password, 'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->subUserId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subToken = json_decode($I->grabResponse())->access_token;
    }

    public function shouldListAllSymbolsAsABareArray(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/symbols');
        $I->seeResponseCodeIs(HttpCode::OK);
        $raw = $I->grabResponse();
        $symbols = json_decode($raw, true);
        $I->assertIsArray($symbols);
        $I->assertTrue(array_is_list($symbols), 'the response is a bare JSON array');
        $I->assertSame(self::NAMES, array_column($symbols, 'name'), 'all 14, in mapfile order');

        // Real JSON booleans and numbers, not strings or 1/0
        $I->assertStringContainsString('"filled":true', $raw);
        $I->assertStringContainsString('"filled":false', $raw);

        $byName = array_column($symbols, null, 'name');
        foreach ($symbols as $symbol) {
            $I->assertContains($symbol['type'], ['vector', 'ellipse']);
            $I->assertIsBool($symbol['filled']);
            $I->assertNotEmpty($symbol['points']);
            foreach ($symbol['points'] as $point) {
                $I->assertCount(2, $point);
            }
        }
        $I->assertSame([0, 0.5], $byName['arrow']['anchorpoint']);
        $I->assertSame([0, 0.5], $byName['arrow2']['anchorpoint']);
        $I->assertArrayNotHasKey('anchorpoint', $byName['circle']);
        $I->assertFalse($byName['hatch1']['filled']);
        $I->assertSame([[0, 1], [1, 0]], $byName['hatch1']['points']);
        $I->assertSame([[1, 1]], $byName['circle']['points']);
        $I->assertSame([[20, 6], [2, 6]], $byName['dash-dot']['points']);
        $I->assertSame([0, 0.375], $byName['star']['points'][0]);
    }

    public function shouldReturnOneSymbolAsAnObject(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/symbols/star');
        $I->seeResponseCodeIs(HttpCode::OK);
        $symbol = json_decode($I->grabResponse(), true);
        $I->assertFalse(array_is_list($symbol), 'a single name returns a bare object');
        $I->assertSame('star', $symbol['name']);
        $I->assertSame('vector', $symbol['type']);
        $I->assertCount(10, $symbol['points']);
    }

    public function shouldReturnAListInRequestedOrder(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/symbols/star,arrow');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame(['star', 'arrow'], array_column(json_decode($I->grabResponse(), true), 'name'));

        // A repeated name is answered as asked, not an error
        $I->sendGET('/api/v4/symbols/star,star');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame(['star', 'star'], array_column(json_decode($I->grabResponse(), true), 'name'));
    }

    public function shouldReturn404ForAnUnknownName(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/symbols/star,nope');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'SYMBOL_NOT_FOUND']);
    }

    public function shouldReturn400ForAMalformedName(ApiTester $I)
    {
        $this->asSuper($I);
        foreach (['/api/v4/symbols/star,', '/api/v4/symbols/st%20ar', '/api/v4/symbols/star,,arrow'] as $uri) {
            $I->sendGET($uri);
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
            $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'INVALID_REQUEST']);
        }
    }

    public function subUserMayReadTheSymbols(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendGET('/api/v4/symbols');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertCount(14, json_decode($I->grabResponse(), true));
    }

    public function shouldRequireAToken(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->sendGET('/api/v4/symbols');
        // v4 answers a missing token with 400 "No token in header"
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['success' => false]);
    }

    public function shouldAnswerPreflight(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Access-Control-Request-Method', 'GET');
        $I->sendOPTIONS('/api/v4/symbols');
        $I->seeResponseCodeIsSuccessful();
        $I->deleteHeader('Access-Control-Request-Method');
    }

    public function shouldRejectWrite(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/symbols', json_encode([]));
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
        $I->seeResponseContainsJson(['errorCode' => 'NOT_ACCEPTABLE']);
    }
}
