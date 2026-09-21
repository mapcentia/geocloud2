<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;
use InvalidArgumentException;
use League\Flysystem\UnableToDeleteFile;

/**
 * Everything a Flysystem adapter can do for a snapshot, plus the key layout.
 * Backends add readRange and downloadUrl, which Flysystem does not offer.
 */
abstract class FlysystemSnapshotStorage implements SnapshotStorage
{
    public function __construct(
        protected readonly Filesystem $filesystem,
        protected readonly string     $prefix,
    ) {
    }

    /**
     * "{prefix}/{database}/schema={schema}/relation={relation}/_gc2_snapshot_date={date}/{file}"
     * (prefix omitted when empty; ends with '/' when $file is empty). The
     * segment is prefixed with _gc2_ so Hive-style readers never confuse it
     * with a data column.
     */
    public function key(SnapshotRef $ref, string $file = ''): string
    {
        // Defence in depth: every segment is interpolated straight into the
        // object key / local path, so a '/', '\\' or '..' in any of them would
        // escape the snapshot's directory (locally, into the filesystem). The
        // controller's regex and the catalog already rule this out; a backend
        // must not depend on a caller upstream getting it right.
        foreach (['database' => $ref->database, 'schema' => $ref->schema, 'relation' => $ref->relation, 'snapshotDate' => $ref->snapshotDate, 'file' => $file] as $name => $segment) {
            $this->assertSafeSegment($name, $segment);
        }
        return $this->databaseRoot($ref->database)
            . "schema={$ref->schema}/relation={$ref->relation}/_gc2_snapshot_date={$ref->snapshotDate}/$file";
    }

    /**
     * "{prefix}/{database}/{relativePath}": the key of a file that belongs to
     * the database as a whole rather than to one snapshot — the STAC catalog
     * documents, which sit next to (and above) the snapshot directories.
     * Unlike key(), $relativePath may contain '/' (it spans directories), but
     * it must stay inside the database root.
     */
    public function keyAt(string $database, string $relativePath): string
    {
        $this->assertSafeSegment('database', $database);
        if ($relativePath === '' || str_contains($relativePath, '\\') || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new InvalidArgumentException("Snapshot path '$relativePath' must be relative and must not contain '\\' or '..'");
        }
        return $this->databaseRoot($database) . $relativePath;
    }

    public function writeAt(string $database, string $relativePath, string $contents): void
    {
        $this->filesystem->write($this->keyAt($database, $relativePath), $contents);
    }

    /** "{prefix}/{database}/", prefix omitted when empty. */
    private function databaseRoot(string $database): string
    {
        $prefix = trim($this->prefix, '/');
        return ($prefix !== '' ? $prefix . '/' : '') . "$database/";
    }

    private function assertSafeSegment(string $name, string $segment): void
    {
        if (str_contains($segment, '/') || str_contains($segment, '\\') || str_contains($segment, '..')) {
            throw new InvalidArgumentException("Snapshot key segment '$name' must not contain '/', '\\' or '..'");
        }
    }

    public function exists(SnapshotRef $ref, string $file): bool
    {
        return $this->filesystem->fileExists($this->key($ref, $file));
    }

    public function size(SnapshotRef $ref, string $file): int
    {
        return $this->filesystem->fileSize($this->key($ref, $file));
    }

    public function listFiles(SnapshotRef $ref): array
    {
        $files = [];
        foreach ($this->filesystem->listContents($this->key($ref), false) as $item) {
            /** @var StorageAttributes $item */
            if (!$item->isFile()) {
                continue;
            }
            $files[] = ['name' => basename($item->path()), 'size_bytes' => (int)$this->filesystem->fileSize($item->path())];
        }
        usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $files;
    }

    public function readStream(SnapshotRef $ref, string $file)
    {
        return $this->filesystem->readStream($this->key($ref, $file));
    }

    public function writeStream(SnapshotRef $ref, string $file, $stream): void
    {
        $this->filesystem->writeStream($this->key($ref, $file), $stream);
    }

    public function write(SnapshotRef $ref, string $file, string $contents): void
    {
        $this->filesystem->write($this->key($ref, $file), $contents);
    }

    public function delete(SnapshotRef $ref, string $file): void
    {
        try {
            $this->filesystem->delete($this->key($ref, $file));
        } catch (UnableToDeleteFile) {
            // Missing files are not an error: delete is used for best-effort cleanup.
        }
    }
}
