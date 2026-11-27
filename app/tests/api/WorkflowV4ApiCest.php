<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use Codeception\Util\HttpCode;

/**
 * Versioning and workflow in v4:
 * - `versioning` and `workflow` on the table resource (app/api/v4/controllers/Table.php),
 * - workflow roles (WorkflowRole.php),
 * - workflow items and the approval step (WorkflowItem.php, app/models/WorkflowItems.php).
 *
 * Runs one feature through the whole flow: drafted by an author through the Feature API,
 * approved by a reviewer, published by the owner, and checks at each step who can see it.
 */
class WorkflowV4ApiCest
{
    private $ts;
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $schemaName;
    /** @var array<string, array{id: string, token: string}> author, reviewer, bystander */
    private array $sub = [];
    private $ownerFeature;
    private $draft;

    public function __construct()
    {
        $this->ts = time();
        $this->schemaName = 'wf_test_' . $this->ts;
    }

    private function tableUri(): string
    {
        return '/api/v4/schemas/' . $this->schemaName . '/tables/poi';
    }

    private function as(ApiTester $I, ?string $who = null): void
    {
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . ($who === null ? $this->token : $this->sub[$who]['token']));
    }

    private function table(ApiTester $I): array
    {
        $this->as($I);
        $I->sendGET($this->tableUri());
        $I->seeResponseCodeIs(HttpCode::OK);
        return json_decode($I->grabResponse(), true);
    }

    private function patchTable(ApiTester $I, array $body, ?string $who = null): void
    {
        $this->as($I, $who);
        $I->stopFollowingRedirects();
        $I->sendPATCH($this->tableUri(), json_encode($body));
    }

    private function scalar(ApiTester $I, string $sql): mixed
    {
        $this->as($I);
        $I->sendPOST('/api/v4/sql', json_encode(['q' => $sql]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse(), true);
        $row = ($body['data'] ?? $body)[0];
        return array_values($row)[0];
    }

    private function feature(string $name): string
    {
        return json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['name' => $name],
                'geometry' => ['type' => 'Point', 'coordinates' => [9.5, 55.7]],
            ]],
        ]);
    }

    /** Names of the features $who reads through the Feature API. */
    private function visibleNames(ApiTester $I, ?string $who): array
    {
        $this->as($I, $who);
        $I->sendGET('/api/v4/ogc/database/' . $this->userId . '/collections/' . $this->schemaName . '.poi/items?limit=100');
        $I->seeResponseCodeIs(HttpCode::OK);
        $names = array_map(fn($f) => $f['properties']['name'], json_decode($I->grabResponse(), true)['features']);
        sort($names);
        return $names;
    }

    private function items(ApiTester $I, ?string $who, string $query = ''): array
    {
        $this->as($I, $who);
        $I->sendGET($this->tableUri() . '/workflow-items' . $query);
        $I->seeResponseCodeIs(HttpCode::OK);
        $items = json_decode($I->grabResponse(), true);
        $I->assertTrue(array_is_list($items), 'the list is a bare JSON array');
        return $items;
    }

    private function approve(ApiTester $I, ?string $who, string $gid): void
    {
        $this->as($I, $who);
        $I->stopFollowingRedirects();
        $I->sendPATCH($this->tableUri() . '/workflow-items/' . $gid);
    }

    public function shouldPrepare(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "wf super $this->ts", 'email' => "wfsuper$this->ts@example.com", 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $this->as($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
            'name' => 'poi',
            'columns' => [
                ['name' => 'gid', 'type' => 'serial'],
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST($this->tableUri() . '/constraints', json_encode(['constraint' => 'primary', 'columns' => ['gid']]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        // Three sub-users, all with write privilege on the table; only two get a workflow role
        foreach (['author', 'reviewer', 'bystander'] as $who) {
            $I->deleteHeader('Authorization');
            $I->sendPOST('/api/v2/session/start', json_encode([
                'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
            ]));
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $I->capturePHPSESSID());
            $I->sendPOST('/api/v2/user', json_encode([
                'name' => "wf $who $this->ts", 'email' => "wf$who$this->ts@example.com",
                'password' => $this->password, 'subuser' => true,
            ]));
            $I->seeResponseCodeIs(HttpCode::OK);
            $id = json_decode($I->grabResponse())->data->screenname;
            $I->deleteHeader('Cookie');
            $I->sendPOST('/api/v4/oauth', json_encode([
                'grant_type' => 'password', 'username' => $id, 'password' => $this->password,
                'database' => $this->userId, 'client_id' => 'gc2-cli',
            ]));
            $I->seeResponseCodeIs(HttpCode::CREATED);
            $this->sub[$who] = ['id' => $id, 'token' => json_decode($I->grabResponse())->access_token];
        }
        $this->as($I);
        $I->stopFollowingRedirects();
        $I->sendPATCH($this->tableUri() . '/privileges', json_encode(array_map(
            fn($s) => ['subuser' => $s['id'], 'privilege' => 'write'], array_values($this->sub)
        )));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
    }

    public function aNewTableHasNeither(ApiTester $I)
    {
        $table = $this->table($I);
        $I->assertFalse($table['versioning']);
        $I->assertFalse($table['workflow']);
        $I->assertArrayHasKey('workflow_items', $table['_links']);
    }

    public function workflowNeedsVersioning(ApiTester $I)
    {
        $this->patchTable($I, ['workflow' => true]);
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
        $I->seeResponseContainsJson(['errorCode' => 'VERSIONING_REQUIRED']);
        $I->assertFalse($this->table($I)['workflow']);
    }

    public function onlyTheOwnerSwitches(ApiTester $I)
    {
        $this->patchTable($I, ['versioning' => true], 'author');
        $I->seeResponseCodeIsClientError();
        $this->patchTable($I, ['versioning' => 'yes']);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->assertFalse($this->table($I)['versioning']);
    }

    public function shouldEnableVersioning(ApiTester $I)
    {
        $this->patchTable($I, ['versioning' => true]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $table = $this->table($I);
        $I->assertTrue($table['versioning']);
        $I->assertFalse($table['workflow']);
        $I->assertContains('gc2_version_gid', $table['_columns']);

        // Asking again changes nothing
        $this->patchTable($I, ['versioning' => true]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
    }

    public function anEditKeepsThePreviousVersion(ApiTester $I)
    {
        $this->as($I);
        $I->stopFollowingRedirects();
        $I->sendPOST($this->tableUri() . '/features', $this->feature('before'));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->ownerFeature = basename(json_decode($I->grabResponse(), true)[0]['_links']['self']);
        $I->sendPATCH($this->tableUri() . '/features/' . $this->ownerFeature, json_encode([
            'type' => 'Feature', 'properties' => ['name' => 'after'],
            'geometry' => ['type' => 'Point', 'coordinates' => [9.5, 55.7]],
        ]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->assertEquals(2, $this->scalar($I, "SELECT count(*) FROM $this->schemaName.poi"), 'current and ended version');
        $I->assertEquals(1, $this->scalar($I, "SELECT count(*) FROM $this->schemaName.poi WHERE gc2_version_end_date IS NULL"));
        $I->assertSame(['after'], $this->visibleNames($I, null));
    }

    public function shouldEnableWorkflow(ApiTester $I)
    {
        $this->patchTable($I, ['workflow' => true]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $table = $this->table($I);
        $I->assertTrue($table['workflow']);
        $I->assertContains('gc2_status', $table['_columns']);
        $I->assertEquals(0, $this->scalar($I, "SELECT count(*) FROM $this->schemaName.poi WHERE gc2_status IS DISTINCT FROM 3"),
            'what was there counts as published');
    }

    public function versioningCannotGoWhileWorkflowStays(ApiTester $I)
    {
        $this->patchTable($I, ['versioning' => false]);
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
        $I->seeResponseContainsJson(['errorCode' => 'WORKFLOW_ENABLED']);
        $I->assertTrue($this->table($I)['versioning']);
    }

    public function shouldSetRoles(ApiTester $I)
    {
        $uri = $this->tableUri() . '/workflow-roles';
        $this->as($I);
        $I->sendGET($uri);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame([], json_decode($I->grabResponse(), true));

        // Not by a sub-user, and not an unknown role
        $this->as($I, 'author');
        $I->stopFollowingRedirects();
        $I->sendPATCH($uri, json_encode(['subuser' => $this->sub['author']['id'], 'role' => 'publisher']));
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $this->as($I);
        $I->sendPATCH($uri, json_encode(['subuser' => $this->sub['author']['id'], 'role' => 'boss']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->sendPATCH($uri, json_encode([
            ['subuser' => $this->sub['author']['id'], 'role' => 'author'],
            ['subuser' => $this->sub['reviewer']['id'], 'role' => 'publisher'],
        ]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        // One at a time merges with what is there
        $I->sendPATCH($uri, json_encode(['subuser' => $this->sub['reviewer']['id'], 'role' => 'reviewer']));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);

        $I->sendGET($uri);
        $I->seeResponseCodeIs(HttpCode::OK);
        $roles = array_column(json_decode($I->grabResponse(), true), 'role', 'subuser');
        $I->assertSame(['author', 'reviewer'], [$roles[$this->sub['author']['id']], $roles[$this->sub['reviewer']['id']]]);
        $I->assertArrayNotHasKey($this->sub['bystander']['id'], $roles);

        // A sub-user can read the roles (to know their own)
        $this->as($I, 'reviewer');
        $I->sendGET($uri);
        $I->seeResponseCodeIs(HttpCode::OK);
    }

    public function anAuthorsInsertIsADraft(ApiTester $I)
    {
        $this->as($I, 'author');
        $I->stopFollowingRedirects();
        $I->sendPOST($this->tableUri() . '/features', $this->feature('draft'));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->draft = basename(json_decode($I->grabResponse(), true)[0]['_links']['self']);
        $I->assertEquals(1, $this->scalar($I, "SELECT gc2_status FROM $this->schemaName.poi WHERE gid = " . (int)$this->draft));

        // The author and the reviewer see the draft; someone without a role sees published only
        $I->assertSame(['after', 'draft'], $this->visibleNames($I, 'author'));
        $I->assertSame(['after', 'draft'], $this->visibleNames($I, 'reviewer'));
        $I->assertSame(['after'], $this->visibleNames($I, 'bystander'));
    }

    public function theDraftIsWaitingForTheReviewerAndTheOwner(ApiTester $I)
    {
        $item = $this->items($I, 'reviewer')[0];
        $I->assertSame((int)$this->draft, $item['gid']);
        $I->assertSame(1, $item['status']);
        $I->assertSame('drafted', $item['status_name']);
        $I->assertSame('insert', $item['operation']);
        $I->assertSame($this->sub['author']['id'], $item['user']);
        $I->assertSame($this->sub['author']['id'], $item['author']);
        $I->assertNull($item['reviewer']);
        $I->assertNull($item['publisher']);
        $I->assertNotEmpty($item['created']);
        $I->assertNotEmpty($item['version_gid']);

        $I->assertCount(1, $this->items($I, 'reviewer'));
        $I->assertCount(1, $this->items($I, null), 'the owner acts as publisher');
        $I->assertSame([], $this->items($I, 'author'), 'nothing is waiting for the author');
        $I->assertCount(1, $this->items($I, 'author', '?all=true'));

        // One item by id is an object
        $this->as($I, 'reviewer');
        $I->sendGET($this->tableUri() . '/workflow-items/' . $this->draft);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame((int)$this->draft, json_decode($I->grabResponse(), true)['gid']);
        $I->sendGET($this->tableUri() . '/workflow-items/999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'WORKFLOW_ITEM_NOT_FOUND']);
    }

    public function withoutARoleThereIsNoAccess(ApiTester $I)
    {
        $this->as($I, 'bystander');
        $I->sendGET($this->tableUri() . '/workflow-items');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'NO_WORKFLOW_ROLE']);
        $this->approve($I, 'bystander', $this->draft);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->assertEquals(1, $this->scalar($I, "SELECT gc2_status FROM $this->schemaName.poi WHERE gid = " . (int)$this->draft));
    }

    public function anAuthorCannotMoveADraftFurther(ApiTester $I)
    {
        $this->approve($I, 'author', $this->draft);
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
        $I->seeResponseContainsJson(['errorCode' => 'WORKFLOW_STATUS_CONFLICT']);
    }

    public function theReviewerApproves(ApiTester $I)
    {
        $before = $this->scalar($I, "SELECT count(*) FROM $this->schemaName.poi");
        $this->approve($I, 'reviewer', $this->draft);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->assertStringEndsWith('/workflow-items/' . $this->draft, $I->grabHttpHeader('Location'));

        $item = $this->items($I, null)[0];
        $I->assertSame(2, $item['status']);
        $I->assertSame('reviewed', $item['status_name']);
        $I->assertSame('update', $item['operation']);
        $I->assertSame($this->sub['author']['id'], $item['author'], 'the author keeps their signature');
        $I->assertSame($this->sub['reviewer']['id'], $item['reviewer']);
        $I->assertNull($item['publisher']);

        $I->assertEquals($before + 1, $this->scalar($I, "SELECT count(*) FROM $this->schemaName.poi"), 'the draft state is kept as an ended version');
        $I->assertSame([], $this->items($I, 'reviewer'), 'nothing more for the reviewer');
        $I->assertSame(['after'], $this->visibleNames($I, 'bystander'), 'reviewed is not published');

        // Twice is a conflict, and an unknown feature is not found
        $this->approve($I, 'reviewer', $this->draft);
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
        $this->approve($I, 'reviewer', '999999');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'FEATURE_NOT_FOUND']);
    }

    public function theOwnerPublishes(ApiTester $I)
    {
        $this->approve($I, null, $this->draft);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);

        $I->assertSame([], $this->items($I, null), 'nothing is waiting');
        $item = $this->items($I, null, '?all=true')[0];
        $I->assertSame(3, $item['status']);
        $I->assertSame('published', $item['status_name']);
        $I->assertSame($this->userId, $item['publisher']);
        $I->assertSame($this->sub['reviewer']['id'], $item['reviewer']);

        $I->assertSame(['after', 'draft'], $this->visibleNames($I, 'bystander'), 'published is for everyone');
    }

    public function switchingBothOffLeavesOneRowPerFeature(ApiTester $I)
    {
        $I->assertGreaterThan(2, $this->scalar($I, "SELECT count(*) FROM $this->schemaName.poi"), 'there is history to lose');
        $this->patchTable($I, ['workflow' => false, 'versioning' => false]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);

        $table = $this->table($I);
        $I->assertFalse($table['versioning']);
        $I->assertFalse($table['workflow']);
        $I->assertNotContains('gc2_version_gid', $table['_columns']);
        $I->assertNotContains('gc2_status', $table['_columns']);
        $I->assertEquals(2, $this->scalar($I, "SELECT count(*) FROM $this->schemaName.poi"), 'only the current rows are left');
        $I->assertSame(['after', 'draft'], $this->visibleNames($I, 'bystander'));

        // The workflow routes say so
        $this->approve($I, null, $this->draft);
        $I->seeResponseCodeIs(HttpCode::CONFLICT);
        $I->seeResponseContainsJson(['errorCode' => 'WORKFLOW_NOT_ENABLED']);
        $I->assertSame([], $this->items($I, null, '?all=true'), 'the log of the table is gone');
    }

    public function bothCanBeEnabledInOneRequest(ApiTester $I)
    {
        $this->patchTable($I, ['versioning' => true, 'workflow' => true]);
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $table = $this->table($I);
        $I->assertTrue($table['versioning']);
        $I->assertTrue($table['workflow']);
    }

    // A LIMIT rule makes the server check the changed rows before it lets an edit through. On a
    // workflow table the edit also returns the workflow columns, and the check must cope with that.
    public function anEditOnAWorkflowTablePassesALimitRule(ApiTester $I)
    {
        $this->as($I);
        $I->stopFollowingRedirects();
        $I->sendPOST('/api/v4/rules', json_encode([
            'username' => '*', 'service' => '*', 'request' => '*', 'access' => 'limit', 'filter' => '1=1',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $ruleId = basename($I->grabHttpHeader('Location'));

        $I->sendPATCH($this->tableUri() . '/features/' . $this->ownerFeature, json_encode([
            'type' => 'Feature', 'properties' => ['name' => 'after'],
            'geometry' => ['type' => 'Point', 'coordinates' => [9.6, 55.8]],
        ]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->sendPOST($this->tableUri() . '/features', $this->feature('limited'));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $inserted = basename(json_decode($I->grabResponse(), true)[0]['_links']['self']);
        $I->sendDELETE($this->tableUri() . '/features/' . $inserted);
        $I->seeResponseCodeIsSuccessful();

        $this->as($I);
        $I->sendDELETE('/api/v4/rules/' . $ruleId);
        $I->seeResponseCodeIsSuccessful();
    }

    public function unknownTableIsNotFound(ApiTester $I)
    {
        $this->as($I);
        $I->sendGET('/api/v4/schemas/' . $this->schemaName . '/tables/nope/workflow-items');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'TABLE_NOT_FOUND']);
        $I->sendGET('/api/v4/schemas/' . $this->schemaName . '/tables/nope/workflow-roles');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }
}
