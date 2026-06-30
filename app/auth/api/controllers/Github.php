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

#[Controller(route: 'github/(start)|(callback)', scope: Scope::PUBLIC)]
class Github extends AbstractSocialLogin
{
    protected function providerKey(): string
    {
        return 'github';
    }

    protected function providerLabel(): string
    {
        return 'GitHub';
    }

    protected function authorizeUrl(string $state, string $callback, array $cfg): string
    {
        $params = [
            'client_id' => $cfg['clientId'],
            'redirect_uri' => $callback,
            'scope' => 'read:user user:email',
            'state' => $state,
        ];
        return 'https://github.com/login/oauth/authorize?' . http_build_query($params);
    }

    protected function exchangeCodeForToken(Client $client, string $code, string $callback, array $cfg): ?string
    {
        $res = $client->post('https://github.com/login/oauth/access_token', [
            'form_params' => [
                'client_id' => $cfg['clientId'],
                'client_secret' => $cfg['clientSecret'],
                'code' => $code,
                'redirect_uri' => $callback,
            ]
        ]);
        $body = json_decode((string)$res->getBody(), true);
        return $body['access_token'] ?? null;
    }

    protected function fetchVerifiedEmail(Client $client, string $accessToken, array $cfg): ?string
    {
        $authHeaders = ['Authorization' => "Bearer $accessToken", 'User-Agent' => 'gc2-app'];

        // GitHub may not return the email in /user, so fall back to /user/emails
        $userRes = $client->get('https://api.github.com/user', ['headers' => $authHeaders]);
        $user = json_decode((string)$userRes->getBody(), true);
        if (!empty($user['email'])) {
            return $user['email'];
        }

        $emailsRes = $client->get('https://api.github.com/user/emails', ['headers' => $authHeaders]);
        $emails = json_decode((string)$emailsRes->getBody(), true);
        if (is_array($emails)) {
            foreach ($emails as $e) {
                if (!empty($e['primary']) && !empty($e['verified']) && !empty($e['email'])) {
                    return $e['email'];
                }
            }
            if (count($emails) > 0) {
                return $emails[0]['email'] ?? null;
            }
        }
        return null;
    }
}
