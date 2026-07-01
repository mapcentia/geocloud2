<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

/**
 * Result of a single function invocation returned by a FunctionRunner.
 *
 * This is the contract between the PHP control plane and whatever data-plane
 * runner actually executes the function (e.g. a gVisor-backed runner). The
 * control plane only ever sees this value object - it never touches the
 * sandbox directly.
 */
readonly class InvocationResult
{
    /**
     * @param string $status One of 'succeeded' or 'failed'.
     * @param mixed $output The value returned by the handler (JSON-serialisable).
     * @param string|null $logs Captured stdout/stderr from the invocation.
     * @param string|null $error Error message when $status is 'failed'.
     * @param int|null $durationMs Wall-clock execution time in milliseconds.
     */
    public function __construct(
        public string  $status,
        public mixed   $output = null,
        public ?string $logs = null,
        public ?string $error = null,
        public ?int    $durationMs = null,
    )
    {
    }
}
