<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\auth\api\controllers;

use app\api\v4\Controller;
use app\api\v4\Scope;
use GuzzleHttp\Client;

#[Controller(route: 'google/(start)|(callback)', scope: Scope::PUBLIC)]
class Google extends AbstractSocialLogin
{
    protected function providerKey(): string
    {
        return 'google';
    }

    protected function providerLabel(): string
    {
        return 'Google';
    }

    protected function authorizeUrl(string $state, string $callback, array $cfg): string
    {
        $params = [
            'client_id' => $cfg['clientId'],
            'redirect_uri' => $callback,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    protected function exchangeCodeForToken(Client $client, string $code, string $callback, array $cfg): ?string
    {
        $res = $client->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id' => $cfg['clientId'],
                'client_secret' => $cfg['clientSecret'],
                'code' => $code,
                'redirect_uri' => $callback,
                'grant_type' => 'authorization_code',
            ]
        ]);
        $body = json_decode((string)$res->getBody(), true);
        return $body['access_token'] ?? null;
    }

    protected function fetchVerifiedEmail(Client $client, string $accessToken): ?string
    {
        $res = $client->get('https://openidconnect.googleapis.com/v1/userinfo', [
            'headers' => ['Authorization' => "Bearer $accessToken"],
        ]);
        $info = json_decode((string)$res->getBody(), true);
        if (!is_array($info) || empty($info['email'])) {
            return null;
        }
        // Only accept a Google-verified email
        if (array_key_exists('email_verified', $info) && !filter_var($info['email_verified'], FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }
        return $info['email'];
    }
}
