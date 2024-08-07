<?php

namespace app\auth\types;
enum ResponseType: string
{
    case TOKEN = 'token';
    case CODE = 'code';
    case REFRESH = 'refresh';
}
