<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Owns all XML output for the WFS server. Two modes:
 *   - streaming (default): write() goes to php://output + flush() per call
 *   - buffered: write() accumulates in-memory until bufferFlush()
 * Transaction handler uses buffered; GetFeature uses streaming.
 */
namespace app\wfs\output;

final class GmlWriter
{
    private bool $buffering = false;
    private string $buffer = '';

    public function __construct(
        public readonly string  $gmlNameSpace,
        public readonly string  $gmlNameSpaceUri,
        public readonly ?string $gmlNameSpaceGeom = null,
        /** @var array<string, string> $gmlFeature */
        public readonly array   $gmlFeature = [],
        /** @var array<string, string> $gmlGeomFieldName */
        public readonly array   $gmlGeomFieldName = [],
        /** @var array<string, bool> $gmlUseAltFunctions */
        public readonly array   $gmlUseAltFunctions = [],
    ) {}

    public function bufferStart(): void
    {
        $this->buffering = true;
        $this->buffer = '';
    }

    public function bufferFlush(): void
    {
        $out = $this->buffer;
        $this->buffer = '';
        $this->buffering = false;
        echo $out;
    }

    /** Discards any pending buffered content (used before exception reports). */
    public function bufferDiscard(): void
    {
        $this->buffer = '';
        $this->buffering = false;
    }

    public function flush(): void
    {
        if ($this->buffering) return;
        flush();
        if (ob_get_level() > 0) {
            ob_flush();
        }
    }

    public function write(string $s): void
    {
        if ($this->buffering) {
            $this->buffer .= $s;
            return;
        }
        echo $s;
    }

    /**
     * @param 'open'|'close'|'selfclose' $type
     * @param array<string, string>|null $atts
     */
    public function writeTag(string $type, ?string $ns, string $tag, ?array $atts = null, bool $newline = true): void
    {
        $name = $ns !== null ? "$ns:$tag" : $tag;
        $s = '<';
        if ($type === 'close') $s .= '/';
        $s .= $name;
        if (!empty($atts)) {
            foreach ($atts as $k => $v) {
                $s .= ' ' . $k . '="' . $v . '"';
            }
        }
        if ($type === 'selfclose') $s .= '/';
        $s .= '>';
        if ($newline) $s .= "\n";
        $this->write($s);
    }

    public function writeXmlProlog(): void
    {
        $this->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
    }

    public function writeMemoryFooter(): void
    {
        $this->write("\n<!-- Memory used: " . round(memory_get_peak_usage() / 1024) . " KB -->\n");
        $this->write(str_pad('', 4096));
        $this->flush();
    }

    public function writeFeatureCollectionOpen(\app\wfs\Request $req, \app\wfs\Context $ctx, ?int $numberMatched = null): void
    {
        $ns  = $this->gmlNameSpace;
        $uri = $this->gmlNameSpaceUri;
        $tn  = implode(',', $req->typeNames ?? []);
        $countAttr = $numberMatched !== null
            ? ' numberOfFeatures="' . $numberMatched . '" timeStamp="' . date('Y-m-d\TH:i:s.v\Z') . '"'
            : '';
        $this->write(
            '<wfs:FeatureCollection '
            . 'xmlns:xs="http://www.w3.org/2001/XMLSchema" '
            . 'xmlns:wfs="http://www.opengis.net/wfs" '
            . "xmlns:{$ns}=\"{$uri}\" "
            . 'xmlns:gml="http://www.opengis.net/gml" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . $countAttr . ' '
            . "xsi:schemaLocation=\"{$uri} {$ctx->thePath}?service=wfs&amp;version={$req->version}&amp;request=DescribeFeatureType&amp;typeName={$tn} "
            . 'http://www.opengis.net/wfs http://schemas.opengis.net/wfs/' . $req->version . '/' . ($req->version === '1.1.0' ? 'wfs' : 'WFS-basic') . '.xsd">'
        );
    }

    public function writeFeatureCollectionClose(): void
    {
        $this->write('</wfs:FeatureCollection>');
    }

    public function writeFeatureMembersOpen(string $version): void
    {
        if ($version === '1.1.0') {
            $this->writeTag('open', 'gml', 'featureMembers');
        }
    }

    public function writeFeatureMembersClose(string $version): void
    {
        if ($version === '1.1.0') {
            $this->writeTag('close', 'gml', 'featureMembers');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public function writeFeature(array $row, string $table, \app\models\Table $tableObj, \app\wfs\Request $req, \app\wfs\Context $ctx): void
    {
        $ns          = $this->gmlNameSpace;
        $featureName = $this->gmlFeature[$table] ?? $table;

        if ($req->version !== '1.1.0') {
            $this->writeTag('open', 'gml', 'featureMember');
        }
        $idAttr = $req->version === '1.1.0'
            ? ['gml:id' => "{$table}.{$row['fid']}"]
            : ['fid'    => "{$table}.{$row['fid']}"];
        $this->writeTag('open', $ns, $featureName, $idAttr);

        foreach ($row as $field => $value) {
            if ($field === 'fid' || $field === 'FID' || $field === 'oid'
                || in_array($field, ['txmin', 'tymin', 'txmax', 'tymax'], true)
            ) {
                continue;
            }
            $info = $tableObj->metaData[$field] ?? null;
            if ($info === null) {
                continue;
            }
            if (($info['type'] ?? '') === 'geometry') {
                if ($value === null || $value === '') {
                    continue;
                }
                $geomNs       = $this->gmlNameSpaceGeom ?? $ns;
                $geomFieldName = $this->gmlGeomFieldName[$table] ?? $field;
                $this->writeTag('open', $geomNs, $geomFieldName);
                $this->write((string) $value);
                $this->writeTag('close', $geomNs, $geomFieldName);
                continue;
            }
            if ($value === null) {
                continue;
            }
            if (in_array($info['type'] ?? '', ['string', 'text', 'json', 'jsonb'], true) && $value !== '') {
                $value = '<![CDATA[' . str_replace('&', '&#38;', (string) $value) . ']]>';
            }
            $this->writeTag('open', $ns, $field, null, false);
            $this->write($value === false ? '0' : (string) $value);
            $this->writeTag('close', $ns, $field);
        }

        $this->writeTag('close', $ns, $featureName);
        if ($req->version !== '1.1.0') {
            $this->writeTag('close', 'gml', 'featureMember');
        }
        $this->flush();
    }
}
