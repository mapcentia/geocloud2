<?php

namespace app\api\v4\Responses;

final class NoContentResponse extends Response
{
    public function __construct()
    {
        parent::__construct(204, null);
    }
}