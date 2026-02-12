<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\Responses;

final class PatchResponse extends Response
{
    public function __construct(?array $data, ?string $location = null)
    {
        parent::__construct(303, $data, $location);
    }
}