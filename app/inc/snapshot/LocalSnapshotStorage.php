<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

/**
 * Snapshots on local disk under $root. Ranges are served with fseek; there is
 * no direct-download URL, so the API always proxies.
 */
final class LocalSnapshotStorage extends FlysystemSnapshotStorage
{
    public function __construct(private readonly string $root, string $prefix = '')
    {
        parent::__construct(new Filesystem(new LocalFilesystemAdapter($root)), $prefix);
    }

    public function readRange(SnapshotRef $ref, string $file, int $start, int $length)
    {
        $path = rtrim($this->root, '/') . '/' . $this->key($ref, $file);
        $h = @fopen($path, 'rb');
        if ($h === false) {
            throw new RuntimeException("Could not open $path");
        }
        if ($start > 0 && fseek($h, $start) !== 0) {
            fclose($h);
            throw new RuntimeException("Could not seek to $start in $path");
        }
        return $h;
    }

    public function downloadUrl(SnapshotRef $ref, string $file, int $ttlSeconds): ?string
    {
        return null;
    }

    public function locationOf(SnapshotRef $ref): string
    {
        return 'file://' . rtrim($this->root, '/') . '/' . $this->key($ref);
    }
}
