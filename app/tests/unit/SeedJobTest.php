<?php
use app\inc\Connection;
use app\models\SeedJob;
use Codeception\Test\Unit;

/**
 * The tile seeder queue. Rows live in the tenant's settings.seed_jobs, and every
 * fact a client needs is in the row, so status and cancel work from any node.
 */
class SeedJobTest extends Unit
{
    protected UnitTester $tester;
    private array $created = [];

    private function model(): SeedJob
    {
        try {
            $m = new SeedJob(connection: new Connection(database: 'mydb'));
            $m->prepare("SELECT 1 FROM settings.seed_jobs LIMIT 1")->execute();
            return $m;
        } catch (Throwable $e) {
            $this->markTestSkipped('mydb not reachable: ' . $e->getMessage());
        }
    }

    private function queue(SeedJob $m, array $extra = []): array
    {
        $row = $m->queue(array_merge([
            'name' => 'test seed ' . uniqid(),
            'tileset' => 'myschema.roads',
            'grid' => 'GoogleMapsCompatible',
            'zoom_start' => 0,
            'zoom_end' => 3,
            'extent_layer' => null,
            'threads' => 1,
            'username' => 'tester',
        ], $extra));
        $this->created[] = $row['uuid'];
        return $row;
    }

    protected function _after(): void
    {
        if ($this->created) {
            $m = new SeedJob(connection: new Connection(database: 'mydb'));
            foreach ($this->created as $uuid) {
                $res = $m->prepare("DELETE FROM settings.seed_jobs WHERE uuid = :u");
                $m->execute($res, ['u' => $uuid]);
            }
        }
    }

    public function testQueuedRowStartsPendingWithoutPidOrHost(): void
    {
        $m = $this->model();
        $row = $this->queue($m);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['pid'], 'a pending job has no process yet');
        $this->assertNull($row['host']);
        $this->assertNull($row['started']);

        $read = $m->get($row['uuid']);
        $this->assertSame($row['uuid'], $read['uuid']);
        $presented = SeedJob::present($read);
        $this->assertSame('pending', $presented['status']);
        $this->assertFalse($presented['stale']);
        $this->assertSame(0, $presented['zoom_start'], 'zooms are integers, not strings');
        $this->assertArrayNotHasKey('log', $presented, 'the list shape carries no log');
        $this->assertArrayHasKey('log', SeedJob::present($read, withLog: true));
    }

    public function testClaimTakesTheOldestPendingRowAndOnlyOnce(): void
    {
        $m = $this->model();
        $first = $this->queue($m, ['name' => 'claim one ' . uniqid()]);
        $second = $this->queue($m, ['name' => 'claim two ' . uniqid()]);

        $claimed = $m->claimOne();
        $this->assertNotNull($claimed);
        $this->assertSame($first['uuid'], $claimed['uuid'], 'oldest first');
        $this->assertSame('running', $claimed['status']);
        $this->assertNotNull($claimed['started']);
        $this->assertNotNull($claimed['heartbeat'], 'the claim seeds the heartbeat, so the reaper has a clock');

        // A second claimer on its own connection must get the next row, never the same one.
        $other = new SeedJob(connection: new Connection(database: 'mydb'));
        $claimedByOther = $other->claimOne();
        $this->assertNotSame($claimed['uuid'], $claimedByOther['uuid'] ?? null,
            'FOR UPDATE SKIP LOCKED must not hand the same row to two workers');
        $this->assertSame($second['uuid'], $claimedByOther['uuid']);

        $this->assertNull($m->claimOne(), 'nothing left to claim');
    }

    public function testCancelIsImmediateWhenPendingAndCooperativeWhenRunning(): void
    {
        $m = $this->model();
        $pending = $this->queue($m, ['name' => 'cancel pending ' . uniqid()]);
        $this->assertSame('cancelled', $m->requestCancel($pending['uuid']));
        $row = $m->get($pending['uuid']);
        $this->assertSame('cancelled', $row['status']);
        $this->assertNotNull($row['finished']);
        $this->assertSame('noop', $m->requestCancel($pending['uuid']), 'cancelling twice is idempotent');

        $running = $this->queue($m, ['name' => 'cancel running ' . uniqid()]);
        $m->claimOne();
        $this->assertSame('cancelling', $m->requestCancel($running['uuid']));
        $this->assertTrue($m->isCancelRequested($running['uuid']));
        $this->assertSame('running', $m->get($running['uuid'])['status'], 'the worker finishes it, not the API');
    }

    public function testStaleRunningRowIsReapedAsFailed(): void
    {
        $m = $this->model();
        $row = $this->queue($m, ['name' => 'stale ' . uniqid()]);
        $m->claimOne();
        // Backdate the heartbeat past the stale window, as if the run were killed.
        $res = $m->prepare("UPDATE settings.seed_jobs
                               SET heartbeat = now() - interval '" . SeedJob::STALE_RUNNING_INTERVAL . "' - interval '1 minute'
                             WHERE uuid = :u");
        $m->execute($res, ['u' => $row['uuid']]);

        $this->assertGreaterThanOrEqual(1, $m->reapStale());
        $reaped = $m->get($row['uuid']);
        $this->assertSame('failed', $reaped['status']);
        $this->assertStringContainsString('stale', (string)$reaped['error']);
        $this->assertTrue(SeedJob::present($reaped)['stale'] === false || $reaped['status'] === 'failed');
    }

    public function testLegacyRowsAreNeverClaimedAndKeepANullStatus(): void
    {
        $m = $this->model();
        // What the pre-v4 code and MapcacheTileset write: no status at all.
        $uuid = null;
        $res = $m->prepare("INSERT INTO settings.seed_jobs (name, pid, host) VALUES ('legacy job', 4242, 'old-node') RETURNING uuid");
        $m->execute($res);
        $uuid = $m->fetchRow($res)['uuid'];
        $this->created[] = $uuid;

        $this->assertNull($m->get($uuid)['status']);
        $this->assertSame('noop', $m->requestCancel($uuid), 'a legacy row cannot be cancelled');
        $presented = SeedJob::present($m->get($uuid));
        $this->assertNull($presented['status']);
        $this->assertFalse($presented['stale'], 'a row with no status is not a stuck run');

        // Claiming must ignore it even when it is the oldest row.
        $fresh = $this->queue($m, ['name' => 'after legacy ' . uniqid()]);
        $this->assertSame($fresh['uuid'], $m->claimOne()['uuid']);
    }
}
