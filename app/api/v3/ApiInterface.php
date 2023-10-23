<?php

namespace app\api\v3;
interface ApiInterface
{
    public function get_index(): array;
    public function post_index(): array;
    public function put_index(): array;
    public function delete_index(): array;

}