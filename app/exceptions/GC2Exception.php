<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\exceptions;

use Exception;
use Throwable;

class GC2Exception extends Exception
{
    protected string|null $errorCode;

    public function __construct($message, $code = 0, ?Throwable $previous = null, ?string $errorCode = null) {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}