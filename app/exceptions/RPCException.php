<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
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
class RPCException extends Exception
{
    protected array $response;

    public function __construct($message, $code = 0, Throwable $previous = null, $data = null, $id = null) {
        $this->response = [
            "jsonrpc" => "2.0",
            "error" => [
                "code" => $code,
                "message" => $message,
                "data" => $data,
            ],
            "id" => $id,
        ];
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): array
    {
        return $this->response;
    }
}