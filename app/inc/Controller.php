<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\api\v2\Sql;
use app\conf\App;
use app\conf\Connection;
use app\models\Database;
use app\models\Layer;
use app\models\Setting;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

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

    /**
     * Controller constructor.
     */
    function __construct()
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
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function auth(?string $key = null, array $level = ["all" => true], bool $neverAllowSubUser = false): array
    {
        $response = [];
        $prop = $_SESSION['usergroup'] ?: $_SESSION['screen_name'];
        if (($_SESSION["subuser"] && $prop == Connection::$param['postgisschema']) && !$neverAllowSubUser) {
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
     * @throws PhpfastcacheInvalidArgumentException
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
        $settings = new Setting();
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
     * @param string|null $subUser
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function basicHttpAuthLayer(string $layer, string $db, string $subUser = null): void
    {
        $key = "http_auth_" . $layer . "_" . ($subUser ?: $db);
        if (!$_SESSION[$key]) {
            Database::setDb($db);
            $postgisObject = new Model();
            $auth = $postgisObject->getGeometryColumns($layer, "authentication");
            $layerSplit = explode(".", $layer);
            $HTTP_FORM_VARS["TYPENAME"] = $layerSplit[1];
            if ($auth == "Read/write" || !empty(Input::getAuthUser())) {
                include(__DIR__ . '/http_basic_authen.php');
            }
            $_SESSION[$key] = true;
        }
    }

    /**
     * @param string $layer
     * @param bool $transaction
     * @param array<string> $rels
     * @param string|null $subUser
     * @param string|null $inputApiKey
     * @return array|null
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function ApiKeyAuthLayer(string $layer, bool $transaction, array $rels, ?string $subUser = null, ?string $inputApiKey = null): ?array
    {
        // Check if layer has schema prefix and add 'public' if no.
        $bits = explode(".", $layer);
        if (sizeof($bits) == 1) {
            $schema = "public";
            $unQualifiedName = $layer;
            $layer = $schema . "." . $layer;
        } else {
            $schema = $bits[0];
            $unQualifiedName = $bits[1];
        }

        $postgisObject = new Model();
        $settings = new Setting();
        $response = $settings->get();
        $userGroup = $response["data"]->userGroups->$subUser ?? null;
        if ($subUser) {
            $apiKey = $response['data']->api_key_subuser->$subUser;
        } else {
            $apiKey = $response['data']->api_key;
        }
        $isKeyCorrect = $apiKey == $inputApiKey && $apiKey != false;

        $check = false;
        if (!empty($_SESSION["auth"])) {
            if ($subUser && $subUser == $_SESSION["screen_name"] && $_SESSION["parentdb"] == Connection::$param["postgisdb"]) {
                $check = true;
            } elseif (!$subUser && $_SESSION["screen_name"] == Connection::$param["postgisdb"]) {
                $check = true;
            }
        }

        $isAuth = $isKeyCorrect || $check;
        $session = !empty($_SESSION["subuser"]) ? $_SESSION["screen_name"] . '@' . $_SESSION["parentdb"] : $_SESSION["screen_name"] ?? null;

        $response = [];
        $response['is_auth'] = $isAuth;
        $auth = $postgisObject->getGeometryColumns($layer, "authentication");
        if ($auth == "Read/write" || $auth == "Write" || empty($auth)) {
            $rows = $postgisObject->getColumns($schema, $unQualifiedName);
            foreach ($rows as $row) {
                // Check if we got the right layer from the database
                if (!$row["f_table_schema"] == $schema || !$row["f_table_name"] == $unQualifiedName) {
                    continue;
                }
                if ($subUser) {
                    $privileges = (array)json_decode($row["privileges"]);
                    $response['auth_level'] = $auth;
                    if ($isAuth) {
                        $response['privileges'] = $privileges[$userGroup] ?? $privileges[$subUser];
                        $response['session'] = $session;
                        $response[Sql::USEDRELSKEY] = $rels;
                        switch ($transaction) {
                            case false:
                                if ((empty($privileges[$userGroup ?: $subUser]) || (!empty($privileges[$userGroup ?: $subUser]) && $privileges[$userGroup ?: $subUser] == "none")) && ($subUser != $schema && $userGroup != $schema)) {
                                    // Always let suusers read from layers open to all
                                    if ($auth == "Write") {
                                        $response['success'] = true;
                                        $response['code'] = 200;
                                        break;
                                    }
                                    $response['success'] = false;
                                    $response['message'] = "You don't have privileges to see '$layer'. Please contact the database owner, which can grant you privileges.";
                                    $response['code'] = 403;
                                } else {
                                    $response['success'] = true;
                                    $response['code'] = 200;
                                }
                                break;
                            default:
                                if ((!$privileges[$userGroup ?: $subUser] || $privileges[$userGroup ?: $subUser] == "none" || $privileges[$userGroup ?: $subUser] == "read") && ($subUser != $schema && $userGroup != $schema)) {
                                    $response['success'] = false;
                                    $response['message'] = "You don't have privileges to edit '$layer'. Please contact the database owner, which can grant you privileges.";
                                    $response['code'] = 403;
                                } else {
                                    $response['success'] = true;
                                    $response['code'] = 200;
                                }
                                break;
                        }
                    } else {
                        $response[Sql::USEDRELSKEY] = $rels;
                        $response['privileges'] = $privileges[$userGroup] ?? $privileges[$subUser];
                        $response['session'] = $session;

                        if ($auth == "Read/write" || ($transaction)) {
                            $response['success'] = false;
                            $response['message'] = "Not the right key!";
                            $response['code'] = 403;
                        } else {
                            $response['success'] = true;
                            $response['code'] = 200;
                        }
                    }
                } else {
                    $response['auth_level'] = $auth;
                    $response[Sql::USEDRELSKEY] = $rels;
                    $response['session'] = $session;

                    if ($auth == "Read/write" || ($transaction)) {
                        if ($isAuth) {
                            $response['success'] = true;
                            $response['code'] = 200;
                        } else {
                            $response['success'] = false;
                            $response['message'] = "Not the right key!";
                            $response['code'] = 403;
                        }
                    } else {
                        $response['success'] = true;
                        $response['code'] = 200;
                    }
                }
                return $response;
            }
        } else {
            $response3['success'] = true;
            $response3['session'] = $session;
            $response3['auth_level'] = $auth;
            $response3['is_auth'] = $isAuth;
            $response3[Sql::USEDRELSKEY] = $rels;
            return $response3;
        }
        return null;
    }
}


