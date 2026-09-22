<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\models\Job;
use Codeception\Test\Unit;

/**
 * Regression test for the scheduler's boolean columns (delete_append,
 * download_schema, active, snapshot).
 *
 * Two failure modes, one binding:
 *
 * - PDOStatement::execute(array) binds every value as PARAM_STR, so a raw PHP
 *   `false` reaches Postgres as the empty string and a BOOL column rejects it
 *   with 22P02 "invalid input syntax for type boolean". That made *every*
 *   create/update fail whenever a boolean was false or the (optional)
 *   snapshot property was omitted.
 * - The ExtJS scheduler submits unchecked checkboxes as the *string* "false"
 *   (uncheckedValue in public/scheduler/app/view/MyWindow.js) and
 *   app/controllers/Job.php does not normalise the body, so a truthiness test
 *   would store an unchecked Active/Delete-append/Download-schema as true.
 *
 * Job::newJob/updateJob therefore bind 0/1 via FILTER_VALIDATE_BOOLEAN.
 */
class JobSnapshotFlagTest extends Unit
{
    protected UnitTester $tester;

    private const string DB = 'snapflagdb';

    private function job(): Job
    {
        try {
            $job = new Job(new Connection(database: 'gc2scheduler'));
            $job->prepare("SELECT 1 FROM jobs LIMIT 1")->execute();
            // jobs rows are routinely restored/imported with explicit ids, which
            // leaves jobs_id_seq behind max(id) and makes the next INSERT fail
            // with a 23505 on jobs_pkey — for this test and for the scheduler UI
            // alike. Pull the sequence forward (never back) so the inserts below
            // test the binding and not the state of the shared dev database.
            $job->prepare("SELECT setval('jobs_id_seq', GREATEST((SELECT last_value FROM jobs_id_seq), COALESCE((SELECT max(id) FROM jobs), 0)))")->execute();
            return $job;
        } catch (Throwable $e) {
            $this->markTestSkipped('gc2scheduler not reachable: ' . $e->getMessage());
        }
    }

    /** @return array<string, mixed> the jobs row with that name */
    private function rowByName(Job $job, string $name): array
    {
        $res = $job->prepare("SELECT * FROM jobs WHERE name = :name");
        $job->execute($res, ['name' => $name]);
        $row = $job->fetchRow($res);
        $this->assertNotEmpty($row, "no jobs row named $name");
        return $row;
    }

    private function payload(string $name, array $extra = []): object
    {
        return (object)array_merge([
            'name' => $name,
            'schema' => 'public',
            'url' => 'http://example.invalid/x.zip',
            'cron' => '',
            'epsg' => '4326',
            'type' => 'zip',
            'min' => '0',
            'hour' => '*',
            'dayofmonth' => '*',
            'month' => '*',
            'dayofweek' => '*',
            'encoding' => 'UTF8',
            'extra' => null,
            'delete_append' => false,
            'download_schema' => false,
            'presql' => null,
            'postsql' => null,
            'active' => true,
        ], $extra);
    }

    public function testBooleansBindWithoutSnapshotPropertyAndRoundTrip(): void
    {
        $job = $this->job();
        $uniq = uniqid();
        $withoutFlag = 'snapflag_' . $uniq;
        $withFlag = 'snapflagon_' . $uniq;
        $inactive = 'snapflagoff_' . $uniq;
        $created = [];
        try {
            // No `snapshot` property at all: used to bind false -> '' -> 22P02.
            $job->newJob($this->payload($withoutFlag), self::DB);
            $row = $this->rowByName($job, $withoutFlag);
            $created[] = $row['id'];
            $this->assertFalse($row['snapshot'], 'omitted snapshot defaults to false');
            $this->assertFalse($row['delete_append'], 'false booleans are stored as false, not rejected');
            $this->assertFalse($row['download_schema']);
            $this->assertTrue($row['active']);

            // Explicit snapshot => true.
            $job->newJob($this->payload($withFlag, ['snapshot' => true]), self::DB);
            $row = $this->rowByName($job, $withFlag);
            $created[] = $row['id'];
            $this->assertTrue($row['snapshot']);

            // active => false is the same binding bug on another column.
            $job->newJob($this->payload($inactive, ['active' => false]), self::DB);
            $row = $this->rowByName($job, $inactive);
            $created[] = $row['id'];
            $this->assertFalse($row['active']);
            $this->assertFalse($row['snapshot']);

            // updateJob turns the flag on for the first job.
            $first = $this->rowByName($job, $withoutFlag);
            $job->updateJob($this->payload($withoutFlag, ['id' => $first['id'], 'snapshot' => true]));
            $this->assertTrue($this->rowByName($job, $withoutFlag)['snapshot']);
            $this->assertFalse($this->rowByName($job, $withoutFlag)['delete_append']);
        } finally {
            foreach ($created as $id) {
                $job->deleteJob((object)['id' => $id]);
            }
        }
    }

    /**
     * snapshot_formats: the per-job format list the v4 API writes and
     * buildGetCmd() passes on to get.php. Validation lives in
     * Job::toColumns(), so it covers createJob(), patchJob() and the
     * validateFields() pre-check of a POST list alike.
     */
    public function testSnapshotFormatsValidationAndRoundTrip(): void
    {
        $job = $this->job();
        $uniq = uniqid();
        $fields = fn(string $name, array $extra = []) => array_merge([
            'name' => $name, 'schema' => 'public', 'url' => 'http://example.invalid/x.zip',
            'schedule' => '0 3 * * *', 'snapshot' => true,
        ], $extra);
        $created = [];
        try {
            // null (and an absent property) stores NULL: use the server default.
            $id = $job->createJob($fields('snapfmtnull_' . $uniq), self::DB);
            $created[] = $id;
            $this->assertNull($job->getById($id, self::DB)['snapshot_formats'], 'absent means NULL');
            $job->patchJob($id, self::DB, ['snapshot_formats' => ['flatgeobuf']]);
            $this->assertSame(['flatgeobuf'], json_decode($job->getById($id, self::DB)['snapshot_formats'], true));
            $job->patchJob($id, self::DB, ['snapshot_formats' => null]);
            $this->assertNull($job->getById($id, self::DB)['snapshot_formats'], 'null resets to the server default');

            // A list round-trips in the requested order.
            $id = $job->createJob($fields('snapfmtlist_' . $uniq, ['snapshot_formats' => ['parquet', 'flatgeobuf']]), self::DB);
            $created[] = $id;
            $this->assertSame(['parquet', 'flatgeobuf'], json_decode($job->getById($id, self::DB)['snapshot_formats'], true));

            foreach ([
                         'unknown id' => ['geojson'],
                         'duplicates' => ['parquet', 'parquet'],
                         'empty list' => [],
                         'not a list' => ['0' => 'parquet', 'x' => 'flatgeobuf'],
                         'not a string' => [1],
                         'not an array' => 'parquet',
                     ] as $why => $bad) {
                try {
                    $job->validateFields($fields('snapfmtbad_' . $uniq, ['snapshot_formats' => $bad]));
                    $this->fail("$why should be refused");
                } catch (GC2Exception $e) {
                    $this->assertSame(400, $e->getCode(), $why);
                    $this->assertSame('INVALID_REQUEST', $e->getErrorCode(), $why);
                }
            }
            // The message names the offending id and the known ones.
            try {
                $job->validateFields($fields('x', ['snapshot_formats' => ['geojson']]));
            } catch (GC2Exception $e) {
                $this->assertStringContainsString('geojson', $e->getMessage());
                $this->assertStringContainsString('parquet', $e->getMessage());
            }
        } finally {
            foreach ($created as $id) {
                $job->deleteJobById($id, self::DB);
            }
        }
    }

    /**
     * The scheduler UI posts unchecked checkboxes as the string "false" and
     * checked ones as "on", so the flags arrive as strings, not JSON booleans.
     * "false" must stay false (a plain truthiness test would store true) and
     * "on" must become true, on create and on update alike.
     */
    public function testStringFlagsFromTheSchedulerUiRoundTrip(): void
    {
        $job = $this->job();
        $uniq = uniqid();
        $off = 'snapflagstroff_' . $uniq;
        $on = 'snapflagstron_' . $uniq;
        $created = [];
        try {
            // Unchecked in the ExtJS form: uncheckedValue => 'false'.
            $job->newJob($this->payload($off, ['active' => 'false', 'snapshot' => 'false', 'delete_append' => 'false', 'download_schema' => 'false']), self::DB);
            $row = $this->rowByName($job, $off);
            $created[] = $row['id'];
            $this->assertFalse($row['active'], 'the string "false" is not truthy here');
            $this->assertFalse($row['snapshot']);
            $this->assertFalse($row['delete_append']);
            $this->assertFalse($row['download_schema']);

            // Checked: the browser submits "on".
            $job->newJob($this->payload($on, ['active' => 'on', 'snapshot' => 'on']), self::DB);
            $row = $this->rowByName($job, $on);
            $created[] = $row['id'];
            $this->assertTrue($row['active']);
            $this->assertTrue($row['snapshot']);

            // updateJob parses the same strings.
            $offId = $this->rowByName($job, $off)['id'];
            $job->updateJob($this->payload($off, ['id' => $offId, 'active' => 'on', 'snapshot' => 'on']));
            $row = $this->rowByName($job, $off);
            $this->assertTrue($row['active']);
            $this->assertTrue($row['snapshot']);

            $job->updateJob($this->payload($off, ['id' => $offId, 'active' => 'false', 'snapshot' => 'false']));
            $row = $this->rowByName($job, $off);
            $this->assertFalse($row['active'], 'unchecking Active in the UI must store false');
            $this->assertFalse($row['snapshot']);
        } finally {
            foreach ($created as $id) {
                $job->deleteJob((object)['id' => $id]);
            }
        }
    }
}
