<?php

namespace app\api\v4;

use app\api\v4\Responses\Response;

interface ApiInterface
{
    public function get_index():  Response;
    public function post_index(): Response;
    public function put_index(): Response;
    public function patch_index(): Response;
    public function delete_index(): Response;
    public function validate(): void;
}