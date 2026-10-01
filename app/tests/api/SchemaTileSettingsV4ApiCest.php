<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use Codeception\Util\HttpCode;

/**
 * GET/PATCH/DELETE /api/v4/schemas/{schema}/tile — the settings of the merged
 * per-schema tileset (<schema> and <schema>.mvt).
 *
 * The settings outlive their schema on purpose (spec §3.3), so the interesting
 * cases are asymmetric: PATCH needs the schema to exist, GET and DELETE do not —
 * otherwise a row left behind by a dropped schema could never be inspected or
 * cleared through the API.
 *
 * Ordered and stateful, like the other v4 cests: shouldPrepare provisions, the
 * last test cleans up.
 */
class SchemaTileSettingsV4ApiCest
{
    private string $password = 'Abc12345!';
    private ?string $userId = null;
    private ?string $token = null;
    private ?string $subToken = null;
    private string $schema = 'tilesettings';
    private string $gone = 'tilesettingsgone';

    /**
     * PhpBrowser follows redirects by default, which would turn every PATCH's 303
     * into the 200 of the GET it points at — and the status under test is the 303
     * itself; following it is the client's choice, not the server's. The module
     * resets this between tests, so it belongs here rather than in shouldPrepare.
     */
    public function _before(ApiTester $I): void
    {
        $I->stopFollowingRedirects();
    }

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepare(ApiTester $I): void
    {
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => "tileset $ts", 'email' => "tileset$ts@example.com",
            'password' => $this->password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId,
            'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $this->asSuper($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        // A sub user of the same database, to prove the scope rather than assume it.
        $I->sendPOST('/api/v2/session/start', json_encode(['user' => $this->userId, 'password' => $this->password, 'schema' => 'public']));
        $cookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->sendPOST('/api/v2/user', json_encode(['name' => "tilesetsub $ts", 'email' => "tilesetsub$ts@example.com",
            'password' => $this->password, 'subuser' => true]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sub = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $sub,
            'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $this->subToken = json_decode($I->grabResponse())->access_token;
        $I->assertNotEmpty($this->subToken, 'without a sub-user token the scope test would pass for the wrong reason');
    }

    public function shouldFallBackBeforeAnythingIsStored(ApiTester $I): void
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse(), true);
        $I->assertSame(60, $body['ttl'], 'the fallback is this loop\'s 60, not layerSettings\' 30');
        $I->assertSame(3, $body['meta_size']);
        $I->assertSame(0, $body['meta_buffer']);
        $I->assertSame('PNG', $body['format']);
        $I->assertSame('MVT', $body['vector_format'], 'reported, but not configurable');
        $I->assertSame($this->schema, $body['title']);
        $I->assertTrue($body['schema_exists']);
        $I->assertSame([], (array)$body['_stored'], 'nothing is stored yet, so _stored must be empty');
        // An empty PHP array serialises as [], which breaks a generated client that
        // types _stored as the object the OpenAPI schema declares.
        $I->assertStringContainsString('"_stored":{}', $I->grabResponse(),
            '_stored must serialise as a JSON object even when empty');
    }

    /**
     * A form needs the default even for a field that IS stored — that is exactly
     * when it wants to say "default is 60" beside the value the user is about to
     * clear. The effective values cannot supply it: once a field is stored, the
     * effective value IS the stored one.
     */
    public function shouldReportTheDefaultsSeparately(ApiTester $I): void
    {
        $this->asSuper($I);
        $I->sendPATCH('/api/v4/schemas/' . $this->schema . '/tile', json_encode(['ttl' => 9999, 'cache' => 'disk']));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tile');
        $body = json_decode($I->grabResponse(), true);
        $I->assertSame(9999, $body['ttl'], 'the effective value is the stored one');
        $I->assertSame(60, $body['_defaults']['ttl'], 'and the default is still reported');
        $I->assertSame('sqlite', $body['_defaults']['cache']);
        $I->assertSame(3, $body['_defaults']['meta_size']);
        $I->assertSame($this->schema, $body['_defaults']['title'], 'the title defaults to the schema name');
        $I->assertNull($body['_defaults']['auto_expire']);
        // The keys a form can patch, and only those: vector_format is not settable.
        $I->assertEqualsCanonicalizing(
            ['cache', 'format', 'ttl', 'auto_expire', 'meta_size', 'meta_buffer', 's3_tile_set', 'title', 'abstract'],
            array_keys($body['_defaults']),
            '_defaults carries exactly the patchable keys');
        $I->sendDELETE('/api/v4/schemas/' . $this->schema . '/tile');
    }

    public function shouldStoreMergeAndReadBack(ApiTester $I): void
    {
        $this->asSuper($I);
        $I->sendPATCH('/api/v4/schemas/' . $this->schema . '/tile', json_encode(['cache' => 'disk', 'ttl' => 86400]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);

        $I->sendPATCH('/api/v4/schemas/' . $this->schema . '/tile', json_encode(['meta_size' => 5]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);

        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tile');
        $body = json_decode($I->grabResponse(), true);
        $I->assertSame('disk', $body['cache']);
        $I->assertSame(86400, $body['ttl']);
        $I->assertSame(5, $body['meta_size']);
        $I->assertEqualsCanonicalizing(['cache' => 'disk', 'ttl' => 86400, 'meta_size' => 5], (array)$body['_stored'],
            '_stored must carry only what was actually set');
    }

    public function shouldRemoveOneKeyWithAnExplicitNull(ApiTester $I): void
    {
        $this->asSuper($I);
        $I->sendPATCH('/api/v4/schemas/' . $this->schema . '/tile', json_encode(['ttl' => null]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tile');
        $body = json_decode($I->grabResponse(), true);
        $I->assertSame(60, $body['ttl'], 'removing ttl returns it to the fallback');
        $I->assertArrayNotHasKey('ttl', (array)$body['_stored']);
        $I->assertSame('disk', $body['cache'], 'the other keys survive');
    }

    /**
     * Review Focus 1 and 2. The config is ONE file per database, so a metatile of
     * 0 would make MapCache reject the whole document and take down every tileset
     * in it; s3_tile_set is interpolated into the S3 object URL.
     */
    public function shouldRefuseValuesThatWouldBreakTheConfig(ApiTester $I): void
    {
        $this->asSuper($I);
        foreach ([['meta_size' => 0], ['meta_size' => -1], ['meta_buffer' => -5], ['ttl' => 0],
                     ['s3_tile_set' => 'other/prefix'], ['s3_tile_set' => '../escape'], ['s3_tile_set' => 'with space'],
                     // libcurl normalises '.' and '..' away, so these would put the
                     // schema's tiles at the bucket root, on top of every other
                     // schema's objects in a shared bucket.
                     ['s3_tile_set' => '..'], ['s3_tile_set' => '.'], ['s3_tile_set' => '...'],
                     // Closing its own CDATA section would let a title inject
                     // MapCache configuration; the generator escapes it, and the
                     // API has no reason to accept it in the first place.
                     ['title' => 'a]]></title><cache>evil</cache>'], ['abstract' => 'x]]>y'],
                     ['cache' => 'redis'], ['format' => 'WEBP'], ['format' => 'JSON'],
                     // MVT is the .mvt tileset's only possible value, so accepting it
                     // as a setting would accept a value that cannot change anything.
                     ['format' => 'MVT'],
                     ['theme_column' => 'x']] as $body) {
            $I->sendPATCH('/api/v4/schemas/' . $this->schema . '/tile', json_encode($body));
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST, 'must refuse ' . json_encode($body));
            // INPUT_VALIDATION_ERROR is what AbstractApi::checkViolations() emits for an
            // Assert\Collection violation; INVALID_REQUEST is for the checks the
            // controller makes itself, such as a malformed schema name.
            $I->seeResponseContainsJson(['errorCode' => 'INPUT_VALIDATION_ERROR']);
        }
        // None of it may have been stored: _stored is still what the last good patch left.
        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tile');
        $I->assertEqualsCanonicalizing(['cache' => 'disk', 'meta_size' => 5],
            (array)json_decode($I->grabResponse(), true)['_stored']);
    }

    /** Review Focus 5: a 300-character name must not reach a varchar(255) as a 500 with SQL in it. */
    public function shouldRefuseAnImpossibleSchemaName(ApiTester $I): void
    {
        $this->asSuper($I);
        $I->sendPATCH('/api/v4/schemas/' . str_repeat('a', 300) . '/tile', json_encode(['cache' => 'disk']));
        $I->seeResponseCodeIsClientError();
        $I->dontSeeResponseContains('SQLSTATE');
    }

    public function shouldKeepSettingsWhenTheSchemaIsGone(ApiTester $I): void
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->gone]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPATCH('/api/v4/schemas/' . $this->gone . '/tile', json_encode(['cache' => 'disk']));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->sendDELETE('/api/v4/schemas/' . $this->gone);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);

        // GET still answers, and says the schema is not there.
        $I->sendGET('/api/v4/schemas/' . $this->gone . '/tile');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse(), true);
        $I->assertSame('disk', $body['cache'], 'the settings survived the schema');
        $I->assertFalse($body['schema_exists']);

        // PATCH does not: a write to a schema that is not there is how a typo
        // becomes a row that silently takes effect months later.
        $I->sendPATCH('/api/v4/schemas/' . $this->gone . '/tile', json_encode(['ttl' => 900]));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'SCHEMA_NOT_FOUND']);

        // DELETE does, so a leftover row can be cleared.
        $I->sendDELETE('/api/v4/schemas/' . $this->gone . '/tile');
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET('/api/v4/schemas/' . $this->gone . '/tile');
        $I->assertSame([], (array)json_decode($I->grabResponse(), true)['_stored']);
    }

    public function shouldBeSuperUserOnly(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subToken);
        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'SUPER_USER_ONLY']);
    }

    /**
     * A real CORS preflight, which always carries Access-Control-Request-Method —
     * Route2 validates the method named there against AcceptableMethods, so a bare
     * OPTIONS is correctly 406 and would make this test prove nothing.
     */
    public function shouldAnswerACorsPreflight(ApiTester $I): void
    {
        $this->asSuper($I);
        $I->haveHttpHeader('Access-Control-Request-Method', 'PATCH');
        $I->sendOPTIONS('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseCodeIsSuccessful();
        $I->deleteHeader('Access-Control-Request-Method');
    }

    public function shouldCleanUp(ApiTester $I): void
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        // Idempotent: nothing left to remove is still 204.
        $I->sendDELETE('/api/v4/schemas/' . $this->schema . '/tile');
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendDELETE('/api/v4/schemas/' . $this->schema);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }
}
