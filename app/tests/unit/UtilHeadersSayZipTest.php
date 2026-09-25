<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Util;
use Codeception\Test\Unit;

/**
 * Util::headersSayZip() — what app/scripts/get.php asks of a job URL's response
 * headers before deciding to unpack the download (getCmdZip).
 *
 * The header lines are the raw "Name: value" strings get_headers() returns, and
 * real servers spell the type in more ways than one. GC2's own SQL API answers
 * `Content-type: application/zip, application/octet-stream` for an ogr/… format,
 * which an exact comparison against "Content-Type: application/zip" misses — the
 * bug this function replaces.
 */
class UtilHeadersSayZipTest extends Unit
{
    protected UnitTester $tester;

    public function testRecognisesTheWaysServersSpellAZip(): void
    {
        $this->assertTrue(Util::headersSayZip(['Content-Type: application/zip']));
        // GC2's SQL API: two types in one header, and a lower case header name
        $this->assertTrue(Util::headersSayZip(['Content-type: application/zip, application/octet-stream']),
            'GC2 format=ogr/... answers with a list of types');
        $this->assertTrue(Util::headersSayZip(['content-type: application/zip;charset=UTF-8']));
        $this->assertTrue(Util::headersSayZip(['CONTENT-TYPE: APPLICATION/ZIP']));
        $this->assertTrue(Util::headersSayZip(['Content-Type: application/x-zip-compressed']));
        // Only the filename gives it away
        $this->assertTrue(Util::headersSayZip([
            'Content-Type: application/octet-stream',
            'Content-Disposition: attachment; filename="_1ad0e77.flatgeobuf.zip"',
        ]), 'a zip behind application/octet-stream is named by Content-Disposition');
    }

    public function testDoesNotSeeAZipInAnythingElse(): void
    {
        $this->assertFalse(Util::headersSayZip([]));
        $this->assertFalse(Util::headersSayZip(['HTTP/1.1 200 OK', 'Content-Type: application/json; charset=utf-8']));
        $this->assertFalse(Util::headersSayZip(['Content-Type: text/csv']));
        $this->assertFalse(Util::headersSayZip(['Content-Type: text/html; charset=UTF-8']));
        $this->assertFalse(Util::headersSayZip(['Content-Type: application/gml+xml']));
        // A zip named in some other header is not the response's own type
        $this->assertFalse(Util::headersSayZip(['X-Source-File: something.zip', 'Content-Type: text/html']),
            'only Content-Type and Content-Disposition decide');
        // The real headers of the GeoFA WFS GetFeature the scheduler pages
        $this->assertFalse(Util::headersSayZip([
            'HTTP/1.1 200 OK',
            'Content-Type: text/xml; subtype=gml/3.2.1',
            'Transfer-Encoding: chunked',
        ]));
    }
}
