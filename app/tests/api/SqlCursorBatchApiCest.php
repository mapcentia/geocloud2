<?php

use Codeception\Util\HttpCode;

/**
 * Every row the query selected must come back, in every format.
 *
 * app/models/Sql.php streams its result through a server-side cursor and used to
 * pull one row per round trip (`FETCH 1 FROM curs`), which costs the database
 * roughly 7-11 us of CPU per row in pure fetch overhead. The batch size is now
 * configurable (`SqlApiSettings.fetchSize`), and the loops read a whole batch per
 * round trip.
 *
 * That turns a correct loop into a lossy one if it is done carelessly: the old
 * idiom re-executed the FETCH on every iteration and read a single row from it,
 * so with a batch of n it would advance the cursor n rows and keep one, silently
 * returning every nth row. This suite is the guard against that. It is the
 * reason the row count, not just the shape of the response, is asserted.
 *
 * ROW_COUNT is deliberately not a multiple of any plausible batch size, so a
 * partial final batch is always exercised. If fetchSize is configured above
 * ROW_COUNT the suite still passes but proves less, since everything then
 * arrives in one batch.
 *
 * The excel branch shares its loop with csv, so csv covers both.
 */
class SqlCursorBatchApiCest
{
    private const ROW_COUNT = 2501;

    private DateTime $date;
    private string $password = 'A1abcabcabc';
    private string $userId;
    private string $cookie;

    /** Two scalars and a geometry, so the geojson branch has a geometry to lift out. */
    private string $query = "SELECT i AS n, 'r' || i AS t,
            ST_SetSRID(ST_Point(10.0 + i / 100000.0, 56.0), 4326) AS the_geom
        FROM generate_series(1, 2501) i ORDER BY i";

    public function __construct()
    {
        $this->date = new DateTime();
    }

    public function shouldPrepare(ApiTester $I)
    {
        $ts = $this->date->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'sqlbatch_' . $ts, 'email' => 'sqlbatch' . $ts . '@example.com', 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->cookie = $I->capturePHPSESSID();
    }

    public function shouldReturnEveryRowAsJson(ApiTester $I)
    {
        $data = json_decode($this->post($I, 'json'), true);
        $I->assertSame(true, $data['success']);
        $I->assertCount(self::ROW_COUNT, $data['data'], 'the json branch dropped rows');
        // The batch boundary is where rows go missing, so check the sequence itself,
        // not only how many came back.
        $I->assertSame(1, $data['data'][0]['n']);
        $I->assertSame(self::ROW_COUNT, $data['data'][self::ROW_COUNT - 1]['n']);
        $I->assertSame(range(1, self::ROW_COUNT), array_column($data['data'], 'n'),
            'every row must arrive exactly once and in order');
    }

    public function shouldReturnEveryRowAsGeojson(ApiTester $I)
    {
        $data = json_decode($this->post($I, 'geojson'), true);
        $I->assertSame('FeatureCollection', $data['type']);
        $I->assertCount(self::ROW_COUNT, $data['features'], 'the geojson branch dropped rows');
        $I->assertSame(range(1, self::ROW_COUNT), array_column(array_column($data['features'], 'properties'), 'n'));
        $I->assertSame('Point', $data['features'][0]['geometry']['type'],
            'the geometry must still be lifted out of the row');
    }

    public function shouldReturnEveryRowAsNdjson(ApiTester $I)
    {
        $body = $this->post($I, 'ndjson');
        // The branch pads each flushed chunk with str_pad(..., 4096), so lines carry
        // trailing spaces; count the features rather than the raw lines.
        $I->assertSame(self::ROW_COUNT, substr_count($body, '"type":"Feature"'),
            'the ndjson branch dropped rows');
        $I->assertStringContainsString('"n":1,', $body);
        $I->assertStringContainsString('"n":' . self::ROW_COUNT . ',', $body);
    }

    public function shouldReturnEveryRowAsCcsv(ApiTester $I)
    {
        $this->assertCsvBody($I, $this->post($I, 'ccsv'), 'ccsv');
    }

    public function shouldReturnEveryRowAsCsv(ApiTester $I)
    {
        $this->assertCsvBody($I, $this->post($I, 'csv'), 'csv');
    }

    /** One header line plus one line per row, and the first and last row are the right ones. */
    private function assertCsvBody(ApiTester $I, string $body, string $format): void
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $body)), fn($l) => $l !== ''));
        $I->assertSame(self::ROW_COUNT + 1, count($lines), "the $format branch dropped rows");
        $I->assertStringStartsWith('n;t', ltrim($lines[0], "\xEF\xBB\xBF"), 'first line is the header');
        $I->assertStringStartsWith('1;"r1"', $lines[1]);
        $I->assertStringStartsWith(self::ROW_COUNT . ';"r' . self::ROW_COUNT . '"', $lines[self::ROW_COUNT]);
    }

    /**
     * An empty result must end the batch loop at once rather than spin on it.
     */
    public function shouldHandleAnEmptyResult(ApiTester $I)
    {
        $data = json_decode($this->postQuery($I, 'json',
            "SELECT i AS n FROM generate_series(1, 0) i"), true);
        $I->assertSame(true, $data['success']);
        $I->assertSame([], $data['data']);
    }

    /**
     * Exactly one full batch, which is where an off-by-one in the loop shows:
     * the batch is drained, the next FETCH returns nothing and must terminate.
     * 1000 is the default fetchSize; with a different one configured this is
     * simply another multi-batch case and still has to come back whole.
     */
    public function shouldHandleExactlyOneFullBatch(ApiTester $I)
    {
        $data = json_decode($this->postQuery($I, 'json',
            "SELECT i AS n FROM generate_series(1, 1000) i ORDER BY i"), true);
        $I->assertCount(1000, $data['data'], 'a result of exactly one batch lost rows');
        $I->assertSame(range(1, 1000), array_column($data['data'], 'n'));
    }

    private function post(ApiTester $I, string $format): string
    {
        return $this->postQuery($I, $format, $this->query);
    }

    private function postQuery(ApiTester $I, string $format, string $q): string
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $this->cookie);
        $I->sendPOST('/api/v2/sql/' . $this->userId, json_encode(['q' => $q, 'format' => $format]));
        $I->seeResponseCodeIs(HttpCode::OK);
        return $I->grabResponse();
    }
}
