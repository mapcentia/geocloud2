<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

/**
 * Physical storage of snapshot files. Callers address files by SnapshotRef +
 * file name and never see buckets, keys or paths.
 */
interface SnapshotStorage
{
    public function exists(SnapshotRef $ref, string $file): bool;

    public function size(SnapshotRef $ref, string $file): int;

    /** @return list<array{name:string, size_bytes:int}> files in the snapshot directory, by name */
    public function listFiles(SnapshotRef $ref): array;

    /** @return resource read stream of the whole file */
    public function readStream(SnapshotRef $ref, string $file);

    /**
     * @return resource read stream positioned at byte $start (0-based). It holds
     *     at least the requested bytes when they exist and may hold more (local
     *     files); callers copy at most $length bytes.
     */
    public function readRange(SnapshotRef $ref, string $file, int $start, int $length);

    /** @param resource $stream */
    public function writeStream(SnapshotRef $ref, string $file, $stream): void;

    public function write(SnapshotRef $ref, string $file, string $contents): void;

    /** Deletes one file; a missing file is not an error. */
    public function delete(SnapshotRef $ref, string $file): void;

    /** Short-lived URL a client can fetch directly, or null when the backend cannot issue one. */
    public function downloadUrl(SnapshotRef $ref, string $file, int $ttlSeconds): ?string;

    /** Display location of the snapshot directory, e.g. s3://bucket/prefix/.../ or file:///root/.../ */
    public function locationOf(SnapshotRef $ref): string;
}
