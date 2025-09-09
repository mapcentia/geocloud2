<?php

namespace app\api\v4\Responses;

final class EmptyResponse extends Response
{
    public function __construct()
    {
        parent::__construct(200, null);
    }
}