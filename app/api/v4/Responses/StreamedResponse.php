<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\api\v4\Responses;

use Closure;

final class StreamedResponse extends Response
{
    /**
     * @param array<string,string> $headers extra response headers (name => value), emitted before the callback runs
     */
    public function __construct(
        public readonly string  $contentType,
        public readonly Closure $callback,
        int                     $status = 200,
        public readonly array   $headers = [],
    ) {
        parent::__construct(status: $status, data: null);
    }
}
