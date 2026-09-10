<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\wfs\output;

use app\models\Table;
use app\wfs\Context;
use app\wfs\Request;

/**
 * What the GetFeature handler needs from an output writer. GmlWriter (WFS) and GeoJsonWriter
 * (OGC API Features) implement it; the handler builds and streams the rows the same way for both.
 */
interface FeatureWriterInterface
{
    public function writeXmlProlog(): void;

    public function writeFeatureCollectionOpen(Request $req, Context $ctx, ?int $numberMatched = null): void;

    public function writeFeatureCollectionClose(): void;

    public function writeFeatureMembersOpen(string $version): void;

    public function writeFeatureMembersClose(string $version): void;

    /**
     * @param 'open'|'close'|'selfclose' $type
     * @param array<string, string>|null $atts
     */
    public function writeTag(string $type, ?string $ns, string $tag, ?array $atts = null, bool $newline = true): void;

    public function write(string $s): void;

    /** @param array<string, mixed> $row one cursor row: columns as selected plus "fid" */
    public function writeFeature(array $row, string $table, Table $tableObj, Request $req, Context $ctx): void;

    public function flush(): void;

    /** False when the format has no envelope element (GeoJSON); the handler then skips the bounds query. */
    public function wantsBoundedBy(): bool;
}
