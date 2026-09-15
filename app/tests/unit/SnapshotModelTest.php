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
        $uuid = $m->create('public', 'roads', 25832, self::$database);
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
        $uuid = $m->create('public', 'nosrs', null, self::$database);
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
        $uuid = $m->create('public', $rel, null, self::$database);
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

        $a = $m->create('public', 'claim_a', null, self::$database);
        $b = $m->create('public', 'claim_b', null, self::$database);

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
        $ok = $m->create('public', 'finish_ok', null, self::$database);
        $m->finish($ok, 'succeeded', 's3://bucket/x/', 42, null);
        $row = $m->get($ok)['data'];
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame('s3://bucket/x/', $row['s3_path']);
        $this->assertSame(42, (int)$row['row_count']);
        $this->assertNull($row['error']);
        $this->assertNotNull($row['finished']);

        $bad = $m->create('public', 'finish_bad', null, self::$database);
        $m->finish($bad, 'failed', null, null, 'ogr2ogr exploded');
        $row = $m->get($bad)['data'];
        $this->assertSame('failed', $row['status']);
        $this->assertSame('ogr2ogr exploded', $row['error']);
        $this->assertNull($row['s3_path']);
    }

    public function testListIsNewestFirstAndFilters(): void
    {
        $m = $this->model();
        $m->create('lst', 'one', null, self::$database);
        $newest = $m->create('lst', 'two', null, self::$database);

        $all = $m->list();
        $this->assertSame($newest, $all[0]['uuid']);

        $filtered = $m->list('lst', 'one');
        $this->assertCount(1, $filtered);
        $this->assertSame('one', $filtered[0]['relation_name']);

        $bySchema = $m->list('lst');
        $this->assertCount(2, $bySchema);

        $this->assertCount(1, $m->list(null, null, 1));
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
