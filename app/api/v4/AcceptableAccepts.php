<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use Attribute;

#[Attribute]
class AcceptableAccepts
{
    public array $acceptsAllowed;

    public function __construct(array $types)
    {
        $this->acceptsAllowed = $types;
    }

    public function getAllowedAccepts(): array
    {
        return $this->acceptsAllowed;
    }

    /**
     * @throws GC2Exception
     */
    public function throwException(array $accepts): never
    {
        throw new GC2Exception("Accept media type(s) " . implode(',' , $accepts) ." is not acceptable. Must be: " . implode(', ', $this->acceptsAllowed), 406, null, "NOT_ACCEPTABLE");
    }
}