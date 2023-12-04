<?php

namespace app\exceptions;

use Exception;

class GC2Exception extends Exception
{
    protected string|null $errorCode;
    public function __construct($message, $code = 0, Throwable $previous = null, string $errorCode = null) {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}