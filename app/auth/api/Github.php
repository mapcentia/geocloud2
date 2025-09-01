<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\auth\api;

use app\api\v4\AbstractApi;
use app\conf\App;
use app\inc\Connection;
use app\inc\Session;
use app\inc\Session as HttpSession;
use app\models\User as UserModel;
use Exception;
use GuzzleHttp\Client;

class Github extends AbstractApi
{
    public function __construct()
    {
        parent::__construct(connection: new Connection());
        HttpSession::start();
    }

    public function get_index(): array { return []; }
    public function post_index(): array { return []; }
    public function put_index(): array { return []; }
    public function delete_index(): array { return []; }
    public function patch_index(): array { return []; }
    public function validate(): void { /* no-op */ }

    /**
     * Initiates the GitHub OAuth flow
     * Keeps original OAuth client params to return to /auth after login
     */
    public function get_start(): array
    {
        $cfg = App::$param['github'] ?? null;
        if (!$cfg || empty($cfg['clientId']) || empty($cfg['clientSecret'])) {
            echo 'GitHub OAuth is not configured on the server';
            return [];
        }

        // Preserve original params to return to /auth after login
        $returnQuery = $_SERVER['QUERY_STRING'] ?? '';
        $_SESSION['github_return_query'] = $returnQuery;
        $_SESSION['github_parent_db'] = $_GET['parentdb'] ?? null;

        // CSRF protection state
        $state = bin2hex(random_bytes(16));
        $_SESSION['github_oauth_state'] = $state;

        $callback = rtrim(App::$param['host'], '/') . '/github/callback';
        $params = [
            'client_id' => $cfg['clientId'],
            'redirect_uri' => $callback,
            'scope' => 'read:user user:email',
            'state' => $state,
        ];
        $authUrl = 'https://github.com/login/oauth/authorize?' . http_build_query($params);
        header("Location: $authUrl");
        return ['code' => 302];
    }

    /**
     * GitHub callback: exchange code -> token, fetch user + email, ensure local user, set session, redirect to /auth
     */
    public function get_callback(): array
    {
        $cfg = App::$param['github'] ?? null;
        if (!$cfg || empty($cfg['clientId']) || empty($cfg['clientSecret'])) {
            echo 'GitHub OAuth is not configured on the server';
            return [];
        }
        $expectedState = $_SESSION['github_oauth_state'] ?? null;
        if (!$expectedState || !isset($_GET['state']) || !hash_equals($expectedState, (string)$_GET['state'])) {
            echo 'Invalid OAuth state';
            return [];
        }
        if (empty($_GET['code'])) {
            echo 'Missing authorization code';
            return [];
        }

        $client = new Client(['timeout' => 10, 'headers' => ['Accept' => 'application/json', 'User-Agent' => 'gc2-app']]);

        try {
            // 1) Exchange code for access token
            $callback = rtrim(App::$param['host'], '/') . '/github/callback';
            $res = $client->post('https://github.com/login/oauth/access_token', [
                'form_params' => [
                    'client_id' => $cfg['clientId'],
                    'client_secret' => $cfg['clientSecret'],
                    'code' => $_GET['code'],
                    'redirect_uri' => $callback,
                    'state' => $_GET['state'],
                ]
            ]);
            $tokenBody = json_decode((string)$res->getBody(), true);
            if (empty($tokenBody['access_token'])) {
                echo 'Could not obtain access token from GitHub';
                return [];
            }
            $accessToken = $tokenBody['access_token'];

            // 2) Fetch user info
            $authHeaders = ['Authorization' => "Bearer $accessToken", 'User-Agent' => 'gc2-app'];
            $userRes = $client->get('https://api.github.com/user', ['headers' => $authHeaders]);
            $user = json_decode((string)$userRes->getBody(), true);

            // 3) Fetch verified primary email (GitHub may not return email in /user)
            $email = null;
            if (!empty($user['email'])) {
                $email = $user['email'];
            } else {
                $emailsRes = $client->get('https://api.github.com/user/emails', ['headers' => $authHeaders]);
                $emails = json_decode((string)$emailsRes->getBody(), true);
                if (is_array($emails)) {
                    foreach ($emails as $e) {
                        if (!empty($e['primary']) && !empty($e['verified']) && !empty($e['email'])) {
                            $email = $e['email'];
                            break;
                        }
                    }
                    if (!$email && count($emails) > 0) {
                        $email = $emails[0]['email'] ?? null;
                    }
                }
            }
            if (!$email) {
                echo 'Could not determine your email from GitHub account';
                return [];
            }

            // 4) Determine parentdb from preserved query
            $returnQuery = $_SESSION['github_return_query'] ?? '';
            parse_str($returnQuery, $retParams);
            $parentdb = $_SESSION['github_parent_db'] ?? null;

            // 5) Ensure local user exists (in specific parentdb if provided, else top-level)
            $userModel = new UserModel(parentDb: $parentdb);
            $row = $this->findUserByEmail($userModel, $email, $parentdb);
            if (!$row) {
                // Create user as subuser when parentdb specified, else top-level user
                // Generate a strong password that includes at least:
                // - one uppercase letter
                // - one lowercase letter
                // - one digit
                // - one symbol
                $targetLen = 16;
                $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $lower = 'abcdefghijklmnopqrstuvwxyz';
                $digits = '0123456789';
                $symbols = '!@#$%^&*()-_=+[]{}<>?';
                $all = $upper . $lower . $digits . $symbols;

                $passwordChars = [];
                $passwordChars[] = $upper[random_int(0, strlen($upper) - 1)];
                $passwordChars[] = $lower[random_int(0, strlen($lower) - 1)];
                $passwordChars[] = $digits[random_int(0, strlen($digits) - 1)];
                $passwordChars[] = $symbols[random_int(0, strlen($symbols) - 1)];

                for ($i = count($passwordChars); $i < $targetLen; $i++) {
                    $passwordChars[] = $all[random_int(0, strlen($all) - 1)];
                }
                // Fisher–Yates shuffle using crypto-safe random_int
                for ($i = count($passwordChars) - 1; $i > 0; $i--) {
                    $j = random_int(0, $i);
                    [$passwordChars[$i], $passwordChars[$j]] = [$passwordChars[$j], $passwordChars[$i]];
                }
                $password = implode('', $passwordChars);

                $data = [
                    // Use GitHub login as GC2 username
                    'name' => $user['login'] ?? $email,
                    'email' => $email,
                    'password' => $password,
                ];
                if ($parentdb) {
                    // Create as subuser within the specified parent database
                    $data['parentdb'] = $parentdb;
                } else {
                    // Create as top-level (superuser) with its own database
                    $data['subuser'] = false;
                }
                $userModel->createUser($data);
                $row = $this->findUserByEmail($userModel, $email, $parentdb);
            }

            if (!$row) {
                echo 'Failed to create or find user for GitHub login';
                return [];
            }

            // 6) Set session vars (mirrors app\models\Session::setSessionVars)
            $_SESSION['zone'] = $row['zone'];
            $_SESSION['auth'] = true;
            $_SESSION['screen_name'] = $row['screenname'];
            $_SESSION['parentdb'] = $row['parentdb'] ?: $row['screenname'];
            $_SESSION['subuser'] = (bool)$row['parentdb'];
            $_SESSION['properties'] = !empty($row['properties']) ? json_decode($row['properties']) : null;
            $_SESSION['email'] = $row['email'];
            $_SESSION['usergroup'] = $row['usergroup'] ?: null;
            $_SESSION['created'] = strtotime($row['created']);
            $_SESSION['postgisschema'] = 'public';

            // 7) Redirect back to /auth with original query
            $redirect = '/auth' . ($returnQuery ? ('?' . $returnQuery) : '');
            header('Location: ' . $redirect);
            return ['code' => 302];
        } catch (Exception $e) {
            echo 'GitHub login failed: ' . htmlspecialchars($e->getMessage());
            return [];
        }
    }

    private function findUserByEmail(UserModel $userModel, string $email, ?string $parentDb): ?array
    {
        if ($parentDb) {
            $sql = 'SELECT * FROM users WHERE email = :email AND parentdb = :parentdb LIMIT 1';
            $stmt = $userModel->prepare($sql);
            $userModel->execute($stmt,[':email' => $email, ':parentdb' => $parentDb]);
        } else {
            $sql = 'SELECT * FROM users WHERE email = :email AND parentdb IS NULL LIMIT 1';
            $stmt = $userModel->prepare($sql);
            $userModel->execute($stmt,[':email' => $email]);
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
