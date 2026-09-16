<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use RuntimeException;

/** A syntactically valid Range that no byte of the file can satisfy (HTTP 416). */
final class RangeNotSatisfiable extends RuntimeException
{
    public function __construct(public readonly int $size)
    {
        parent::__construct("Range not satisfiable for size $size");
    }
}
