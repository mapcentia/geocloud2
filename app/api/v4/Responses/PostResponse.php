<?php

namespace app\api\v4\Responses;

final class PostResponse extends Response
{
    public function __construct(?array $data, ?string $location = null)
    {
        parent::__construct(201, $data, $location);
    }
}