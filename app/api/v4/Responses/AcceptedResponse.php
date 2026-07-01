<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\Responses;

/**
 * 202 Accepted - the request was queued for asynchronous processing. Used for
 * async function invocations: the body carries the invocation id to poll.
 */
final class AcceptedResponse extends Response
{
    public function __construct(array|string|null $data)
    {
        parent::__construct(202, $data);
    }
}
