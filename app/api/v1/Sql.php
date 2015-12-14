<?php
namespace app\api\v1;

use \app\inc\Input;

class Sql extends \app\inc\Controller
{
    public $response;
    private $q;
    private $apiKey;
    private $data;
    private $subUser;
    private $usedRelations;

    public function get_index()
    {
        include_once 'Cache_Lite/Lite.php';

        $db = Input::getPath()->part(4);
        $dbSplit = explode("@", $db);

        if (sizeof($dbSplit) == 2) {
            $this->subUser = $dbSplit[0];
        } elseif (isset($_SESSION["subuser"])) {
            $this->subUser = $_SESSION["subuser"];
        } else {
            $this->subUser = null;
        }
        if (Input::get('base64') === "true") {
            $this->q = base64_decode(Input::get('q'));
        } else {
            $this->q = urldecode(Input::get('q'));
        }
        $settings_viewer = new \app\models\Setting();
        $res = $settings_viewer->get();
        $this->apiKey = $res['data']['api_key'];

        $this->response = $this->transaction($this->q, Input::get('client_encoding'));

        // Check if $this->data is set in SELECT section
        if (!$this->data) {
            $this->data = $this->response;
        }
        return unserialize($this->data);
    }

    public function post_select()
    {
        $input = json_decode(Input::get());
        $tokens = $input->data;
        print_r($tokens);

        $sql = "SELECT " . $tokens->fields . " FROM " . $tokens->from;
        if (isset($tokens->where)) {
            $sql .= " WHERE " . $tokens->where;
        }
        if (isset($tokens->order)) {
            $sql .= " ORDER BY " . $tokens->where;
        }
        if (isset($tokens->LIMIT)) {
            $sql .= " LIMIT " . $tokens->where;
        }
        print_r($sql);

    }

    public function post_index()
    {
        return $this->get_index();
    }

    private function recursiveFind(array $array, $needle)
    {
        $iterator = new \RecursiveArrayIterator($array);
        $recursive = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);
        $aHitList = array();
        foreach ($recursive as $key => $value) {
            if ($key === $needle) {
                array_push($aHitList, $value);
            }
        }
        return $aHitList;
    }

    private function parseSelect($fromArr)
    {
        foreach ($fromArr as $table) {
            if ($table["expr_type"] == "subquery") {
                // Recursive call
                $this->parseSelect($table["sub_tree"]["FROM"]);
            }
            $table["no_quotes"] = str_replace('"', '', $table["no_quotes"]);
            if (explode(".", $table["no_quotes"])[0] == "settings" ||
                explode(".", $table["no_quotes"])[1] == "geometry_columns" ||
                $table["no_quotes"] == "geometry_columns"
            ) {
                $this->response['success'] = false;
                $this->response['message'] = "Can't complete the query";
                $this->response['code'] = 406;
                return serialize($this->response);
            }
            if ($table["no_quotes"]) {
                $this->usedRelations[] = $table["no_quotes"];
            }
            $this->usedRelations = array_unique($this->usedRelations);
        }
    }

    private function transaction($sql, $clientEncoding = null)
    {
        if (strpos($sql, ';') !== false) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = "You can't use ';'. Use the bulk transaction API instead";
            return serialize($this->response);
        }
        if (strpos($sql, '--') !== false) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = "SQL comments '--' are not allowed";
            return serialize($this->response);
        }
        require_once dirname(__FILE__) . '/../../libs/PHP-SQL-Parser/src/PHPSQLParser.php';
        $parser = new \PHPSQLParser($sql, true);
        $parsedSQL = $parser->parsed;
        $this->usedRelations = array();

        //print_r($parsedSQL);

        // First recursive go through the SQL to find FROM in select, update and delete
        foreach ($this->recursiveFind($parsedSQL, "FROM") as $x) {
            $this->parseSelect($x);
        }

        // Check auth on relations
        foreach ($this->usedRelations as $rel) {
            $response = $this->ApiKeyAuthLayer($rel, $this->subUser, false, Input::get('key'), $this->usedRelations);
            if (!$response["success"]) {
                return serialize($response);
            }
        }

        // Check SQL UPDATE
        if (isset($parsedSQL['UPDATE'])) {
            foreach ($parsedSQL['UPDATE'] as $table) {
                $this->usedRelations[] = $table["no_quotes"];
                if (explode(".", $table["no_quotes"])[0] == "settings" ||
                    explode(".", $table["no_quotes"])[1] == "geometry_columns" ||
                    $table["no_quotes"] == "geometry_columns"
                ) {
                    $this->response['success'] = false;
                    $this->response['message'] = "Can't complete the query";
                    $this->response['code'] = 406;
                    return serialize($this->response);
                }
                $response = $this->ApiKeyAuthLayer($table["no_quotes"], $this->subUser, true, Input::get('key'), $this->usedRelations);
                if (!$response["success"]) {
                    return serialize($response);
                }
            }

            // Check SQL DELETE
        } elseif (isset($parsedSQL['DELETE'])) {
            foreach ($parsedSQL['FROM'] as $table) {
                $this->usedRelations[] = $table["no_quotes"];
                if (explode(".", $table["no_quotes"])[0] == "settings" ||
                    explode(".", $table["no_quotes"])[1] == "geometry_columns" ||
                    $table["no_quotes"] == "geometry_columns"
                ) {
                    $this->response['success'] = false;
                    $this->response['message'] = "Can't complete the query";
                    $this->response['code'] = 406;
                    return serialize($this->response);
                }
                $response = $this->ApiKeyAuthLayer($table["no_quotes"], $this->subUser, true, Input::get('key'), $this->usedRelations);
                if (!$response["success"]) {
                    return serialize($response);
                }
            }

            // Check SQL INSERT
        } elseif (isset($parsedSQL['INSERT'])) {
            foreach ($parsedSQL['INSERT'] as $table) {
                $this->usedRelations[] = $table["no_quotes"];
                if (explode(".", $table["no_quotes"])[0] == "settings" ||
                    explode(".", $table["no_quotes"])[1] == "geometry_columns" ||
                    $table["no_quotes"] == "geometry_columns"
                ) {
                    $this->response['success'] = false;
                    $this->response['message'] = "Can't complete the query";
                    $this->response['code'] = 406;
                    return serialize($this->response);
                }
                $response = $this->ApiKeyAuthLayer($table["no_quotes"], $this->subUser, true, Input::get('key'), $this->usedRelations);
                if (!$response["success"]) {
                    return serialize($response);
                }
            }
        }

        if ($parsedSQL['DROP']) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = "DROP is not allowed through the API";
        } elseif ($parsedSQL['ALTER']) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = "ALTER is not allowed through the API";
        } elseif ($parsedSQL['CREATE']) {
            if (strpos(strtolower($parsedSQL['CREATE']), 'create view') !== false) {
                if ($this->apiKey == Input::get('key') && $this->apiKey != false) {
                    $api = new \app\models\Sql();
                    $this->response = $api->transaction($this->q);
                } else {
                    $this->response['success'] = false;
                    $this->response['message'] = "Not the right key!";
                    $this->response['code'] = 403;
                }
            } else {
                $this->response['success'] = false;
                $this->response['message'] = "Only CREATE VIEW is allowed through the API";
                $this->response['code'] = 403;
            }
        } elseif ($parsedSQL['UPDATE'] || $parsedSQL['INSERT'] || $parsedSQL['DELETE']) {
            $api = new \app\models\Sql();
            $this->response = $api->transaction($this->q);
        } elseif ($parsedSQL['SELECT']) {
            $lifetime = (Input::get('lifetime')) ?: 0;
            $options = array('cacheDir' => \app\conf\App::$param['path'] . "app/tmp/", 'lifeTime' => $lifetime);
            $Cache_Lite = new \Cache_Lite($options);
            if ($this->data = $Cache_Lite->get($this->q)) {
                //echo "Cached";
            } else {
                //echo "Not cached";
                ob_start();
                $srs = Input::get('srs') ?: "900913";
                $api = new \app\models\Sql($srs);
                $this->response = $api->sql($this->q, $clientEncoding);
                $this->response["rels"] = $this->usedRelations;

                echo serialize($this->response);
                // Cache script
                $this->data = ob_get_contents();
                $Cache_Lite->save($this->data, $this->q);
                ob_get_clean();
            }
        } else {
            $this->response['success'] = false;
            $this->response['message'] = "Check your SQL. Could not recognise it as either SELECT, INSERT, UPDATE or DELETE";
            $this->response['code'] = 400;
        }
        return serialize($this->response);
    }
}