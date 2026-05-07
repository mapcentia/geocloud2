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
}
