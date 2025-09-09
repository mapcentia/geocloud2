<?php

namespace app\api\v4\Responses;

final class RedirectResponse extends Response
{
    public function __construct(string $location)
    {
        parent::__construct(302, null, $location);
    }
}