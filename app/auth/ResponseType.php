<?php

namespace app\auth;
enum ResponseType: string
{
    case TOKEN = 'token';
    case CODE = 'code';
    case REFRESH = 'refresh';
}
