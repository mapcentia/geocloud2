<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\auth\api\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\Responses\Response;
use app\conf\App;
use app\inc\Connection;
use app\inc\Route2;
use app\inc\Session as HttpSession;
use app\models\Database;
use app\models\User as UserModel;
use Exception;
use GuzzleHttp\Client;
use PDO;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Base class for social/OAuth login providers (GitHub, Google, ...).
 *
 * The generic authorization-code flow (start -> provider -> callback ->
 * ensure local user -> set session -> redirect back to /auth) lives here.
 * Concrete providers only supply the endpoints, scopes and how to read a
 * verified email from the provider.
 */
abstract class AbstractSocialLogin extends AbstractApi
{
    public function __construct(protected readonly Route2 $route, Connection $connection, protected $twig = new Environment(new FilesystemLoader(__DIR__ . '/../templates')))
    {
        parent::__construct($connection);
        HttpSession::start();
    }

    /**
     * Key used both as the config key in App::$param and as the route/session
     * namespace, e.g. 'github' or 'google'.
     */
    abstract protected function providerKey(): string;

    /**
     * Human-readable provider name used in user-facing messages.
     */
    abstract protected function providerLabel(): string;

    /**
     * Build the provider's authorization URL to redirect the user to.
     */
    abstract protected function authorizeUrl(string $state, string $callback, array $cfg): string;

    /**
     * Exchange the authorization code for an access token. Returns null on failure.
     */
    abstract protected function exchangeCodeForToken(Client $client, string $code, string $callback, array $cfg): ?string;

    /**
     * Fetch a verified primary email for the authenticated provider user.
     * Returns null when no usable verified email can be determined.
     */
    abstract protected function fetchVerifiedEmail(Client $client, string $accessToken, array $cfg): ?string;

    /**
     * Initiates the OAuth flow.
     * Keeps the original OAuth client params so we can return to /auth after login.
     */
    public function get_start(): Response
    {
        $cfg = $this->config();
        if ($cfg === null) {
            echo htmlspecialchars($this->providerLabel() . ' OAuth is not configured on the server');
            return $this->emptyResponse();
        }
        $key = $this->providerKey();

        // Preserve original params to return to /auth after login
        $_SESSION[$key . '_return_query'] = $_SERVER['QUERY_STRING'] ?? '';
        $_SESSION[$key . '_parent_db'] = $_GET['parentdb'] ?? null;
        $_SESSION[$key . '_redirect_uri'] = $_GET['redirect_uri'] ?? null;
        $_SESSION[$key . '_action'] = $_GET['action'] ?? null;

        // CSRF protection state
        $state = bin2hex(random_bytes(16));
        $_SESSION[$key . '_oauth_state'] = $state;

        return $this->redirectResponse(location: $this->authorizeUrl($state, $this->callbackUrl(), $cfg));
    }

    /**
     * Provider callback: exchange code -> token, fetch verified email, ensure
     * local user, set session, redirect to /auth (or the original redirect_uri).
     */
    public function get_callback(): Response
    {
        $cfg = $this->config();
        if ($cfg === null) {
            echo htmlspecialchars($this->providerLabel() . ' OAuth is not configured on the server');
            return $this->emptyResponse();
        }
        $key = $this->providerKey();

        $expectedState = $_SESSION[$key . '_oauth_state'] ?? null;
        if (!$expectedState || !isset($_GET['state']) || !hash_equals($expectedState, (string)$_GET['state'])) {
            echo 'Invalid OAuth state';
            return $this->emptyResponse();
        }
        // State is single-use
        unset($_SESSION[$key . '_oauth_state']);

        if (empty($_GET['code'])) {
            echo 'Missing authorization code';
            return $this->emptyResponse();
        }

        $client = new Client(['timeout' => 10, 'headers' => ['Accept' => 'application/json', 'User-Agent' => 'gc2-app']]);

        try {
            $callback = $this->callbackUrl();
            $accessToken = $this->exchangeCodeForToken($client, $_GET['code'], $callback, $cfg);
            if (!$accessToken) {
                echo 'Could not obtain access token from ' . htmlspecialchars($this->providerLabel());
                return $this->emptyResponse();
            }

            $email = $this->fetchVerifiedEmail($client, $accessToken, $cfg);
            if (!$email) {
                echo 'Could not determine a verified email from your ' . htmlspecialchars($this->providerLabel()) . ' account';
                return $this->emptyResponse();
            }

            // Determine parentdb from the preserved start params
            $parentdb = $_SESSION[$key . '_parent_db'] ?? null;

            // Ensure local user exists (subuser when parentdb provided, else top-level)
            $userModel = new UserModel(parentDb: $parentdb);
            $row = $this->findUserByEmail($userModel, $email, $parentdb);
            if (!$row) {
                $this->createSocialUser($userModel, $email, $parentdb);
                $row = $this->findUserByEmail($userModel, $email, $parentdb);
            }
            if (!$row) {
                echo 'Failed to create or find user for ' . htmlspecialchars($this->providerLabel()) . ' login';
                return $this->emptyResponse();
            }

            $this->establishSession($row);

            // Redirect back to /auth with original query, or to the original redirect_uri
            $returnQuery = $_SESSION[$key . '_return_query'] ?? '';
            if (($_SESSION[$key . '_action'] ?? null) === 'signin') {
                $redirect = '/auth' . ($returnQuery ? ('?' . $returnQuery) : '');
            } else {
                $redirect = $_SESSION[$key . '_redirect_uri'] ?? '/auth';
            }
            return $this->redirectResponse(location: $redirect);
        } catch (Exception $e) {
            echo htmlspecialchars($this->providerLabel()) . ' login failed: ' . htmlspecialchars($e->getMessage());
            return $this->emptyResponse();
        }
    }

    protected function config(): ?array
    {
        $cfg = App::$param[$this->providerKey()] ?? null;
        if (!$cfg || empty($cfg['clientId']) || empty($cfg['clientSecret'])) {
            return null;
        }
        return $cfg;
    }

    protected function callbackUrl(): string
    {
        return rtrim(App::$param['host'], '/') . '/' . $this->providerKey() . '/callback';
    }

    private function findUserByEmail(UserModel $userModel, string $email, ?string $parentDb): ?array
    {
        if ($parentDb) {
            $sql = 'SELECT * FROM users WHERE email = :email AND parentdb = :parentdb LIMIT 1';
            $stmt = $userModel->prepare($sql);
            $userModel->execute($stmt, [':email' => $email, ':parentdb' => $parentDb]);
        } else {
            $sql = 'SELECT * FROM users WHERE email = :email AND parentdb IS NULL LIMIT 1';
            $stmt = $userModel->prepare($sql);
            $userModel->execute($stmt, [':email' => $email]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function createSocialUser(UserModel $userModel, string $email, ?string $parentdb): void
    {
        $data = [
            // Use the provider account email as the GC2 username
            'name' => $email,
            'email' => $email,
            'password' => $this->generateStrongPassword(),
        ];
        if ($parentdb) {
            // Create as subuser within the specified parent database
            $data['parentdb'] = $parentdb;
            $data['subuser'] = true;
        } else {
            // Create as top-level (superuser) with its own database
            $data['subuser'] = false;
        }
        $userRes = $userModel->createUser($data);
        if (!$parentdb) {
            (new Database())->changeOwner(db: $userRes['data']['screenname'], newOwner: $userRes['data']['screenname']);
        }
    }

    /**
     * Generate a strong random password containing at least one upper, lower,
     * digit and symbol, using a crypto-safe RNG.
     */
    private function generateStrongPassword(int $targetLen = 16): string
    {
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $digits = '0123456789';
        $symbols = '!@#$%^&*()-_=+[]{}<>?';
        $all = $upper . $lower . $digits . $symbols;

        $chars = [];
        $chars[] = $upper[random_int(0, strlen($upper) - 1)];
        $chars[] = $lower[random_int(0, strlen($lower) - 1)];
        $chars[] = $digits[random_int(0, strlen($digits) - 1)];
        $chars[] = $symbols[random_int(0, strlen($symbols) - 1)];
        for ($i = count($chars); $i < $targetLen; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        // Fisher–Yates shuffle using crypto-safe random_int
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }

    /**
     * Set session vars (mirrors app\models\Session::setSessionVars).
     */
    private function establishSession(array $row): void
    {
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
    }

    // --- Unused REST verbs for these routes -------------------------------

    public function get_index(): Response
    {
        return $this->emptyResponse();
    }

    public function post_index(): Response
    {
        return $this->emptyResponse();
    }

    public function put_index(): Response
    {
        return $this->emptyResponse();
    }

    public function delete_index(): Response
    {
        return $this->emptyResponse();
    }

    public function patch_index(): Response
    {
        return $this->emptyResponse();
    }

    public function validate(): void
    { /* no-op */
    }
}
