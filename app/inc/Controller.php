<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;
use app\exceptions\GC2Exception;
use app\exceptions\ServiceException;
use app\models\Authorization;
use app\models\Layer;
use app\models\Setting;
use app\models\User;
use Psr\Cache\InvalidArgumentException;
use Throwable;

/**
 * Class Controller
 * @package app\inc
 */
class Controller
{
    public array $response;

    const string USED_RELS_KEY = "checked_relations";

    function __construct(public Connection $connection = new Connection())
    {
        $this->response = [];
    }

    /**
     * Implement OPTIONS method for all action controllers.
     */
    public function options_index(): void
    {
        //Pass
    }

    /**
     * Implement HEAD method for all action controllers.
     */
    public function head_index(): void
    {
        //Pass
    }

    /**
     * Determines if the current user has superuser privileges based on session data.
     *
     * @return array Returns an array containing the success status, message, and code.
     *               If the user lacks superuser privileges, the array includes an error message and a 403 status code.
     *               Otherwise, it indicates success.
     */
    public function isSuperUser(): array
    {
        if ($_SESSION["subuser"]) {
            $response['success'] = false;
            $response['message'] = "You don't have privileges to do this.";
            $response['code'] = 403;
        } else {
            $response['success'] = true;
        }
        return $response;
    }

    /**
     * Authenticates a user or subuser based on a provided relation key, privilege levels, and other permissions.
     *
     * @param string $key The key used to retrieve privileges for authentication.
     * @param array $level An associative array defining the required privilege levels. Defaults to ["all" => true].
     * @param bool $neverAllowSubUser A flag indicating whether subusers should always be denied privileges. Defaults to false.
     * @return array An associative array containing the success status, optional message, and HTTP status code.
     * @throws InvalidArgumentException|GC2Exception
     */
    public function auth(string $key, array $level = ["all" => true], bool $neverAllowSubUser = false): array
    {
        $response = [];
        $privileges = json_decode(new Layer(connection: $this->connection)->getValueFromKey($key, "privileges"), true);
        $authorization = new Authorization(connection: $this->connection);
        $privilege = $authorization->extractHighestPrivilege(
            privileges: $privileges ?? [],
            subUser: $_SESSION["screen_name"],
            groups: $_SESSION["usergroup"],
        );
        $isOwner = $authorization->isOwner(
            subUser: $_SESSION["screen_name"],
            groups: $_SESSION["usergroup"],
            schema: $this->connection->schema
        );
        if (($_SESSION["subuser"] && $isOwner) && !$neverAllowSubUser) {
            $response['success'] = true;
        } elseif ($_SESSION["subuser"]) {
            $text = "You don't have privileges to do this. Please contact the database owner, who can grant you privileges.";
            if (sizeof($level) == 0) {
                $response['success'] = false;
                $response['message'] = $text;
                $response['code'] = 403;
            } else {
                if (!isset($level[$privilege])) {
                    $response['success'] = false;
                    $response['message'] = $text;
                    $response['code'] = 403;
                } else {
                    $response['success'] = true;
                }
            }
        } else {
            $response['success'] = true;
        }
        return $response;
    }

    /**
     * Determines if the current user is the owner of the resource or has the necessary privileges.
     *
     * @return array Returns an associative array containing the success status of the ownership check.
     *               If the user is not the owner, additional keys 'message' and 'code' are included
     *               to provide error information.
     */
    public function isOwner(): array
    {
        if (!$_SESSION["subuser"]) {
            return ['success' => true];
        }
        $isOwner = new Authorization(connection: $this->connection)->isOwner(
            subUser: $_SESSION["screen_name"],
            groups: $_SESSION["usergroup"],
            schema: $this->connection->schema
        );
        $response['success'] = $isOwner;
        if (!$isOwner) {
            $response['message'] = "You don't have privileges to do this.";
            $response['code'] = 403;
        }
        return $response;
    }

    /**
     * Authenticates an API key provided by a user against stored keys and trusted addresses.
     *
     * @param string $user The username or identifier associated with the API key.
     * @param string $key The API key to be authenticated.
     * @return bool Returns true if the API key is valid and associated with the trusted user, otherwise false.
     * @throws InvalidArgumentException
     */
    public function authApiKey(string $user, string $key): bool
    {
        foreach (App::$param["trustedAddresses"] as $address) {
            if (Util::ipInRange(Util::clientIp(), $address)) {
                return true;
            }
        }
        global $postgisdb;
        $postgisdb = $user;
        $settings = new Setting(connection: $this->connection);
        $res = $settings->get();
        $apiKey = $res['data']->api_key;
        if ($apiKey == $key && $key) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Provides a basic HTTP authentication layer to secure a specified layer.
     *
     * @param string $layer The name of the layer to which the authentication layer should be applied.
     * @return void
     * @throws InvalidArgumentException
     * @throws ServiceException
     * @throws Throwable
     */
    public function basicHttpAuthLayer(string $layer): void
    {
        $postgisObject = new Model(connection: $this->connection);
        $auth = $postgisObject->getGeometryColumns($layer, "authentication");
        if ($auth == "Read/write" || !empty(Input::getAuthUser())) {
            new BasicAuth(connection: $this->connection)->authenticate($layer, false);
        }
    }

    /**
     * Authenticates and authorizes access to a specified layer based on API key, session data, and user group inheritance.
     *
     * @param string $layer The name of the layer to be accessed.
     * @param bool $transaction Indicates whether the operation involves a transactional context.
     * @param array $rels An array of relationships relevant to the authorization process.
     * @param string|null $subUser Optional sub-user identifier for specific API key validation and session checks.
     * @param string|null $inputApiKey Optional input API key provided by the client for authentication.
     * @return array|null Returns an array containing authorization details, including authentication status and session data, or null if authorization fails.
     * @throws Throwable
     */
    public function ApiKeyAuthLayer(string $layer, bool $transaction, array $rels, ?string $subUser = null, ?string $inputApiKey = null): ?array
    {
        $response = new Setting(connection: $this->connection)->get();
        $userGroup = !empty($response["data"]->userGroups->$subUser) ? json_decode($response["data"]->userGroups->$subUser) : null;
        $userGroupFullChain = new User()->getFullInheritance($userGroup, $this->connection->database);
        if ($subUser) {
            $apiKey = $response['data']->api_key_subuser->$subUser;
        } else {
            $apiKey = $response['data']->api_key;
        }
        $isKeyCorrect = $apiKey == $inputApiKey && $apiKey != false;
        $check = false;
        if (!empty($_SESSION["auth"])) {
            if ($subUser && $subUser == $_SESSION["screen_name"] && $_SESSION["parentdb"] == $this->connection->database) {
                $check = true;
            } elseif (!$subUser && $_SESSION["screen_name"] == $this->connection->database) {
                $check = true;
            }
        }
        $isAuth = $isKeyCorrect || $check;
        $session = !empty($_SESSION["subuser"]) ? $_SESSION["screen_name"] . '@' . $_SESSION["parentdb"] : $_SESSION["screen_name"] ?? null;
        $response = new Authorization(connection: $this->connection)->check(relName: $layer, transaction: $transaction, isAuth: $isAuth, subUser: $subUser, userGroup: $userGroupFullChain, rels: $rels);
        $response['is_auth'] = $isAuth;
        $response['session'] = $session;
        return $response;
    }
}


