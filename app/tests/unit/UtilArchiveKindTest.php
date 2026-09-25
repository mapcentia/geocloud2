<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Util;
use Codeception\Test\Unit;

/**
 * Util::archiveKind() — what app/scripts/get.php getCmdZip() unpacks a download
 * with.
 *
 * It reads the file's own first bytes instead of the job URL's extension, which
 * the old code did: a URL like
 * …/api/v2/sql/fkg?format=ogr/flatgeobuf&q=select * from fkg.t_5610… ends in no
 * extension at all, and the condition that was meant to tell gzip from zip
 * (`!strtolower($ext) == "gz"`) was always false, so every download went down
 * the gzip path and a zip was copied byte for byte instead of being unpacked.
 */
class UtilArchiveKindTest extends Unit
{
    protected UnitTester $tester;

    private function tmp(string $bytes): string
    {
        $path = sys_get_temp_dir() . '/archivekind_' . uniqid();
        file_put_contents($path, $bytes);
        return $path;
    }

    public function testRecognisesAZip(): void
    {
        $path = sys_get_temp_dir() . '/archivekind_' . uniqid() . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('sql_statement.fgb', 'not really flatgeobuf');
        $zip->close();
        $this->assertSame('zip', Util::archiveKind($path));
        unlink($path);
    }

    public function testRecognisesGzip(): void
    {
        $path = $this->tmp(gzencode('some plain content'));
        $this->assertSame('gz', Util::archiveKind($path));
        unlink($path);
    }

    public function testEmptyZipIsStillAZip(): void
    {
        // An archive with no entries starts "PK\x05\x06", not "PK\x03\x04".
        $path = $this->tmp("PK\x05\x06" . str_repeat("\x00", 18));
        $this->assertSame('zip', Util::archiveKind($path));
        unlink($path);
    }

    public function testAnythingElseIsNeither(): void
    {
        foreach (['{"type":"FeatureCollection"}', "<?xml version=\"1.0\"?><wfs:FeatureCollection/>", 'a,b,c', ''] as $content) {
            $path = $this->tmp($content);
            $this->assertNull(Util::archiveKind($path), 'plain content is no archive: ' . substr($content, 0, 20));
            unlink($path);
        }
        $this->assertNull(Util::archiveKind('/no/such/file/at/all'));
    }
}
