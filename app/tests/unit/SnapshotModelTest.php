<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\models\Snapshot;
use Codeception\Test\Unit;

/**
 * Integration test for the snapshot queue model. Provisions a fresh database
 * through the v2 user API (needs the in-container web server on localhost)
 * and skips cleanly when it cannot be reached.
 */
class SnapshotModelTest extends Unit
{
    protected UnitTester $tester;

    private const string API = 'http://localhost';
    private const string PASSWORD = 'A1abcabcabc';

    private static ?string $database = null;

    protected function _before(): void
    {
        if (self::$database !== null) {
            return;
        }
        $ts = (string)(new DateTime())->getTimestamp() . bin2hex(random_bytes(3));
        $created = $this->post('/api/v2/user', [
            'name' => 'Snapshot model test ' . $ts,
            'email' => 'snapmodel' . $ts . '@example.com',
            'password' => self::PASSWORD,
        ]);
        if ($created === null || empty($created['data']['screenname'])) {
            $this->markTestSkipped('GC2 API not reachable; skipping snapshot model test.');
        }
        self::$database = $created['data']['screenname'];
    }

    private function model(): Snapshot
    {
        return new Snapshot(new Connection(database: self::$database));
    }

    public function testCreateReturnsUuidAndGetReadsPendingRow(): void
    {
        $m = $this->model();
        $uuid = $m->create('public', 'roads', 25832, self::$database, ['parquet']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuid);

        $row = $m->get($uuid)['data'];
        $this->assertSame('public', $row['schema_name']);
        $this->assertSame('roads', $row['relation_name']);
        $this->assertSame(25832, (int)$row['srs']);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['started']);
        $this->assertNull($row['finished']);
    }

    public function testCreateWithoutSrsStoresNull(): void
    {
        $m = $this->model();
        $uuid = $m->create('public', 'nosrs', null, self::$database, ['parquet']);
        $this->assertNull($m->get($uuid)['data']['srs']);
    }

    public function testGetUnknownUuidThrows404(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(404);
        $this->model()->get('00000000-0000-0000-0000-000000000000');
    }

    public function testGetMalformedUuidThrows404NotDbError(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(404);
        $this->model()->get('not-a-uuid');
    }

    public function testHasActiveSeesPendingAndRunningButNotFinished(): void
    {
        $m = $this->model();
        $this->assertFalse($m->hasActive('public', 'active_' . __LINE__));

        $rel = 'active_rel';
        $uuid = $m->create('public', $rel, null, self::$database, ['parquet']);
        $this->assertTrue($m->hasActive('public', $rel));

        $claimed = $m->claimPending(100);
        $this->assertContains($uuid, array_column($claimed, 'uuid'));
        $this->assertTrue($m->hasActive('public', $rel));

        $m->finish($uuid, 'succeeded', 's3://b/p/', 3, null);
        $this->assertFalse($m->hasActive('public', $rel));
    }

    public function testClaimPendingFlipsToRunningSetsStartedAndRespectsLimit(): void
    {
        $m = $this->model();
        // Drain anything left by other tests so the limit assertion is exact.
        $m->claimPending(1000);

        $a = $m->create('public', 'claim_a', null, self::$database, ['parquet']);
        $b = $m->create('public', 'claim_b', null, self::$database, ['parquet']);

        $first = $m->claimPending(1);
        $this->assertCount(1, $first);
        $this->assertSame($a, $first[0]['uuid'], 'oldest pending row is claimed first');
        $this->assertSame('running', $first[0]['status']);
        $this->assertNotNull($first[0]['started']);

        $second = $m->claimPending(1);
        $this->assertCount(1, $second);
        $this->assertSame($b, $second[0]['uuid']);

        $this->assertCount(0, $m->claimPending(1));
    }

    public function testFinishStoresOutcomeAndFinishedTimestamp(): void
    {
        $m = $this->model();
        $ok = $m->create('public', 'finish_ok', null, self::$database, ['parquet']);
        $columns = [['column_name' => 'gid', 'data_type' => 'integer'], ['column_name' => 'geom', 'data_type' => 'geometry(Point,25832)']];
        // finish() only finalises a claimed ('running') row, so claim first.
        $m->claimPending(1000);
        $m->finish($ok, 'succeeded', 's3://bucket/x/', 42, null, 'abc123', $columns);
        $row = $m->get($ok)['data'];
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame('s3://bucket/x/', $row['s3_path']);
        $this->assertSame(42, (int)$row['row_count']);
        $this->assertNull($row['error']);
        $this->assertSame('abc123', $row['schema_version']);
        // assertEquals: jsonb reorders object keys, the content must match.
        $this->assertEquals($columns, json_decode($row['relation_schema'], true), 'relation_schema round-trips through jsonb');
        $this->assertNotNull($row['finished']);

        $bad = $m->create('public', 'finish_bad', null, self::$database, ['parquet']);
        $m->claimPending(1000);
        $m->finish($bad, 'failed', null, null, 'ogr2ogr exploded');
        $row = $m->get($bad)['data'];
        $this->assertSame('failed', $row['status']);
        $this->assertSame('ogr2ogr exploded', $row['error']);
        $this->assertNull($row['s3_path']);
        $this->assertNull($row['schema_version']);
        $this->assertNull($row['relation_schema']);
    }

    /**
     * A worker whose row was reclaimed past the stale window (and re-published
     * by the winner) must not be able to flip it back to 'failed'. finish()
     * therefore only touches rows that are still 'running'.
     */
    public function testFinishIgnoresRowsNotRunning(): void
    {
        $m = $this->model();
        $uuid = $m->create('finish_guard', 'rel', null, self::$database, ['parquet']);
        // Not claimed: the row is 'pending', so no worker owns it.
        $m->finish($uuid, 'failed', null, null, 'not mine to fail');

        $row = $m->get($uuid)['data'];
        $this->assertSame('pending', $row['status'], 'an unclaimed row is left alone');
        $this->assertNull($row['error']);
        $this->assertNull($row['finished']);
    }

    public function testStaleRunningRowIsReclaimedButFreshRunningRowIsNot(): void
    {
        $m = $this->model();
        // Drain anything left by other tests so claimPending(100) below is exact.
        $m->claimPending(1000);

        $stale = $m->create('public', 'stale_rel', null, self::$database, ['parquet']);
        $fresh = $m->create('public', 'fresh_rel', null, self::$database, ['parquet']);

        $claimed = $m->claimPending(100);
        $staleRow = $claimed[array_search($stale, array_column($claimed, 'uuid'), true)];
        $freshRow = $claimed[array_search($fresh, array_column($claimed, 'uuid'), true)];
        $this->assertSame('running', $staleRow['status']);
        $this->assertSame('running', $freshRow['status']);
        $backdatedStarted = $staleRow['started'];

        // Backdate the stale row's started past STALE_RUNNING_INTERVAL, as if
        // its worker had died mid-run.
        $res = $m->prepare("UPDATE settings.snapshots SET started = now() - interval '3 hours' WHERE uuid = :uuid");
        $m->execute($res, ['uuid' => $stale]);

        $this->assertFalse($m->hasActive('public', 'stale_rel'), 'a running row stuck past the stale interval is no longer active');
        $this->assertTrue($m->hasActive('public', 'fresh_rel'), 'a recently claimed running row is still active');

        $reclaimed = $m->claimPending(100);
        $reclaimedUuids = array_column($reclaimed, 'uuid');
        $this->assertContains($stale, $reclaimedUuids, 'the stale running row is reclaimed');
        $this->assertNotContains($fresh, $reclaimedUuids, 'the fresh running row is not re-claimed');

        $reclaimedRow = $reclaimed[array_search($stale, $reclaimedUuids, true)];
        $this->assertSame('running', $reclaimedRow['status']);
        $this->assertGreaterThan($backdatedStarted, $reclaimedRow['started'], 'reclaiming gives the row a fresh started');
    }

    public function testListIsNewestFirstAndFilters(): void
    {
        $m = $this->model();
        $m->create('lst', 'one', null, self::$database, ['parquet']);
        $newest = $m->create('lst', 'two', null, self::$database, ['parquet']);

        $all = $m->list();
        $this->assertSame($newest, $all[0]['uuid']);

        $filtered = $m->list('lst', 'one');
        $this->assertCount(1, $filtered);
        $this->assertSame('one', $filtered[0]['relation_name']);

        $bySchema = $m->list('lst');
        $this->assertCount(2, $bySchema);

        $this->assertCount(1, $m->list(null, null, 1));
    }

    public function testPublishMakesRowVisibleAndSupersedesSameDate(): void
    {
        $m = $this->model();
        $files = [['name' => 'data-a.parquet', 'size_bytes' => 10]];
        $cols = [['column_name' => 'gid', 'data_type' => 'integer']];

        $first = $m->create('pub', 'rel', null, self::$database, ['parquet']);
        $m->claimPending(100);
        $this->assertNull($m->publish($first, '2026-09-16', 's3://b/x/', 5, 'v1', $cols, $files));

        $row = $m->getPublished('pub', 'rel', '2026-09-16')['data'];
        $this->assertSame($first, $row['uuid']);
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame('2026-09-16', $row['snapshot_date']);
        $this->assertSame(10, (int)$row['size_bytes']);
        $this->assertEquals($files, json_decode($row['files'], true));
        $this->assertNotNull($row['published']);
        $this->assertNotNull($row['finished']);

        $second = $m->create('pub', 'rel', null, self::$database, ['parquet']);
        $m->claimPending(100);
        $this->assertSame($first, $m->publish($second, '2026-09-16', 's3://b/y/', 6, 'v1', $cols, [['name' => 'data-b.parquet', 'size_bytes' => 12]]));

        $this->assertSame('superseded', $m->get($first)['data']['status']);
        $this->assertSame($second, $m->getPublished('pub', 'rel', '2026-09-16')['data']['uuid']);
        $this->assertCount(1, $m->listPublished('pub', 'rel'));
    }

    public function testListPublishedIsNewestDateFirstAndHidesOtherStates(): void
    {
        $m = $this->model();
        $cols = [['column_name' => 'gid', 'data_type' => 'integer']];
        $f = [['name' => 'data-x.parquet', 'size_bytes' => 1]];

        $old = $m->create('lp', 'rel', null, self::$database, ['parquet']);
        $new = $m->create('lp', 'rel', null, self::$database, ['parquet']);
        $failed = $m->create('lp', 'rel', null, self::$database, ['parquet']);
        $pending = $m->create('lp', 'rel', null, self::$database, ['parquet']);
        $m->claimPending(3); // claims old, new, failed (oldest first); pending stays pending
        $m->publish($old, '2026-09-14', 's3://b/1/', 1, 'v', $cols, $f);
        $m->publish($new, '2026-09-15', 's3://b/2/', 1, 'v', $cols, $f);
        $m->finish($failed, 'failed', null, null, 'boom');

        $list = $m->listPublished('lp', 'rel');
        $this->assertSame(['2026-09-15', '2026-09-14'], array_column($list, 'snapshot_date'));
        $this->assertNotContains($failed, array_column($list, 'uuid'));
        $this->assertNotContains($pending, array_column($list, 'uuid'));
        $this->assertSame([], $m->listPublished('lp', 'other'));
    }

    public function testGetPublishedUnknownDateThrows404(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(404);
        $this->model()->getPublished('lp', 'rel', '1999-01-01');
    }

    public function testPublishUnknownUuidThrows404(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(404);
        $this->model()->publish('00000000-0000-0000-0000-000000000000', '2026-09-16', 's3://b/x/', 1, 'v1', [], []);
    }

    public function testPublishRequiresRunningRow(): void
    {
        $m = $this->model();
        $uuid = $m->create('pub_unclaimed', 'rel', null, self::$database, ['parquet']);
        // Not claimed: row is still 'pending', not 'running'.
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(404);
        $m->publish($uuid, '2026-09-16', 's3://b/x/', 1, 'v1', [], []);
    }

    public function testListAllPublishedIsOrderedDecodedAndShowsOnlyVisibleRows(): void
    {
        $m = $this->model();
        $cols = [['column_name' => 'gid', 'data_type' => 'integer']];
        $f = [['name' => 'data-x.parquet', 'size_bytes' => 7]];

        $aOld = $m->create('zcat', 'a', null, self::$database, ['parquet']);
        $aNew = $m->create('zcat', 'a', null, self::$database, ['parquet']);
        $b = $m->create('zcat', 'b', null, self::$database, ['parquet']);
        $failed = $m->create('zcat', 'b', null, self::$database, ['parquet']);
        $unpublished = $m->create('zcat', 'c', null, self::$database, ['parquet']);
        $pending = $m->create('zcat', 'd', null, self::$database, ['parquet']);
        $m->claimPending(5); // pending stays pending
        $m->publish($aOld, '2026-09-10', 's3://b/1/', 1, 'v1', $cols, $f);
        $m->publish($aNew, '2026-09-12', 's3://b/2/', 2, 'v2', $cols, $f, [1.5, 2.5, 3.5, 4.5]);
        $m->publish($b, '2026-09-11', 's3://b/3/', 3, 'v1', $cols, $f);
        $m->finish($failed, 'failed', null, null, 'boom');
        // Succeeded but never published (finish(), not publish()): invisible.
        $m->finish($unpublished, 'succeeded', 's3://b/4/', 4, null);

        $all = $m->listAllPublished();
        $uuids = array_column($all, 'uuid');
        $this->assertNotContains($failed, $uuids);
        $this->assertNotContains($pending, $uuids);
        $this->assertNotContains($unpublished, $uuids, 'only rows with a published timestamp are in the catalog');

        $mine = array_values(array_filter($all, fn($r) => $r['schema_name'] === 'zcat'));
        $this->assertSame(
            [['a', '2026-09-12'], ['a', '2026-09-10'], ['b', '2026-09-11']],
            array_map(fn($r) => [$r['relation_name'], $r['snapshot_date']], $mine),
            'by relation, newest date first'
        );

        $sortKeys = array_map(fn($r) => [$r['schema_name'], $r['relation_name']], $all);
        $sorted = $sortKeys;
        sort($sorted);
        $this->assertSame($sorted, $sortKeys, 'the whole list is grouped by schema and relation');

        $this->assertEquals($f, $mine[0]['files'], 'files is decoded');
        $this->assertSame([1.5, 2.5, 3.5, 4.5], $mine[0]['bbox'], 'bbox is decoded');
        $this->assertNull($mine[1]['bbox'], 'a snapshot published without a bbox has none');
        $this->assertSame(2, (int)$mine[0]['row_count']);
    }

    public function testRelationMetaReadsTitleAbstractAndTagsAndSkipsUnknownRelations(): void
    {
        $m = $this->model();
        $res = $m->prepare("CREATE SCHEMA IF NOT EXISTS meta_rel");
        $m->execute($res);
        $res = $m->prepare("CREATE TABLE IF NOT EXISTS meta_rel.pts (gid serial PRIMARY KEY, the_geom geometry(Point, 4326))");
        $m->execute($res);
        $res = $m->prepare("INSERT INTO settings.geometry_columns_join (_key_, f_table_title, f_table_abstract, tags)
                            VALUES ('meta_rel.pts.the_geom', 'Points of interest', 'What it is', '[\"poi\", \"test\"]')
                            ON CONFLICT (_key_) DO UPDATE SET f_table_title = excluded.f_table_title");
        $m->execute($res);

        $meta = $m->relationMeta(['meta_rel.pts', 'meta_rel.nothing_here']);
        $this->assertArrayNotHasKey('meta_rel.nothing_here', $meta, 'an unregistered relation has no metadata row');
        $this->assertSame('Points of interest', $meta['meta_rel.pts']['title']);
        $this->assertSame('What it is', $meta['meta_rel.pts']['description']);
        $this->assertSame(['poi', 'test'], $meta['meta_rel.pts']['keywords']);

        $this->assertSame([], $m->relationMeta([]), 'no relations, no query');
    }

    /**
     * A blank title or abstract is no metadata: reported as null, so a
     * relation with several geometry columns is described by whichever row
     * actually says something.
     */
    public function testRelationMetaTreatsBlankTitleAndAbstractAsMissing(): void
    {
        $m = $this->model();
        $res = $m->prepare("CREATE SCHEMA IF NOT EXISTS meta_blank");
        $m->execute($res);
        $res = $m->prepare("CREATE TABLE IF NOT EXISTS meta_blank.pts (gid serial PRIMARY KEY, the_geom geometry(Point, 4326))");
        $m->execute($res);
        $res = $m->prepare("INSERT INTO settings.geometry_columns_join (_key_, f_table_title, f_table_abstract)
                            VALUES ('meta_blank.pts.the_geom', '   ', '')
                            ON CONFLICT (_key_) DO UPDATE SET f_table_title = excluded.f_table_title, f_table_abstract = excluded.f_table_abstract");
        $m->execute($res);

        $meta = $m->relationMeta(['meta_blank.pts']);
        $this->assertNull($meta['meta_blank.pts']['title']);
        $this->assertNull($meta['meta_blank.pts']['description']);
        $this->assertSame([], $meta['meta_blank.pts']['keywords']);
    }

    public function testCreateStoresTheRequestedFormats(): void
    {
        $m = $this->model();
        $uuid = $m->create('fmt', 'rel', null, self::$database, ['parquet', 'flatgeobuf']);

        $row = $m->get($uuid)['data'];
        $this->assertSame(['parquet', 'flatgeobuf'], $row['formats'], 'the requested list comes back decoded, in request order');
        $this->assertSame(
            [
                ['format' => 'parquet', 'status' => 'requested'],
                ['format' => 'flatgeobuf', 'status' => 'requested'],
            ],
            Snapshot::presentFormats($row),
            'before the worker runs, every requested format is only requested'
        );
    }

    public function testPublishStoresThePerFormatResults(): void
    {
        $m = $this->model();
        $cols = [['column_name' => 'gid', 'data_type' => 'integer']];
        $uuid = $m->create('fmtpub', 'rel', null, self::$database, ['parquet', 'flatgeobuf']);
        $m->claimPending(100);

        $files = [
            ['name' => "data-$uuid.parquet", 'size_bytes' => 120],
            ['name' => "data-$uuid.fgb", 'size_bytes' => 80],
        ];
        $results = [
            ['format' => 'parquet', 'status' => 'produced', 'file' => "data-$uuid.parquet", 'size_bytes' => 120, 'media_type' => 'application/vnd.apache.parquet'],
            ['format' => 'flatgeobuf', 'status' => 'produced', 'file' => "data-$uuid.fgb", 'size_bytes' => 80, 'media_type' => 'application/flatgeobuf'],
        ];
        $m->publish($uuid, '2026-09-21', 's3://b/f/', 4, 'v1', $cols, $files, [1.0, 2.0, 3.0, 4.0], $results);

        $row = $m->getPublished('fmtpub', 'rel', '2026-09-21')['data'];
        // assertEquals, not assertSame: jsonb reorders the keys of each object.
        $this->assertEquals($results, $row['formats'], 'the results replace the requested list on publish');
        $this->assertSame($results, Snapshot::presentFormats($row), 'the presenter normalises the stored results');
        $this->assertSame(200, (int)$row['size_bytes'], 'size_bytes still sums every file');

        $this->assertEquals($results, $m->list('fmtpub', 'rel')[0]['formats'], 'list() decodes formats too');
        $this->assertEquals($results, $m->listPublished('fmtpub', 'rel')[0]['formats']);
        $published = array_values(array_filter($m->listAllPublished(), fn($r) => $r['uuid'] === $uuid));
        $this->assertEquals($results, $published[0]['formats']);
    }

    public function testPublishStoresASkippedFormatWithItsReason(): void
    {
        $m = $this->model();
        $uuid = $m->create('fmtskip', 'rel', null, self::$database, ['parquet', 'flatgeobuf']);
        $m->claimPending(100);
        $results = [
            ['format' => 'parquet', 'status' => 'produced', 'file' => "data-$uuid.parquet", 'size_bytes' => 9, 'media_type' => 'application/vnd.apache.parquet'],
            ['format' => 'flatgeobuf', 'status' => 'skipped', 'reason' => 'relation has no geometry column'],
        ];
        $cols = [['column_name' => 'gid', 'data_type' => 'integer']];
        $m->publish($uuid, '2026-09-21', 's3://b/s/', 1, 'v1', $cols, [['name' => "data-$uuid.parquet", 'size_bytes' => 9]], null, $results);

        $presented = Snapshot::presentFormats($m->get($uuid)['data']);
        $this->assertSame($results[1], $presented[1], 'a skipped format keeps its reason and carries no file');
    }

    public function testPresentFormatsDerivesFromFilesForARowWithoutTheColumn(): void
    {
        $uuid = '11112222-3333-4444-5555-666677778888';
        $row = [
            'uuid' => $uuid,
            'status' => 'succeeded',
            'formats' => [],
            'files' => json_encode([
                ['name' => "data-$uuid.parquet", 'size_bytes' => 4096],
                ['name' => "data-$uuid.fgb", 'size_bytes' => 512],
                ['name' => "metadata-$uuid.json", 'size_bytes' => 300],
            ]),
        ];
        $this->assertSame(
            [
                ['format' => 'parquet', 'status' => 'produced', 'file' => "data-$uuid.parquet", 'size_bytes' => 4096, 'media_type' => 'application/vnd.apache.parquet'],
                ['format' => 'flatgeobuf', 'status' => 'produced', 'file' => "data-$uuid.fgb", 'size_bytes' => 512, 'media_type' => 'application/flatgeobuf'],
            ],
            Snapshot::presentFormats($row),
            'a row written before the formats column is described by its files; metadata.json is not a format'
        );
    }

    public function testPresentFormatsOfARowWithNeitherFormatsNorFilesIsEmpty(): void
    {
        $this->assertSame([], Snapshot::presentFormats(['status' => 'pending', 'formats' => null, 'files' => null]));
        $this->assertSame([], Snapshot::presentFormats([]));
    }

    private function post(string $path, array $body): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => json_encode($body),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents(self::API . $path, false, $ctx);
        if ($raw === false) {
            return null;
        }
        return json_decode($raw, true);
    }
}
