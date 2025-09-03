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
class Route
{

    public function __construct(private string $route)
    {
    }

    public function getRoute(): string
    {
        return $this->route;
    }
}