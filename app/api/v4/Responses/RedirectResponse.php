<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\Responses;

final class RedirectResponse extends Response
{
    public function __construct(string $location)
    {
        parent::__construct(302, null, $location);
    }
}