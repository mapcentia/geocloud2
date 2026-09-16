<?php

use Codeception\Util\HttpCode;

/**
 * HTTP contract of the relation snapshot read API
 * (app/api/v4/controllers/RelationSnapshot.php): listing, metadata, HEAD/GET
 * of the Parquet file with byte ranges, and the privilege check. Runs the
 * snapshot worker inline (same container) after queuing, against whatever
 * storage app/conf/App.php configures. Ordered/stateful.
 */
class RelationSnapshotV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $subUserId;
    private $subToken;
    private $schema;
    private $date;
    private $size;

    public function __construct()
    {
        $this->schema = 'rsnap_' . (new DateTime())->getTimestamp();
    }

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

    private function base(): string
    {
        return '/api/v4/schemas/' . $this->schema . '/relations/poi/snapshots';
    }

    public function shouldPrepareUsersTableAndSnapshot(ApiTester $I)
    {
        $ts = (new DateTime())->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => 'rsnap super ' . $ts, 'email' => 'rsnapsuper' . $ts . '@example.com', 'password' => $this->password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $I->sendPOST('/api/v2/session/start', json_encode(['user' => $this->userId, 'password' => $this->password, 'schema' => 'public']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $cookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->sendPOST('/api/v2/user', json_encode(['name' => 'rsnap sub ' . $ts, 'email' => 'rsnapsub' . $ts . '@example.com', 'password' => $this->password, 'subuser' => true]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->subUserId, 'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subToken = json_decode($I->grabResponse())->access_token;

        $this->asSuper($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => 'poi', 'columns' => [
            ['name' => 'gid', 'type' => 'serial'], ['name' => 'name', 'type' => 'varchar'], ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
        ]]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/sql', json_encode(['q' => "INSERT INTO \"{$this->schema}\".poi(name, the_geom) SELECT 'p'||g, ST_SetSRID(ST_MakePoint(10+g*0.001, 56), 4326) FROM generate_series(1, 50) g"]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'poi']));
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $id = json_decode($I->grabResponse())->id;

        // Run the worker inline for this database (the container has no snapshot cron).
        $out = shell_exec('php -f /var/www/geocloud2/app/scripts/snapshot_worker.php ' . escapeshellarg($this->userId) . ' 2>&1');
        $I->assertStringContainsString('ok=1', (string)$out, "worker output: $out");

        $I->sendGET('/api/v4/snapshots/' . $id);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['status' => 'succeeded']);
        // Take the date from the job API rather than gmdate() after the run:
        // a midnight-UTC rollover between the worker's gmdate() and ours would
        // otherwise point every later request at a date with no snapshot.
        $this->date = json_decode($I->grabResponse())->snapshot_date;
        $I->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string)$this->date);
    }

    public function shouldListSnapshotsOfRelation(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse());
        $I->assertCount(1, $body->snapshots);
        $s = $body->snapshots[0];
        $I->assertEquals($this->date, $s->snapshot_date);
        $I->assertEquals(50, $s->row_count);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $s->schema_version);
        $I->assertCount(1, $s->files);
        $I->assertStringEndsWith('.parquet', $s->files[0]->name);
        $I->assertEquals($this->base() . '/' . $this->date . '/files/' . $s->files[0]->name, $s->files[0]->href);
        $this->size = (int)$s->files[0]->size_bytes;
        $I->assertGreaterThan(8, $this->size);
    }

    public function shouldReturnSnapshotMetadata(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base() . '/' . $this->date);
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse());
        $I->assertEquals($this->date, $body->snapshot_date);
        $I->assertEquals('EPSG:4326', $body->crs);
        $I->assertEquals('gid', $body->relation_schema[0]->column_name);
        $I->assertEquals($this->base() . '/' . $this->date . '/data', $body->_links->data);

        $I->sendGET($this->base() . '/1999-01-01');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'NO_SNAPSHOT_ERROR']);

        $I->sendGET($this->base() . '/not-a-date');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldRejectUnsafeNames(ApiTester $I)
    {
        $this->asSuper($I);
        // CRITICAL: schema/relation are interpolated into settings.getColumns()'s
        // literal-quoted SQL by SnapshotAuthorizer -> Model::getGeometryColumns().
        // A positive-class regex must reject a quote in either segment.
        $I->sendGET("/api/v4/schemas/x'--/relations/poi/snapshots");
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INVALID_REQUEST']);

        $I->sendGET('/api/v4/schemas/' . $this->schema . "/relations/p%27q/snapshots");
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldAnswerHeadWithLengthAndAcceptRanges(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendHEAD($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Accept-Ranges', 'bytes');
        $I->seeHttpHeader('Content-Length', (string)$this->size);
        $I->seeHttpHeader('Content-Type', 'application/vnd.apache.parquet');
        $I->assertSame('', $I->grabResponse());
    }

    public function shouldStreamWholeFile(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertSame($this->size, strlen($body));
        $I->assertSame('PAR1', substr($body, 0, 4));
        $I->assertSame('PAR1', substr($body, -4));
    }

    public function shouldServeByteRanges(ApiTester $I)
    {
        $this->asSuper($I);
        $I->haveHttpHeader('Range', 'bytes=0-3');
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::PARTIAL_CONTENT);
        $I->seeHttpHeader('Content-Range', 'bytes 0-3/' . $this->size);
        $I->seeHttpHeader('Content-Length', '4');
        $I->assertSame('PAR1', $I->grabResponse());

        $I->haveHttpHeader('Range', 'bytes=-4');
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::PARTIAL_CONTENT);
        $I->seeHttpHeader('Content-Range', 'bytes ' . ($this->size - 4) . '-' . ($this->size - 1) . '/' . $this->size);
        $I->assertSame('PAR1', $I->grabResponse());

        $I->haveHttpHeader('Range', 'bytes=999999999-');
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(416);
        $I->seeHttpHeader('Content-Range', 'bytes */' . $this->size);

        $I->haveHttpHeader('Range', 'bytes=0-1,4-5');
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Range');
    }

    public function shouldServeMetadataFileByName(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base() . '/' . $this->date);
        $meta = json_decode($I->grabResponse());
        $metaFile = 'metadata-' . $meta->snapshot_id . '.json';
        $I->sendGET($this->base() . '/' . $this->date . '/files/' . $metaFile);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/json');
        $I->assertEquals($this->schema . '.poi', json_decode($I->grabResponse())->source);

        $I->sendGET($this->base() . '/' . $this->date . '/files/nope.parquet');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldEnforcePrivilegesForSubUser(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base() . '/' . $this->date);
        $meta = json_decode($I->grabResponse());
        $metaFile = 'metadata-' . $meta->snapshot_id . '.json';

        $this->asSub($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'INSUFFICIENT_PRIVILEGES']);
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->sendGET($this->base() . '/' . $this->date . '/files/' . $metaFile);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $this->asSuper($I);
        $I->sendPATCH('/api/v4/schemas/' . $this->schema . '/tables/poi/privileges', json_encode(['subuser' => $this->subUserId, 'privilege' => 'read']));
        $I->seeResponseCodeIsSuccessful();

        $this->asSub($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendHEAD($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendHEAD($this->base() . '/' . $this->date . '/files/' . $metaFile);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/json');
    }

    // A snapshot is the whole table in one Parquet file and cannot carry a row
    // filter, so a sub-user whose SQL/OWS access is narrowed by a geofence rule
    // must not be able to download it — even with the read privilege that the
    // previous test granted.
    public function shouldDenySnapshotsToGeofencedSubUser(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/rules', json_encode([
            'username' => $this->subUserId, 'service' => 'sql', 'request' => 'select', 'access' => 'limit',
            'schema' => $this->schema, 'table' => 'poi', 'filter' => "name = 'p1'",
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $ruleId = basename($I->grabHttpHeader('Location'));

        $this->asSub($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'GEOFENCE_RULES_APPLY']);
        $I->sendGET($this->base() . '/' . $this->date);
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        // The super-user owns the schema and is never geofenced.
        $this->asSuper($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendDELETE('/api/v4/rules/' . $ruleId);
        $I->seeResponseCodeIsSuccessful();

        $this->asSub($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendHEAD($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
