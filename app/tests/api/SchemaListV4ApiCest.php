<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use Codeception\Util\HttpCode;

/** GET /api/v4/schemas: _table_count on every schema, with and without namesOnly. */
class SchemaListV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $schema;

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepareUserAndSchema(ApiTester $I)
    {
        $ts = time();
        $this->schema = "schemalist_$ts";
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => "schemalist $ts", 'email' => "schemalist$ts@example.com", 'password' => $this->password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $this->asSuper($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        foreach (['a', 'b'] as $name) {
            $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => $name, 'columns' => [['name' => 'gid', 'type' => 'serial']]]));
            $I->seeResponseCodeIs(HttpCode::CREATED);
        }
    }

    public function shouldListSchemasWithTableCountWithoutLoadingTables(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/schemas?namesOnly=true');
        $I->seeResponseCodeIs(HttpCode::OK);
        $schemas = json_decode($I->grabResponse());
        $mine = array_values(array_filter($schemas, fn($s) => $s->name === $this->schema));
        $I->assertCount(1, $mine);
        $I->assertSame(2, $mine[0]->_table_count);
        $I->assertFalse(property_exists($mine[0], 'tables'), 'namesOnly omits the tables');
        $I->assertEquals('/api/v4/schemas/' . $this->schema . '/tables', $mine[0]->_links->tables);

        $I->sendGET('/api/v4/schemas');
        $I->seeResponseCodeIs(HttpCode::OK);
        $mine = array_values(array_filter(json_decode($I->grabResponse()), fn($s) => $s->name === $this->schema));
        $I->assertSame(2, $mine[0]->_table_count);
        $I->assertCount(2, $mine[0]->tables, 'the full listing still carries the tables');
    }

    public function shouldListTablesWithColumnCountWithoutLoadingDefinitions(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tables?namesOnly=true');
        $I->seeResponseCodeIs(HttpCode::OK);
        $tables = json_decode($I->grabResponse());
        $I->assertSame(['a', 'b'], array_map(fn($t) => $t->name, $tables));
        $I->assertSame(1, $tables[0]->_column_count);
        $I->assertSame('TABLE', $tables[0]->_type);
        $I->assertFalse($tables[0]->_events);
        $I->assertFalse(property_exists($tables[0], 'columns'), 'namesOnly omits the definition');
        $I->assertEquals('/api/v4/schemas/' . $this->schema . '/tables/a/columns', $tables[0]->_links->columns);

        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tables/a?namesOnly=true');
        $I->seeResponseCodeIs(HttpCode::OK);
        $one = json_decode($I->grabResponse());
        $I->assertSame(1, $one->_column_count);
        $I->assertFalse(property_exists($one, 'columns'));

        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tables/a');
        $I->seeResponseCodeIs(HttpCode::OK);
        $full = json_decode($I->grabResponse());
        $I->assertSame(1, $full->_column_count, 'the full shape carries the count too');
        $I->assertCount(1, $full->columns);
    }

    public function shouldCleanUp(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/schemas/' . $this->schema);
        $I->seeResponseCodeIsSuccessful();
    }
}
