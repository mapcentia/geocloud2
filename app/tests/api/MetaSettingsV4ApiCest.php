<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use Codeception\Util\HttpCode;

/**
 * Relation settings (note, enable_ows, authentication, wms_source, ...) and column settings
 * (mouseover, not_exportable, ...) on GET/PATCH /api/v4/meta (app/api/v4/controllers/Meta.php):
 * what can be set, who may set and read it, and that none of the relation settings reach the
 * token-less route.
 */
class MetaSettingsV4ApiCest
{
    private $ts;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $subToken;
    private $schemaName;

    public function __construct()
    {
        $this->ts = time();
        $this->schemaName = 'meta_settings_' . $this->ts;
    }

    private function rel(): string
    {
        return $this->schemaName . '.poi';
    }

    private function bearer(ApiTester $I, ?string $token = null): void
    {
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . ($token ?? $this->token));
    }

    private function meta(ApiTester $I, ?string $token = null): array
    {
        $this->bearer($I, $token);
        $I->sendGET('/api/v4/meta/' . $this->rel());
        $I->seeResponseCodeIs(HttpCode::OK);
        return json_decode($I->grabResponse(), true)['relations'][$this->rel()];
    }

    /** The relation on the token-less route, or null when it is not there. */
    private function publicMeta(ApiTester $I): ?array
    {
        $I->deleteHeader('Authorization');
        $I->deleteHeader('Cookie');
        $I->sendGET('/api/v4/meta/database/' . $this->userId . '/' . $this->rel());
        $I->seeResponseCodeIs(HttpCode::OK);
        return json_decode($I->grabResponse(), true)['relations'][$this->rel()] ?? null;
    }

    private function patch(ApiTester $I, array $body, ?string $token = null): void
    {
        $this->bearer($I, $token);
        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/meta', json_encode(['relations' => [$this->rel() => $body]]));
    }

    public function shouldPrepare(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "meta settings $this->ts", 'email' => "metasettings$this->ts@example.com", 'password' => $this->password,
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
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
            'name' => 'poi',
            'columns' => [
                ['name' => 'gid', 'type' => 'serial'],
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'kind', 'type' => 'varchar'],
                ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/poi/constraints', json_encode([
            'constraint' => 'primary', 'columns' => ['gid'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->deleteHeader('Authorization');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $I->capturePHPSESSID());
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "meta settings sub $this->ts", 'email' => "metasettingssub$this->ts@example.com",
            'password' => $this->password, 'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $subUserId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subToken = json_decode($I->grabResponse())->access_token;
    }

    public function aNewRelationHasItsDefaults(ApiTester $I)
    {
        $rel = $this->meta($I);
        $I->assertSame('Write', $rel['authentication']);
        foreach (['enable_ows', 'editable', 'base_layer', 'tile_cache', 'not_queryable', 'skip_conflict', 'enable_sql_filter'] as $flag) {
            $I->assertIsBool($rel[$flag], $flag);
        }
        $I->assertTrue($rel['enable_ows']);
        $I->assertTrue($rel['editable']);
        foreach (['note', 'meta_url', 'legend_url', 'wms_source', 'bitmap_source', 'trigger_table'] as $text) {
            $I->assertArrayHasKey($text, $rel);
            $I->assertNull($rel[$text], $text);
        }
        $I->assertFalse($rel['fields']['name']['mouseover']);
        $I->assertFalse($rel['fields']['name']['not_exportable']);
        $I->assertNull($rel['fields']['name']['link_prefix']);
    }

    public function shouldSetRelationAndColumnSettings(ApiTester $I)
    {
        $this->patch($I, [
            'note' => 'Checked by Ann', 'enable_ows' => false, 'editable' => false, 'base_layer' => true,
            'not_queryable' => true, 'tile_cache' => true,
            'meta_url' => 'https://example.com/meta?a=1&b=2', 'legend_url' => 'https://example.com/l.png',
            'wms_source' => 'https://wms.example.com/ows?layers=a&format=image/png', 'wms_client_epsgs' => 'EPSG:4326 EPSG:25832',
            'fields' => [
                'name' => [
                    'alias' => 'Name', 'desc' => 'The name', 'mouseover' => true, 'not_exportable' => true, 'searchable' => true,
                    'link' => true, 'link_prefix' => 'https://example.com/', 'link_suffix' => '.html', 'content' => 'plain',
                    'template' => '<b>{{name}}</b>', 'properties' => ['a' => 1],
                ],
                'kind' => ['ignore' => true],
            ],
        ]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);

        $rel = $this->meta($I);
        $I->assertSame('Checked by Ann', $rel['note']);
        $I->assertSame([false, false, true, true, true],
            [$rel['enable_ows'], $rel['editable'], $rel['base_layer'], $rel['not_queryable'], $rel['tile_cache']]);
        $I->assertSame('https://example.com/meta?a=1&b=2', $rel['meta_url']);
        $I->assertSame('https://example.com/l.png', $rel['legend_url']);
        $I->assertSame('https://wms.example.com/ows?layers=a&format=image/png', $rel['wms_source']);
        $I->assertSame('EPSG:4326 EPSG:25832', $rel['wms_client_epsgs']);

        $name = $rel['fields']['name'];
        $I->assertSame(['Name', 'The name', true, true, true, true, 'https://example.com/', '.html', 'plain', '<b>{{name}}</b>'], [
            $name['alias'], $name['desc'], $name['mouseover'], $name['not_exportable'], $name['searchable'], $name['link'],
            $name['link_prefix'], $name['link_suffix'], $name['content'], $name['template'],
        ]);
        $I->assertSame(['a' => 1], $name['properties']);
        $I->assertFalse($name['autocomplete']);
        $I->assertFalse($name['conflict']);
        $I->assertFalse($name['ignore']);
    }

    public function anIgnoredColumnStaysListedWithAToken(ApiTester $I)
    {
        // Left out here, its "ignore" could never be switched off again
        $rel = $this->meta($I);
        $I->assertArrayHasKey('kind', $rel['fields']);
        $I->assertTrue($rel['fields']['kind']['ignore']);
    }

    public function patchingOneColumnSettingKeepsTheRest(ApiTester $I)
    {
        $this->patch($I, ['fields' => ['name' => ['mouseover' => false]]]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $name = $this->meta($I)['fields']['name'];
        $I->assertFalse($name['mouseover']);
        $I->assertTrue($name['not_exportable']);
        $I->assertSame('Name', $name['alias']);
        $I->assertSame(['a' => 1], $name['properties'], 'the properties are not wiped by a patch that does not carry them');
    }

    public function theTokenLessRouteCarriesNoRelationSettings(ApiTester $I)
    {
        $rel = $this->publicMeta($I);
        $I->assertNotNull($rel);
        foreach (['authentication', 'note', 'enable_ows', 'editable', 'meta_url', 'wms_source', 'wms_client_epsgs', 'bitmap_source', 'filter', 'trigger_table'] as $setting) {
            $I->assertArrayNotHasKey($setting, $rel, $setting);
        }
        // Column settings are what a client presents the data by; the ignored column is gone
        $I->assertTrue($rel['fields']['name']['not_exportable']);
        $I->assertArrayNotHasKey('kind', $rel['fields']);
    }

    public function aSubUserReadsSettingsButNotWhereTheDataComesFrom(ApiTester $I)
    {
        $rel = $this->meta($I, $this->subToken);
        $I->assertSame('Checked by Ann', $rel['note']);
        $I->assertArrayHasKey('enable_ows', $rel);
        $I->assertArrayHasKey('authentication', $rel);
        foreach (['wms_source', 'wms_client_epsgs', 'bitmap_source', 'trigger_table'] as $ownerOnly) {
            $I->assertArrayNotHasKey($ownerOnly, $rel, $ownerOnly);
        }
    }

    public function onlyTheOwnerChangesRelationSettings(ApiTester $I)
    {
        foreach ([['authentication' => 'None'], ['note' => 'x'], ['enable_ows' => true], ['wms_source' => 'https://example.com/']] as $body) {
            $this->patch($I, $body, $this->subToken);
            $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
            $I->seeResponseContainsJson(['errorCode' => 'SUPER_USER_ONLY']);
        }
        $rel = $this->meta($I);
        $I->assertSame('Write', $rel['authentication']);
        $I->assertSame('Checked by Ann', $rel['note']);
    }

    public function shouldRejectInvalidValues(ApiTester $I)
    {
        foreach ([
            ['authentication' => 'Open'], ['enable_ows' => 'yes'], ['note' => 5],
            ['fields' => ['name' => ['mouseover' => 'yes']]], ['fields' => ['name' => ['link_prefix' => 5]]],
            ['unknown_setting' => true],
        ] as $body) {
            $this->patch($I, $body);
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        }
    }

    // The API is snake_case; the names the settings are stored under are not part of it
    public function theStoredNamesAreNotApiNames(ApiTester $I)
    {
        $rel = $this->meta($I);
        foreach (['enableows', 'baselayer', 'tilecache', 'not_querable', 'skipconflict', 'enablesqlfilter', 'featureid',
                     'wmssource', 'wmsclientepsgs', 'bitmapsource', 'triggertable', 'fieldconf', 'layergroup'] as $stored) {
            $I->assertArrayNotHasKey($stored, $rel, $stored);
        }
        foreach (['linkprefix', 'linksuffix', 'querable'] as $stored) {
            $I->assertArrayNotHasKey($stored, $rel['fields']['name'], $stored);
        }
        // The layer filter of the settings row is not used any more and is not part of the API.
        // (A column still has its own "filter" setting.)
        $I->assertArrayNotHasKey('filter', $rel);
        $this->patch($I, ['filter' => "kind = 'a'"]);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->assertArrayHasKey('filter', $rel['fields']['name']);

        foreach ([['skipconflict' => true], ['enableows' => true], ['wmssource' => 'https://example.com/'],
                     ['fields' => ['name' => ['linkprefix' => 'x']]]] as $body) {
            $this->patch($I, $body);
            $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        }
        $this->patch($I, ['skip_conflict' => true, 'enable_sql_filter' => true, 'feature_id' => 'gid', 'trigger_table' => $this->rel(), 'bitmap_source' => 'x']);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $rel = $this->meta($I);
        $I->assertSame([true, true, 'gid', $this->rel(), 'x'],
            [$rel['skip_conflict'], $rel['enable_sql_filter'], $rel['feature_id'], $rel['trigger_table'], $rel['bitmap_source']]);
        $this->patch($I, ['skip_conflict' => false, 'enable_sql_filter' => false, 'feature_id' => null, 'trigger_table' => null, 'bitmap_source' => null]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
    }

    public function protectingARelationTakesEffectAtOnce(ApiTester $I)
    {
        $this->patch($I, ['authentication' => 'Read/write']);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $rel = $this->meta($I);
        $I->assertSame('Read/write', $rel['authentication']);
        $I->assertSame('Read/write', $rel['_authentication'], 'the read-only twin agrees');

        // No window in which an anonymous caller still gets it
        $I->assertNull($this->publicMeta($I));
        $I->sendGET('/api/v4/legends/database/' . $this->userId . '/' . $this->rel());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame([], json_decode($I->grabResponse(), true));
    }

    public function shouldClearAndSwitchBack(ApiTester $I)
    {
        $this->patch($I, ['authentication' => 'Write', 'note' => null, 'wms_source' => '', 'enable_ows' => true, 'editable' => true]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $rel = $this->meta($I);
        $I->assertSame(['Write', null, null, true, true],
            [$rel['authentication'], $rel['note'], $rel['wms_source'], $rel['enable_ows'], $rel['editable']]);
        $I->assertNotNull($this->publicMeta($I));

        $this->patch($I, ['fields' => ['kind' => ['ignore' => false]]]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->assertArrayHasKey('kind', $this->publicMeta($I)['fields']);
    }

    public function theDescriptiveMetadataStillWorks(ApiTester $I)
    {
        $this->patch($I, ['title' => 'Points', 'tags' => ['t1']]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $rel = $this->meta($I);
        $I->assertSame('Points', $rel['title']);
        $I->assertContains('t1', $rel['tags']);
    }
}
