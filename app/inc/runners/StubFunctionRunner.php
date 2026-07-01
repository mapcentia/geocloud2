<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc\runners;

use app\exceptions\GC2Exception;
use app\inc\FunctionRunner;
use app\inc\InvocationResult;

/**
 * Default runner used when no execution backend is configured.
 *
 * It locks the invocation contract (HTTP surface, invocation records, status
 * endpoint) without requiring the gVisor data plane to exist yet. Every
 * invocation fails with 501 so the behaviour is honest: the surface is live,
 * the engine is not.
 */
class StubFunctionRunner implements FunctionRunner
{
    /**
     * @throws GC2Exception Always - the runtime is not available.
     */
    public function invoke(array $function, array $event, array $context): InvocationResult
    {
        throw new GC2Exception(
            "The function runtime is not configured on this instance. Set the 'functionRunner' key in conf/App.php to a runner implementation.",
            501,
            null,
            "FUNCTION_RUNTIME_UNAVAILABLE"
        );
    }
}
