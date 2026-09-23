<?php

use Codeception\Util\HttpCode;

/**
 * HTTP contract of the relation snapshot read API
 * (app/api/v4/controllers/RelationSnapshot.php): listing, metadata, HEAD/GET
 * of the data files with byte ranges, one file per produced format, and the
 * privilege check. Runs the snapshot worker inline (same container) after
 * queuing, against whatever storage app/conf/App.php configures.
 * Ordered/stateful.
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
    private $fgbSize;
    private $nogeomDate;
    private $fgbOnlyDate;

    /**
     * FlatGeobuf's file signature: "fgb", the spec's major version byte, then
     * "fgb" again. The eighth byte is the spec's *minor* version (0x01 with the
     * GDAL in this image) and is deliberately not asserted.
     */
    private const FGB_MAGIC = "fgb\x03fgb";

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
        return $this->baseFor('poi');
    }

    private function baseFor(string $relation): string
    {
        return '/api/v4/schemas/' . $this->schema . '/relations/' . $relation . '/snapshots';
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

        // A relation without a geometry column, so one requested format is
        // skipped and /data/{format} has a 404 case to answer.
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => 'nogeom', 'columns' => [
            ['name' => 'gid', 'type' => 'serial'], ['name' => 'name', 'type' => 'varchar'],
        ]]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/sql', json_encode(['q' => "INSERT INTO \"{$this->schema}\".nogeom(name) SELECT 'n'||g FROM generate_series(1, 5) g"]));
        $I->seeResponseCodeIs(HttpCode::OK);

        // A second spatial table, snapshotted as FlatGeobuf only, so one
        // snapshot has no Parquet at all: /data must then serve the single
        // file it does have.
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => 'poi_fgb', 'columns' => [
            ['name' => 'gid', 'type' => 'serial'], ['name' => 'name', 'type' => 'varchar'], ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
        ]]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/sql', json_encode(['q' => "INSERT INTO \"{$this->schema}\".poi_fgb(name, the_geom) SELECT 'q'||g, ST_SetSRID(ST_MakePoint(11+g*0.001, 57), 4326) FROM generate_series(1, 5) g"]));
        $I->seeResponseCodeIs(HttpCode::OK);

        // Every relation in one array request, each with its own formats.
        $I->sendPOST('/api/v4/snapshots', json_encode([
            ['schema' => $this->schema, 'relation' => 'poi', 'formats' => ['parquet', 'flatgeobuf']],
            ['schema' => $this->schema, 'relation' => 'nogeom', 'formats' => ['parquet', 'flatgeobuf']],
            ['schema' => $this->schema, 'relation' => 'poi_fgb', 'formats' => ['flatgeobuf']],
        ]));
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $accepted = json_decode($I->grabResponse());
        $I->assertCount(3, $accepted);
        [$id, $nogeomId, $fgbOnlyId] = [$accepted[0]->id, $accepted[1]->id, $accepted[2]->id];

        // Run the worker inline for this database (the container has no snapshot
        // cron); the batch size is raised so one run drains all three rows.
        $out = shell_exec('GC2_SNAPSHOT_BATCH=3 php -f /var/www/geocloud2/app/scripts/snapshot_worker.php ' . escapeshellarg($this->userId) . ' 2>&1');
        $I->assertStringContainsString('ok=3', (string)$out, "worker output: $out");

        $I->sendGET('/api/v4/snapshots/' . $id);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['status' => 'succeeded']);
        $body = json_decode($I->grabResponse(), true);
        $I->assertSame(['produced', 'produced'], array_column($body['formats'], 'status'), 'the spatial relation gets both formats');
        // Take the date from the job API rather than gmdate() after the run:
        // a midnight-UTC rollover between the worker's gmdate() and ours would
        // otherwise point every later request at a date with no snapshot.
        $this->date = $body['snapshot_date'];
        $I->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string)$this->date);

        $I->sendGET('/api/v4/snapshots/' . $nogeomId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['status' => 'succeeded']);
        $nogeom = json_decode($I->grabResponse(), true);
        $I->assertSame(['produced', 'skipped'], array_column($nogeom['formats'], 'status'), 'FlatGeobuf is skipped without a geometry column');
        $this->nogeomDate = $nogeom['snapshot_date'];

        $I->sendGET('/api/v4/snapshots/' . $fgbOnlyId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['status' => 'succeeded']);
        $fgbOnly = json_decode($I->grabResponse(), true);
        $I->assertSame([['format' => 'flatgeobuf', 'status' => 'produced']], array_map(fn($f) => ['format' => $f['format'], 'status' => $f['status']], $fgbOnly['formats']));
        $this->fgbOnlyDate = $fgbOnly['snapshot_date'];
    }

    public function shouldListSnapshotsOfRelation(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse());
        $I->assertCount(1, $body);
        $s = $body[0];
        $I->assertEquals($this->date, $s->snapshot_date);
        $I->assertEquals(50, $s->row_count);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $s->schema_version);
        $I->assertCount(2, $s->files, 'one file per produced format');
        $byExtension = [];
        foreach ($s->files as $f) {
            $byExtension[pathinfo($f->name, PATHINFO_EXTENSION)] = $f;
            $I->assertEquals($this->base() . '/' . $this->date . '/files/' . $f->name, $f->href);
        }
        $I->assertSame(['parquet', 'fgb'], array_keys($byExtension));
        $this->size = (int)$byExtension['parquet']->size_bytes;
        $this->fgbSize = (int)$byExtension['fgb']->size_bytes;
        $I->assertGreaterThan(8, $this->size);
        $I->assertGreaterThan(8, $this->fgbSize);

        // Every produced format is addressable straight from the list.
        $I->assertSame([
            ['parquet', 'produced', $this->base() . '/' . $this->date . '/data/parquet'],
            ['flatgeobuf', 'produced', $this->base() . '/' . $this->date . '/data/flatgeobuf'],
        ], array_map(fn($f) => [$f->format, $f->status, $f->href], $s->formats));
        $I->assertSame('application/vnd.apache.parquet', $s->formats[0]->media_type);
        $I->assertSame('application/flatgeobuf', $s->formats[1]->media_type);
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
        $I->assertEquals($this->base() . '/' . $this->date . '/data', $body->_links->data, '/data stays the Parquet');
        $I->assertSame(['parquet', 'flatgeobuf'], array_map(fn($f) => $f->format, $body->formats));
        $I->assertEquals($this->base() . '/' . $this->date . '/data/flatgeobuf', $body->formats[1]->href);
        $I->assertSame($this->fgbSize, (int)$body->formats[1]->size_bytes);

        $I->sendGET($this->base() . '/1999-01-01');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'NO_SNAPSHOT_ERROR']);

        $I->sendGET($this->base() . '/not-a-date');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldResolveLatestToTheNewestSnapshot(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base() . '/latest');
        $I->seeResponseCodeIs(HttpCode::OK);
        $latest = json_decode($I->grabResponse());
        $I->assertEquals($this->date, $latest->snapshot_date, 'latest carries the real date');
        $I->assertEquals($this->base() . '/latest', $latest->_links->latest);
        $I->assertEquals($this->base() . '/' . $this->date . '/data', $latest->_links->data, 'dated hrefs pin the snapshot');
        $I->sendGET($this->base() . '/' . $this->date);
        $I->assertEquals($latest, json_decode($I->grabResponse()), 'same object as the dated URL');

        $I->sendHEAD($this->base() . '/latest/data/flatgeobuf');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/flatgeobuf');
        $I->seeHttpHeader('Cache-Control', 'private, no-cache');
        $I->sendGET($this->base() . '/latest/files/metadata-' . $latest->snapshot_id . '.json');
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendGET('/api/v4/schemas/' . $this->schema . '/relations/no_such_relation/snapshots/latest');
        $I->seeResponseCodeIsClientError();
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

    public function shouldAnswerCorsPreflightOnFileRoutes(ApiTester $I)
    {
        // A browser sends OPTIONS before a GET with Authorization/Range headers.
        foreach (['', '/' . $this->date, '/' . $this->date . '/data', '/' . $this->date . '/files/anything'] as $suffix) {
            $I->haveHttpHeader('Origin', 'http://localhost:4001');
            $I->haveHttpHeader('Access-Control-Request-Method', 'GET');
            $I->haveHttpHeader('Access-Control-Request-Headers', 'authorization,range');
            $I->sendOPTIONS($this->base() . $suffix);
            $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
            $I->assertStringContainsStringIgnoringCase('range', $I->grabHttpHeader('Access-Control-Allow-Headers'), 'browsers must be allowed to send Range');
            $I->assertStringContainsStringIgnoringCase('content-range', $I->grabHttpHeader('Access-Control-Expose-Headers'), 'scripts must be able to read Content-Range');
        }
        $I->deleteHeader('Origin');
        $I->deleteHeader('Access-Control-Request-Method');
        $I->deleteHeader('Access-Control-Request-Headers');
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

    // An `allow` rule grants the relation unfiltered — which is exactly what a
    // snapshot is — so it must not be mistaken for "this user is geofenced".
    // Only deny/limit, which a whole-table file cannot honour, block the read.
    public function shouldNotBlockSubUserOnAllowRule(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/rules', json_encode([
            'username' => $this->subUserId, 'service' => 'sql', 'request' => 'select', 'access' => 'allow',
            'schema' => $this->schema, 'table' => 'poi',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $ruleId = basename($I->grabHttpHeader('Location'));

        $this->asSub($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendHEAD($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);

        $this->asSuper($I);
        $I->sendDELETE('/api/v4/rules/' . $ruleId);
        $I->seeResponseCodeIsSuccessful();
    }

    public function shouldServeAChosenFormat(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base() . '/' . $this->date . '/data/flatgeobuf');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/flatgeobuf');
        $I->seeHttpHeader('Accept-Ranges', 'bytes');
        $body = $I->grabResponse();
        $I->assertSame($this->fgbSize, strlen($body), 'the whole FlatGeobuf file');
        $I->assertSame(self::FGB_MAGIC, substr($body, 0, 7), 'FlatGeobuf signature');
        $this->seeContentLengthIfTrusted($I, $this->fgbSize);

        $I->sendHEAD($this->base() . '/' . $this->date . '/data/flatgeobuf');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/flatgeobuf');
        $I->seeHttpHeader('Accept-Ranges', 'bytes');
        $I->assertSame('', $I->grabResponse());
        $this->seeContentLengthIfTrusted($I, $this->fgbSize);

        // /data/parquet is the same file /data serves.
        $I->sendGET($this->base() . '/' . $this->date . '/data/parquet');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/vnd.apache.parquet');
        $I->assertSame($this->size, strlen($I->grabResponse()));
    }

    public function shouldServeByteRangesOfAChosenFormat(ApiTester $I)
    {
        $this->asSuper($I);
        $I->haveHttpHeader('Range', 'bytes=0-7');
        $I->sendGET($this->base() . '/' . $this->date . '/data/flatgeobuf');
        $I->seeResponseCodeIs(HttpCode::PARTIAL_CONTENT);
        $I->seeHttpHeader('Content-Range', 'bytes 0-7/' . $this->fgbSize);
        $I->seeHttpHeader('Content-Type', 'application/flatgeobuf');
        // Content-Length on a 206 needs the vhost's ap_trust_cgilike_cl.
        $this->seeContentLengthIfTrusted($I, 8);
        // The body itself arrives whole even where Content-Length does not.
        $body = $I->grabResponse();
        $I->assertSame(8, strlen($body));
        $I->assertSame(self::FGB_MAGIC, substr($body, 0, 7), 'the file signature, from the first byte range');

        $I->haveHttpHeader('Range', 'bytes=999999999-');
        $I->sendGET($this->base() . '/' . $this->date . '/data/flatgeobuf');
        $I->seeResponseCodeIs(416);
        $I->seeHttpHeader('Content-Range', 'bytes */' . $this->fgbSize);
        $I->deleteHeader('Range');
    }

    public function shouldRejectAnUnknownFormat(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base() . '/' . $this->date . '/data/geojson');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INVALID_REQUEST']);
        $message = json_decode($I->grabResponse())->message;
        $I->assertStringContainsString('geojson', $message);
        $I->assertStringContainsString('flatgeobuf', $message);

        // The segment is validated before anything looks it up.
        $I->sendGET($this->base() . '/' . $this->date . '/data/FlatGeobuf');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INVALID_REQUEST']);
        $I->sendGET($this->base() . '/' . $this->date . '/data/flat-geobuf');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldReturn404ForAFormatThatWasNotProduced(ApiTester $I)
    {
        $this->asSuper($I);
        $base = $this->baseFor('nogeom');
        $I->sendGET($base . '/' . $this->nogeomDate);
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse());
        $I->assertSame(['parquet', 'flatgeobuf'], array_map(fn($f) => $f->format, $body->formats));
        $I->assertSame('skipped', $body->formats[1]->status);
        $I->assertFalse(property_exists($body->formats[1], 'href'), 'a skipped format has nothing to download');
        $I->assertStringContainsString('geometry', $body->formats[1]->reason);

        $I->sendGET($base . '/' . $this->nogeomDate . '/data/flatgeobuf');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'NO_SNAPSHOT_ERROR']);

        // The one format it does have is still served by bare /data.
        $I->sendGET($base . '/' . $this->nogeomDate . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/vnd.apache.parquet');
        $I->assertSame('PAR1', substr($I->grabResponse(), 0, 4));
    }


    public function shouldServeTheOnlyFormatOfAParquetLessSnapshot(ApiTester $I)
    {
        $this->asSuper($I);
        $base = $this->baseFor('poi_fgb');
        $I->sendGET($base . '/' . $this->fgbOnlyDate);
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse());
        $I->assertSame(['flatgeobuf'], array_map(fn($f) => $f->format, $body->formats));
        $I->assertEquals($base . '/' . $this->fgbOnlyDate . '/data/flatgeobuf', $body->formats[0]->href);
        $I->assertFalse(property_exists($body->_links, 'data'), 'no Parquet, so no _links.data');

        // Bare /data has only one file to choose from and serves it.
        $I->sendGET($base . '/' . $this->fgbOnlyDate . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/flatgeobuf');
        $I->assertSame(self::FGB_MAGIC, substr($I->grabResponse(), 0, 7));

        $I->sendGET($base . '/' . $this->fgbOnlyDate . '/data/parquet');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'NO_SNAPSHOT_ERROR']);
    }

    /**
     * Content-Length only survives HEAD and 206 when the vhost sets
     * `SetEnv ap_trust_cgilike_cl 1` (AGENTS.md section 6); the running image
     * may predate that, and rebuilding it is not this Cest's business. Where
     * the header is there it must be right.
     */
    private function seeContentLengthIfTrusted(ApiTester $I, int $expected): void
    {
        $length = $I->grabHttpHeader('Content-Length');
        if ($length !== null && $length !== '') {
            $I->assertSame((string)$expected, (string)$length);
        }
    }
}
