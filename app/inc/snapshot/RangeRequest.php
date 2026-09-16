<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

/**
 * One satisfiable byte range of a file of known size (RFC 9110 §14).
 * Inclusive bounds, 0-based.
 */
final readonly class RangeRequest
{
    public function __construct(public int $start, public int $end)
    {
    }

    /**
     * Parses a Range header against the file size.
     *
     * Returns null when the header is absent, uses another unit, names more
     * than one range, or is malformed: the server may then ignore Range and
     * answer 200 with the whole file. Throws RangeNotSatisfiable (416) for a
     * well-formed single range no byte of the file can satisfy.
     *
     * The unit is matched case-insensitively: RFC 9110 range units are
     * case-insensitive tokens, and a client sending "BYTES=0-3" must get the
     * range, not the whole file.
     */
    public static function parse(?string $header, int $size): ?self
    {
        if ($header === null || !preg_match('/^\s*bytes\s*=\s*(\d*)\s*-\s*(\d*)\s*$/i', $header, $m)) {
            return null;
        }
        [, $a, $b] = $m;
        if ($a === '' && $b === '') {
            throw new RangeNotSatisfiable($size);
        }
        if ($size <= 0) {
            throw new RangeNotSatisfiable($size);
        }
        if ($a === '') {
            // suffix range: last $b bytes
            $n = (int)$b;
            if ($n <= 0) {
                throw new RangeNotSatisfiable($size);
            }
            $n = min($n, $size);
            return new self($size - $n, $size - 1);
        }
        $start = (int)$a;
        if ($start >= $size) {
            throw new RangeNotSatisfiable($size);
        }
        $end = $b === '' ? $size - 1 : min((int)$b, $size - 1);
        if ($start > $end) {
            throw new RangeNotSatisfiable($size);
        }
        return new self($start, $end);
    }

    public function length(): int
    {
        return $this->end - $this->start + 1;
    }

    public function contentRange(int $size): string
    {
        return "bytes {$this->start}-{$this->end}/$size";
    }
}
