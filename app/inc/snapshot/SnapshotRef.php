<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

/**
 * Logical identity of one snapshot. Only the storage layer turns this into a
 * physical key or path.
 */
final readonly class SnapshotRef
{
    public function __construct(
        public string $database,
        public string $schema,
        public string $relation,
        public string $snapshotDate,
        public string $snapshotId,
    ) {
    }

    public function dataFile(): string
    {
        return "data-{$this->snapshotId}.parquet";
    }

    public function metadataFile(): string
    {
        return "metadata-{$this->snapshotId}.json";
    }
}
