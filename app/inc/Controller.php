<?php
namespace app\inc;

use \app\inc\Response;

class Controller
{
    public $response;

    // Implement OPTIONS method for all action controllers. Used in CORS.
    public function options_index()
    {
        //Pass
    }

    public function head_index()
    {
        //Pass
    }

    public function auth($key = null, $level = array("all" => true), $neverAllowSubUser = false)
    {
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
                $privileges = (array)json_decode($layer->getValueFromKey($key, "privileges"));
                //print_r($_SESSION);
                $subuserLevel = $privileges[$_SESSION['usergroup'] ?: $_SESSION['subuser']];
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

    public function authApiKey($user, $key)
    {
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

    public function ApiKeyAuthLayer($layer, $subUser, $transaction, $inputApiKey, $rels)
    {
        $postgisObject = new \app\inc\Model();
        $auth = $postgisObject->getGeometryColumns($layer, "authentication");
        if ($auth == "Read/write" || $auth == "Write") {
            $settings_viewer = new \app\models\Setting();
            $response = $settings_viewer->get();
            if (isset($response["data"]["userGroups"]->$subUser)) {
                $userGroup = $response["data"]["userGroups"]->$subUser;
            } else {
                $userGroup = null;
            }
            if ($subUser) {
                $apiKey = $response['data']['api_key_subuser']->$subUser;
            } else {
                $apiKey = $response['data']['api_key'];
            }
            //if ($dbSplit[0] != $postgisschema) {
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
                                    $response['message'] = "You don't have privileges to see this layer. Please contact the database owner, which can grant you privileges.";
                                    $response['code'] = 403;
                                } else {
                                    $response['success'] = true;
                                    $response['code'] = 200;
                                }
                                break;
                            case true:
                                if ($privileges[$userGroup ?: $subUser] == false || $privileges[$userGroup ?: $subUser] == "none" || $privileges[$userGroup ?: $subUser] == "read") {
                                    $response['success'] = false;
                                    $response['message'] = "You don't have privileges to edit this layer. Please contact the database owner, which can grant you privileges.";
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
            //}
        }
        else {
            $response3["success"] = true;
            $response3['session'] = $_SESSION["subuser"] ?: $_SESSION["screen_name"];
            $response3['auth_level'] = $auth;
            return $response3;
        }
    }
}


