<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\exceptions\GC2Exception;

/**
 * Abstraction over the data plane that actually executes a function.
 *
 * The control plane (the Func controller/model) depends only on this
 * interface, so the execution backend can evolve independently:
 *  - StubFunctionRunner: returns 501 until a runtime is configured.
 *  - (later) a gVisor/runsc-backed runner that runs the code in a sandbox.
 *
 * Select the implementation via the "functionRunner" key in conf/App.php.
 */
interface FunctionRunner
{
    /**
     * Synchronously execute a function and return its result.
     *
     * @param array $function The function definition row from settings.functions.
     * @param array $event The invocation payload supplied by the caller.
     * @param array $context Execution context (caller identity, scoped token,
     *                       API base URL, function metadata) handed to the
     *                       sandbox so the handler can call back into GC2.
     * @return InvocationResult
     * @throws GC2Exception On unrecoverable runner/transport errors.
     */
    public function invoke(array $function, array $event, array $context): InvocationResult;
}
