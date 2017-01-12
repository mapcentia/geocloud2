<?php
namespace app\inc;

use app\conf\App;

/**
 * Class Controller
 * @package app\inc
 */
class Controller
{
    public $response;

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
     * @param null $key
     * @param array $level
     * @param bool $neverAllowSubUser
     * @return array
     */
    public function auth($key = null, $level = array("all" => true), $neverAllowSubUser = false)
    {
        $response = [];
        if ($_SESSION['subuser'] == \app\conf\Connection::$param['postgisschema'] && $neverAllowSubUser == false) {
            $response['success'] = true;
        } elseif ($_SESSION['subuser']) {
            $text = "You don't have privileges to do this. Please contact the database owner, who can grant you privileges.";
            if (sizeof($level) == 0) {
                $response['success'] = false;
                $response['message'] = $text;
                $response['code'] = 403;
            } else {
                $layer = new \app\models\Layer();
                $privileges = json_decode($layer->getValueFromKey($key, "privileges"));
                $prop = $_SESSION['usergroup'] ?: $_SESSION['subuser'];
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
     * @param $user
     * @param $key
     * @return bool
     */
    public function authApiKey($user, $key)
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
        $apiKey = $res['data']['api_key'];
        if ($apiKey == $key && $key != false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $layer
     * @param $db
     * @param $subUser
     */
    public function basicHttpAuthLayer($layer, $db, $subUser)
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
     * @param $layer
     * @param $subUser
     * @param $transaction
     * @param $inputApiKey
     * @param $rels
     * @return array
     */
    public function ApiKeyAuthLayer($layer, $subUser, $transaction, $inputApiKey, $rels)
    {
        // Check if layer has schema prefix and add 'public' if no.
        $bits = explode(".", $layer);
        if (sizeof($bits) == 1) {
            $schema = "public";
            $layer = $schema . "." . $layer;
        } else {
            $schema = $bits[0];
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
            $sql = "SELECT * FROM settings.geometry_columns_join WHERE _key_ LIKE :schema";
            $res = $postgisObject->prepare($sql);
            try {
                $res->execute(array("schema" => $layer . ".%"));
            } catch (\PDOException $e) {
                $response = array();
                $response2['success'] = false;
                $response2['message'] = $e->getMessage();
                $response2['code'] = 401;
                die(Response::toJson($response));
            }
            while ($row = $postgisObject->fetchRow($res, "assoc")) {
                if ($subUser) {

                    $privileges = (array)json_decode($row["privileges"]);
                    if (($apiKey == $inputApiKey && $apiKey != false) || $_SESSION["auth"]) {
                        $response = array();
                        $response['auth_level'] = $auth;
                        $response['privileges'] = $privileges[$subUser];
                        $response[\app\api\v1\Sql::USEDRELSKEY] = $rels;

                        switch ($transaction) {
                            case false:
                                if ($privileges[$userGroup ?: $subUser] == false || $privileges[$userGroup ?: $subUser] == "none") {
                                    $response['success'] = false;
                                    $response['message'] = "You don't have privileges to see '{$layer}'. Please contact the database owner, which can grant you privileges.";
                                    $response['code'] = 403;
                                } else {
                                    $response['success'] = true;
                                    $response['code'] = 200;
                                }
                                break;
                            case true:
                                if ($privileges[$userGroup ?: $subUser] == false || $privileges[$userGroup ?: $subUser] == "none" || $privileges[$userGroup ?: $subUser] == "read") {
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
                        $response['privileges'] = $privileges[$subUser];
                        $response['session'] = $_SESSION["subuser"] ?: $_SESSION["screen_name"];

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
                    $response['session'] = $_SESSION["subuser"] ?: $_SESSION["screen_name"];

                    if ($auth == "Read/write" || ($transaction)) {
                        if (($apiKey == Input::get('key') && $apiKey != false) || $_SESSION["auth"]) {
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
            $response3['session'] = $_SESSION["subuser"] ?: $_SESSION["screen_name"];
            $response3['auth_level'] = $auth;
            return $response3;
        }
    }
}


