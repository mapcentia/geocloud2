<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
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
    /**
     * @var array
     */
    public array $response;

    /**
     * @var string|null
     */
    protected ?string $sUser;

    const string USED_RELS_KEY = "checked_relations";

    /**
     * Controller constructor.
     */
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
     * @param string|null $key
     * @param array<bool> $level
     * @param bool $neverAllowSubUser
     * @return array
     */
    public function auth(?string $key = null, array $level = ["all" => true], bool $neverAllowSubUser = false): array
    {
        $response = [];
        $prop = $_SESSION['usergroup'] ?: $_SESSION['screen_name'];
        if (($_SESSION["subuser"] && $prop == $this->connection->schema) && !$neverAllowSubUser) {
            $response['success'] = true;
        } elseif ($_SESSION["subuser"]) {
            $text = "You don't have privileges to do this. Please contact the database owner, who can grant you privileges.";
            if (sizeof($level) == 0) {
                $response['success'] = false;
                $response['message'] = $text;
                $response['code'] = 403;
            } else {
                $layer = new Layer();
                $privileges = json_decode($layer->getValueFromKey($key, "privileges"));
                $subuserLevel = $privileges->$prop;
                if (!isset($level[$subuserLevel])) {
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
     * @param string $user
     * @param string $key
     * @return bool
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
     * @param string $layer
     * @param string $db
     * @throws ServiceException
     */
    public function basicHttpAuthLayer(string $layer, string $db): void
    {
        $postgisObject = new Model(connection: $this->connection);
        $auth = $postgisObject->getGeometryColumns($layer, "authentication");
        if ($auth == "Read/write" || !empty(Input::getAuthUser())) {
            new BasicAuth(connection: $this->connection)->authenticate($layer, false);
        }
    }

    /**
     * @param string $layer
     * @param bool $transaction
     * @param array<string> $rels
     * @param string|null $subUser
     * @param string|null $inputApiKey
     * @return array|null
     * @throws InvalidArgumentException
     * @throws GC2Exception
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


