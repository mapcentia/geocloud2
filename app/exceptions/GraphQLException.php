<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\exceptions;

use Exception;
use Throwable;

/**
 * RPCException is a custom exception class used for JSON-RPC error handling.
 *
 * This exception is designed to encapsulate error details, including the error code,
 * error message, optional additional data, and an optional identifier, in accordance
 * with the JSON-RPC 2.0 specification.
 */
class GraphQLException extends Exception
{
    protected array $response;

    public function __construct($message, $code = 0, ?Throwable $previous = null)
    {
        $this->response = [
            "errors" => [[
                "message" => $message,
            ]],
        ];
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): array
    {
        return $this->response;
    }
}