<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;


/**
 * Class GetPart
 * @package app\inc
 */
class GetPart
{
    /**
     * @var array<string>
     */
    private $parts;

    /**
     * GetPart constructor.
     * @param array<string> $request
     */
    function __construct(array $request)
    {
        $this->parts = $request;
    }

    /**
     * @param int $e
     * @return string|null
     */
    function part(int $e): ?string
    {
        return isset($this->parts[$e]) ? urldecode($this->parts[$e]) : null;
    }

    /**
     * @return array<string>
     */
    function parts(): array
    {
        return $this->parts;
    }
}
