<?php

namespace app\api\v4\Responses;

final class PatchResponse extends Response
{
    public function __construct(?array $data, ?string $location = null)
    {
        parent::__construct(303, $data, $location);
    }
}