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

    /**
     * _geometry_columns on the namesOnly summary: what the Map and Tile Cache pages
     * need, from the same catalog query as _column_count, instead of loading every
     * layer's metadata.
     *
     * Views are covered by the same query (relkind v/m) and keep their typmod, but
     * the API cannot create one, so that case is verified by hand, not here.
     */
    public function shouldReportGeometryColumnsInTheSummary(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => 'geo', 'columns' => [
            ['name' => 'gid', 'type' => 'serial'],
            ['name' => 'the_geom', 'type' => 'geometry(MultiPolygon,25832)'],
            ['name' => 'geog', 'type' => 'geography(Point,4326)'],
            ['name' => 'plain', 'type' => 'geometry'],
            ['name' => 'navn', 'type' => 'varchar'],
        ]]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tables?namesOnly=true');
        $I->seeResponseCodeIs(HttpCode::OK);
        $tables = json_decode($I->grabResponse(), true);
        $by = array_column($tables, null, 'name');

        // Type and srid come from the column's typmod, exactly as PostGIS' own
        // geometry_columns/geography_columns views read them.
        $I->assertSame([
            ['name' => 'the_geom', 'type' => 'MultiPolygon', 'srid' => 25832],
            ['name' => 'geog', 'type' => 'Point', 'srid' => 4326],
            ['name' => 'plain', 'type' => 'Geometry', 'srid' => 0],
        ], $by['geo']['_geometry_columns'], 'geometry and geography columns, in column order');

        $I->assertSame([], $by['a']['_geometry_columns'], 'a table without geometry reports an empty list');

        // The full shape carries the same, so a client can rely on the field either way.
        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tables/geo');
        $I->seeResponseCodeIs(HttpCode::OK);
        $full = json_decode($I->grabResponse(), true);
        $I->assertSame($by['geo']['_geometry_columns'], $full['_geometry_columns']);

        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tables/a?namesOnly=true');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame([], json_decode($I->grabResponse(), true)['_geometry_columns']);
    }

    /**
     * _columns on the namesOnly summary: the column names the SQL console needs for
     * autocompletion, from the same catalog query as _column_count. Names only —
     * types stay in the full form and on /columns, which keeps a listing small.
     */
    public function shouldReportColumnNamesInTheSummary(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tables?namesOnly=true');
        $I->seeResponseCodeIs(HttpCode::OK);
        $by = array_column(json_decode($I->grabResponse(), true), null, 'name');

        // Table column order (attnum), which is the order they were created in.
        $I->assertSame(['gid', 'the_geom', 'geog', 'plain', 'navn'], $by['geo']['_columns']);
        $I->assertSame(['gid'], $by['a']['_columns']);

        // _column_count is the length of that list, for every relation in the schema.
        foreach ($by as $name => $rel) {
            $I->assertSame($rel['_column_count'], count($rel['_columns']), "count matches _columns for $name");
        }

        // The full shape and the single-table route carry the same list.
        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tables/geo');
        $I->seeResponseCodeIs(HttpCode::OK);
        $full = json_decode($I->grabResponse(), true);
        $I->assertSame($by['geo']['_columns'], $full['_columns']);
        $I->assertSame(array_column($full['columns'], 'name'), $full['_columns'],
            'the full shape agrees with its own columns');

        $I->sendGET('/api/v4/schemas/' . $this->schema . '/tables/geo?namesOnly=true');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame($by['geo']['_columns'], json_decode($I->grabResponse(), true)['_columns']);
    }

    /**
     * PostGIS ships views of its own, and public.raster_columns has a geometry
     * column (extent) that says nothing about the user's data. Relations owned by
     * an extension therefore report no geometry columns.
     */
    public function shouldNotClaimGeometryOnPostgisOwnViews(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/schemas/public/tables?namesOnly=true');
        $I->seeResponseCodeIs(HttpCode::OK);
        $by = array_column(json_decode($I->grabResponse(), true), null, 'name');
        $I->assertArrayHasKey('raster_columns', $by, 'postgis_raster is installed in the template database');
        $I->assertSame([], $by['raster_columns']['_geometry_columns'],
            'raster_columns.extent belongs to postgis_raster, not to the user');
        $I->assertSame([], $by['geometry_columns']['_geometry_columns']);
        // A view still lists its columns; only the geometry claim is dropped.
        $I->assertContains('extent', $by['raster_columns']['_columns']);
        $I->assertSame($by['raster_columns']['_column_count'], count($by['raster_columns']['_columns']));
    }

    public function shouldCleanUp(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/schemas/' . $this->schema);
        $I->seeResponseCodeIsSuccessful();
    }
}
