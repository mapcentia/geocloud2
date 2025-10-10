<?php


namespace app\api\v4\Responses;

final class GetResponse extends Response
{
    public function __construct(protected array|string|null $data)
    {
        parent::__construct(200, $data);
    }
}