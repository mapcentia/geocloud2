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
class AcceptableContentTypes
{
    public array $contentTypesAllowed;

    public function __construct(array $types)
    {
        $this->contentTypesAllowed = $types;
    }

    public function getAllowedContentTypes(): array
    {
        return $this->contentTypesAllowed;
    }

    /**
     * @throws GC2Exception
     */
    public function throwException(): never
    {
        throw new GC2Exception("Content-Type not acceptable", 406, null, "NOT_ACCEPTABLE");
    }

    public function options(): never
    {
        header("HTTP/1.0  204");
        exit();
    }
}