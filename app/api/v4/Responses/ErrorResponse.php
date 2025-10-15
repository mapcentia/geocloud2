<?php


namespace app\api\v4\Responses;

final class ErrorResponse extends Response
{
    public function __construct(protected array|string|null $data, int $status = 400)
    {
        parent::__construct($status, $data);
    }
}