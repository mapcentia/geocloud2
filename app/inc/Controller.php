<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;

/**
 * Class Controller
 * @package app\inc
 */
class Controller
{
    /**
     * @var array
     */
    public $response;

    /**
     * @var null
     */
    protected $sUser;

    /**
     * Controller constructor.
     */
    function __construct()
    {
        // Set sub-user property from the "user" url parameter.
        // This is just a helper for action controllers
        // so less boilerplate is needed
        // ====================================================
        if ($db = Route::getParam("user")) {
            $dbSplit = explode("@", $db);
            if (sizeof($dbSplit) == 2) {
                $this->sUser = $dbSplit[0];
            } elseif (isset($_SESSION["subuser"])) {
                $this->sUser = !empty($_SESSION["screen_name"]) ? $_SESSION["screen_name"] : null;
            } else {
                $this->sUser = null;
            }
        }
    }

    /**
     * Implement OPTIONS method for all action controllers.
     */
    public function options_index()
    {
        //Pass
    }

    /**
     * Implement HEAD method for all action controllers.
     */
    public function head_index()
    {
        //Pass
    }

    /**
     * @param string|null $key
     * @param array $level
     * @param bool $neverAllowSubUser
     * @return array
     */
    public function auth(string $key = null, array $level = ["all" => true], bool $neverAllowSubUser = false): array
    {
        $response = [];
        if (($_SESSION["subuser"] == true && $_SESSION['screen_name'] == \app\conf\Connection::$param['postgisschema']) && $neverAllowSubUser == false) {
            $response['success'] = true;
        } elseif ($_SESSION["subuser"]) {
            $text = "You don't have privileges to do this. Please contact the database owner, who can grant you privileges.";
            if (sizeof($level) == 0) {
                $response['success'] = false;
                $response['message'] = $text;
                $response['code'] = 403;
            } else {
                $layer = new \app\models\Layer();
                $privileges = json_decode($layer->getValueFromKey($key, "privileges"));
                $prop = $_SESSION['usergroup'] ?: $_SESSION['screen_name'];
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
        $settings_viewer = new \app\models\Setting();
        $res = $settings_viewer->get();
        $apiKey = $res['data']->api_key;
        if ($apiKey == $key && $key != false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $layer
     * @param string $db
     * @param string|null $subUser
     */
    public function basicHttpAuthLayer(string $layer, string $db, string $subUser = null)
    {
        $key = "http_auth_" . $layer . "_" . ($subUser ?: $db);
        if (!$_SESSION[$key]) {
            \app\inc\Log::write("Checking auth");
            \app\models\Database::setDb($db);
            $postgisObject = new \app\inc\Model();
            $auth = $postgisObject->getGeometryColumns($layer, "authentication");
            $layerSplit = explode(".", $layer);
            $postgisschema = $layerSplit[0];
            $HTTP_FORM_VARS["TYPENAME"] = $layerSplit[1];
            if ($auth == "Read/write") {
                include('inc/http_basic_authen.php');
            }
            $_SESSION[$key] = true;
        }
    }

    /**
     * @param string $layer
     * @param string|null $subUser
     * @param bool $transaction
     * @param string $inputApiKey
     * @param array $rels
     * @return array
     */
    public function ApiKeyAuthLayer(string $layer, string $subUser = null, bool $transaction, string $inputApiKey = null, array $rels): array
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

        $postgisObject = new \app\inc\Model();
        $auth = $postgisObject->getGeometryColumns($layer, "authentication");
        if ($auth == "Read/write" || $auth == "Write") {
            $settings_viewer = new \app\models\Setting();
            $response = $settings_viewer->get();
            if (isset($response["data"]->userGroups->$subUser)) {
                $userGroup = $response["data"]->userGroups->$subUser;
            } else {
                $userGroup = null;
            }
            if ($subUser) {
                $apiKey = $response['data']->api_key_subuser->$subUser;
            } else {
                $apiKey = $response['data']->api_key;
            }
            $sql = "SELECT * FROM settings.getColumns('f_table_schema = ''{$schema}'' AND f_table_name = ''{$unQualifiedName}''','raster_columns.r_table_schema = ''{$schema}'' AND raster_columns.r_table_name = ''{$unQualifiedName}''')";
            $res = $postgisObject->prepare($sql);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                $response = array();
                $response2['success'] = false;
                $response2['message'] = $e->getMessage();
                $response2['code'] = 401;
                die(Response::toJson($response));
            }
            while ($row = $postgisObject->fetchRow($res, "assoc")) {
                // Check if we got the right layer from the database
                if (!$row["f_table_schema"] == $schema || !$row["f_table_name"] == $unQualifiedName) {
                    continue;
                }
                if ($subUser) {
                    $privileges = (array)json_decode($row["privileges"]);
                    if (($apiKey == $inputApiKey && $apiKey != false) || !empty($_SESSION["auth"])) {
                        $response = array();
                        $response['auth_level'] = $auth;
                        $response['privileges'] = !empty($privileges[$subUser]) ? $privileges[$subUser] : null;
                        $response[\app\api\v1\Sql::USEDRELSKEY] = $rels;
                        switch ($transaction) {
                            case false:
//                              if (($privileges[$userGroup ?: $subUser] == false || $privileges[$userGroup ?: $subUser] == "none") && $subUser != $schema) {
                                if ((empty($privileges[$userGroup ?: $subUser]) || (!empty($privileges[$userGroup ?: $subUser]) && $privileges[$userGroup ?: $subUser] == "none")) && $subUser != $schema) {
                                    // Always let suusers read from layers open to all
                                    if ($auth == "None" || $auth == "Write") {
                                        $response['success'] = true;
                                        $response['code'] = 200;
                                        break;
                                    }
                                    $response['success'] = false;
                                    $response['message'] = "You don't have privileges to see '{$layer}'. Please contact the database owner, which can grant you privileges.";
                                    $response['code'] = 403;
                                } else {
                                    $response['success'] = true;
                                    $response['code'] = 200;
                                }
                                break;
                            case true:
                                if (($privileges[$userGroup ?: $subUser] == false || $privileges[$userGroup ?: $subUser] == "none" || $privileges[$userGroup ?: $subUser] == "read") && $subUser != $schema) {


                                    $response['success'] = false;
                                    $response['message'] = "You don't have privileges to edit '{$layer}'. Please contact the database owner, which can grant you privileges.";
                                    $response['code'] = 403;
                                } else {
                                    $response['success'] = true;
                                    $response['code'] = 200;
                                }
                                break;
                        }
                        return $response;
                    } else {
                        $response = array();
                        $response['auth_level'] = $auth;
                        $response[\app\api\v1\Sql::USEDRELSKEY] = $rels;
                        $response['privileges'] = !empty($privileges[$subUser]) ? $privileges[$subUser] : null;
                        $response['session'] = !empty($_SESSION["screen_name"]) ? $_SESSION["screen_name"] : null;

                        if ($auth == "Read/write" || ($transaction)) {
                            $response['success'] = false;
                            $response['message'] = "Not the right key!";
                            $response['code'] = 403;
                            return $response;
                        } else {
                            $response['success'] = true;
                            $response['code'] = 200;
                            return $response;
                        }
                    }
                } else {
                    $response = array();
                    $response['auth_level'] = $auth;
                    $response[\app\api\v1\Sql::USEDRELSKEY] = $rels;
                    $response['session'] = !empty($_SESSION["screen_name"]) ? $_SESSION["screen_name"] : null;

                    if ($auth == "Read/write" || ($transaction)) {
                        if (($apiKey == $inputApiKey && $apiKey != false) || !empty($_SESSION["auth"])) {
                            $response['success'] = true;
                            $response['code'] = 200;
                            return $response;
                        } else {
                            $response['success'] = false;
                            $response['message'] = "Not the right key!";
                            $response['code'] = 403;
                            return $response;
                        }
                    } else {
                        $response['success'] = true;
                        $response['code'] = 200;
                        return $response;
                    }
                }
            }
        } else {
            $response3["success"] = true;
            $response3['session'] = !empty($_SESSION["screen_name"]) ? $_SESSION["screen_name"] : null;
            $response3['auth_level'] = $auth;
            return $response3;
        }
    }
}


