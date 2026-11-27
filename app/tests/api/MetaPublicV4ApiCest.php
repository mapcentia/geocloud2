<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use Codeception\Util\HttpCode;

/**
 * GET /api/v4/meta/database/{database}/[query] (app/api/v4/controllers/MetaPublic.php): relation
 * metadata without a token. Checks the query forms (relation, schema, tag, list, none), that it
 * answers in the same shape as the token route, and that an anonymous caller only gets
 * relations that are readable without credentials.
 */
class MetaPublicV4ApiCest
{
    private $ts;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $schemaName;

    public function __construct()
    {
        $this->ts = time();
        $this->schemaName = 'meta_public_' . $this->ts;
    }

    private function uri(string $query = ''): string
    {
        return '/api/v4/meta/database/' . $this->userId . ($query === '' ? '' : '/' . $query);
    }

    private function open(): string
    {
        return $this->schemaName . '.open';
    }

    private function secret(): string
    {
        return $this->schemaName . '.secret';
    }

    private function anonymous(ApiTester $I): void
    {
        $I->deleteHeader('Authorization');
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Accept', 'application/json');
    }

    private function bearer(ApiTester $I): void
    {
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    /** @return array<string, array> the relations of an anonymous read, keyed by schema.table */
    private function relations(ApiTester $I, string $query = ''): array
    {
        $this->anonymous($I);
        $I->sendGET($this->uri($query));
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse(), true);
        $I->assertSame(['relations'], array_keys($body));
        return $body['relations'];
    }

    public function shouldPrepareUserSchemaAndLayers(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "meta public $this->ts", 'email' => "metapublic$this->ts@example.com", 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $this->bearer($I);
        $I->stopFollowingRedirects();
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        foreach (['open', 'secret'] as $table) {
            $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
                'name' => $table,
                'columns' => [
                    ['name' => 'gid', 'type' => 'serial'],
                    ['name' => 'name', 'type' => 'varchar'],
                    ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
                ],
            ]));
            $I->seeResponseCodeIs(HttpCode::CREATED);
            $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/' . $table . '/constraints', json_encode([
                'constraint' => 'primary', 'columns' => ['gid'],
            ]));
            $I->seeResponseCodeIs(HttpCode::CREATED);
        }
        $I->sendPATCH('/api/v4/meta', json_encode(['relations' => [
            $this->open() => ['tags' => ['public-tag']],
            $this->secret() => ['tags' => ['public-tag']],
        ]]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
    }

    public function shouldReturnARelationWithoutAToken(ApiTester $I)
    {
        $relations = $this->relations($I, $this->open());
        $I->assertSame([$this->open()], array_keys($relations), 'keyed by schema.table');
        $rel = $relations[$this->open()];
        $I->assertSame($this->schemaName, $rel['_schema']);
        $I->assertSame('open', $rel['_rel']);
        $I->assertSame('the_geom', $rel['_geometry_column']);
        $I->assertEquals(4326, $rel['_srid']);
        $I->assertSame('gid', $rel['_pkey']);
        $I->assertContains('public-tag', $rel['tags']);
        $I->assertArrayHasKey('name', $rel['fields']);
        // Nothing about who may do what, or where the layer is drawn from
        foreach (['privileges', 'roles', 'wms_source', 'wmssource', 'qml'] as $internal) {
            $I->assertArrayNotHasKey($internal, $rel);
        }
    }

    public function shouldAnswerInTheSameShapeAsTheTokenRoute(ApiTester $I)
    {
        $public = $this->relations($I, $this->open())[$this->open()];
        $this->bearer($I);
        $I->sendGET('/api/v4/meta/' . $this->open());
        $I->seeResponseCodeIs(HttpCode::OK);
        $withToken = json_decode($I->grabResponse(), true)['relations'][$this->open()];
        // Everything the public route gives is on the token route under the same name ...
        $I->assertSame([], array_values(array_diff(array_keys($public), array_keys($withToken))));
        $I->assertSame($withToken['fields'], $public['fields']);
        // ... and what only the token route has is the relation's settings
        $onlyWithToken = array_values(array_diff(array_keys($withToken), array_keys($public)));
        $I->assertContains('authentication', $onlyWithToken);
        $I->assertContains('enable_ows', $onlyWithToken);
        $I->assertContains('wms_source', $onlyWithToken);
        foreach ($onlyWithToken as $key) {
            $I->assertArrayNotHasKey($key, $public);
        }
    }

    public function shouldTakeASchemaAListATagAndNothing(ApiTester $I)
    {
        $both = [$this->open(), $this->secret()];
        $names = fn(array $relations) => array_values(array_intersect($both, array_keys($relations)));

        $I->assertSame($both, $names($this->relations($I, $this->schemaName)), 'a schema');
        $I->assertSame($both, $names($this->relations($I, $this->open() . ',' . $this->secret())), 'a list');
        $I->assertSame($both, $names($this->relations($I, 'tag:public-tag')), 'a tag');
        $I->assertSame($both, $names($this->relations($I)), 'no query: the whole database');

        $I->assertSame([], $this->relations($I, $this->schemaName . '.nope'), 'an unknown relation is not an error');
        $I->assertSame([], $this->relations($I, 'tag:nope'));
    }

    public function shouldLeaveOutColumnRestrictionsOnRequest(ApiTester $I)
    {
        // The switch must be accepted in both forms and not change which relations come back
        $I->assertSame([$this->open()], array_keys($this->relations($I, $this->open() . '?noRestriction=true')));
        $I->assertSame([$this->open()], array_keys($this->relations($I, $this->open() . '?noRestriction=false')));
    }

    public function shouldProtectSecret(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $I->capturePHPSESSID());
        $I->sendPUT('/controllers/layer/records/' . $this->secret() . '.the_geom', json_encode([
            'data' => ['authentication' => 'Read/write', '_key_' => $this->secret() . '.the_geom'],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    public function shouldNotShowAProtectedRelationWithoutAToken(ApiTester $I)
    {
        $I->assertSame([], $this->relations($I, $this->secret()), 'asked for by name: empty, not an error');
        $I->assertSame([$this->open()], array_keys($this->relations($I, $this->schemaName)));
        $I->assertSame([$this->open()], array_keys($this->relations($I, $this->open() . ',' . $this->secret())));
        $I->assertSame([$this->open()], array_keys($this->relations($I, 'tag:public-tag')));
        $I->assertArrayNotHasKey($this->secret(), $this->relations($I));

        // The token route still has it
        $this->bearer($I);
        $I->sendGET('/api/v4/meta/' . $this->secret());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertArrayHasKey($this->secret(), json_decode($I->grabResponse(), true)['relations']);
    }

    public function aTokenDoesNotWidenThePublicRoute(ApiTester $I)
    {
        // The route is the anonymous view of the database, whoever asks
        $this->bearer($I);
        $I->sendGET($this->uri($this->secret()));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame([], json_decode($I->grabResponse(), true)['relations']);
    }

    public function shouldReturn404ForAnUnknownDatabase(ApiTester $I)
    {
        $this->anonymous($I);
        $I->sendGET('/api/v4/meta/database/no_such_database_' . $this->ts . '/public');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['success' => false, 'errorCode' => 'DATABASE_NOT_FOUND']);
        $I->assertStringNotContainsString('SQLSTATE', $I->grabResponse());
    }

    public function shouldTreatAQuoteInTheQueryAsPartOfTheName(ApiTester $I)
    {
        $this->anonymous($I);
        foreach ([$this->schemaName . '.op%27en', $this->schemaName . '%27x', 'tag:a%27b'] as $query) {
            $I->sendGET($this->uri($query));
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->assertSame([], json_decode($I->grabResponse(), true)['relations']);
        }
    }

    public function shouldAnswerPreflightAndRejectWrite(ApiTester $I)
    {
        $this->anonymous($I);
        $I->haveHttpHeader('Access-Control-Request-Method', 'GET');
        $I->sendOPTIONS($this->uri($this->open()));
        $I->seeResponseCodeIsSuccessful();
        $I->deleteHeader('Access-Control-Request-Method');

        $this->bearer($I);
        $I->sendPATCH($this->uri($this->open()), json_encode(['relations' => [$this->open() => ['tags' => ['x']]]]));
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
        $I->assertContains('public-tag', $this->relations($I, $this->open())[$this->open()]['tags']);
    }
}
