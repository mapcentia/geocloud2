<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\models\Job;
use Codeception\Test\Unit;

/**
 * Regression test for the scheduler's boolean columns (delete_append,
 * download_schema, active, snapshot).
 *
 * PDOStatement::execute(array) binds every value as PARAM_STR, so a raw PHP
 * `false` reaches Postgres as the empty string and a BOOL column rejects it
 * with 22P02 "invalid input syntax for type boolean". That made *every*
 * create/update fail whenever a boolean was false or the (optional) snapshot
 * property was omitted. Job::newJob/updateJob therefore bind 0/1.
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
}
