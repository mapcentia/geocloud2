<?php

namespace app\api\v4\Responses;

abstract class Response
{
    public function __construct(protected int $status, protected ?array $data, protected ?string $location = null)
    {
        if ($this->location) {
            header('Location: ' . $this->location);
        }
    }
    public function getData(): ?array
    {
        return $this->data;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}