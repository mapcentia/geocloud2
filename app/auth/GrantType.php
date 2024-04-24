<?php

namespace app\auth;
enum GrantType: string
{
    case PASSWORD = 'password';
    case  AUTHORIZATION_CODE = 'authorization_code';
    case REFRESH_TOKEN = 'refresh_token';
}
