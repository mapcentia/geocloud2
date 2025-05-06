<?php

namespace app\api\v4;

interface ApiInterface
{
    public function get_index(): array;
    public function post_index(): array;
    public function put_index(): array;
    public function patch_index(): array;
    public function delete_index(): array;
    public function validate(): void;
}