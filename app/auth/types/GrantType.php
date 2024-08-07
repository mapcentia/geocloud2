<?php

namespace app\auth\types;
enum GrantType: string
{
    case PASSWORD = 'password';
    case  AUTHORIZATION_CODE = 'authorization_code';
    case REFRESH_TOKEN = 'refresh_token';
    case DEVICE_CODE = 'device_code';
}
