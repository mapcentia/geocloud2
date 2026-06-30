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

#[Controller(route: 'gitlab/(start)|(callback)', scope: Scope::PUBLIC)]
class Gitlab extends AbstractSocialLogin
{
    protected function providerKey(): string
    {
        return 'gitlab';
    }

    protected function providerLabel(): string
    {
        return 'GitLab';
    }

    /**
     * Base URL of the GitLab instance. Defaults to gitlab.com, but can be
     * overridden in config for self-hosted instances.
     */
    private function baseUrl(array $cfg): string
    {
        return rtrim($cfg['baseUrl'] ?? 'https://gitlab.com', '/');
    }

    protected function authorizeUrl(string $state, string $callback, array $cfg): string
    {
        $params = [
            'client_id' => $cfg['clientId'],
            'redirect_uri' => $callback,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ];
        return $this->baseUrl($cfg) . '/oauth/authorize?' . http_build_query($params);
    }

    protected function exchangeCodeForToken(Client $client, string $code, string $callback, array $cfg): ?string
    {
        $res = $client->post($this->baseUrl($cfg) . '/oauth/token', [
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

    protected function fetchVerifiedEmail(Client $client, string $accessToken, array $cfg): ?string
    {
        $res = $client->get($this->baseUrl($cfg) . '/oauth/userinfo', [
            'headers' => ['Authorization' => "Bearer $accessToken"],
        ]);
        $info = json_decode((string)$res->getBody(), true);
        if (!is_array($info) || empty($info['email'])) {
            return null;
        }
        // Only accept a GitLab-verified email
        if (array_key_exists('email_verified', $info) && !filter_var($info['email_verified'], FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }
        return $info['email'];
    }
}
