<?php
namespace app\api\v1;

use \app\inc\Input;

class Sql extends \app\inc\Controller
{
    public $response;
    private $q;
    private $apiKey;
    private $data;

    public function get_index()
    {
        include_once 'Cache_Lite/Lite.php';

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

    public function post_index()
    {
        return $this->get_index();
    }

    private function transaction($sql, $clientEncoding = null)
    {
        $parsedSQL = \app\inc\SqlParser::ParseString($sql)->getArray();
        if ($parsedSQL['from']) {
            if (
                strpos(strtolower($parsedSQL['from']), 'settings.') !== false ||
                strpos(strtolower($parsedSQL['from']), 'geometry_columns') !== false
            ) {
                $this->response['success'] = false;
                $this->response['message'] = "Can't complete the query";
                $this->response['code'] = 406;
                return serialize($this->response);
            }
        }
        if (strpos($sql, ';') !== false) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = "You can't use ';'. Use the bulk transaction API instead";
        } elseif (strpos($sql, '--') !== false) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = "SQL comments '--' are not allowed";
        } elseif ($parsedSQL['drop']) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = "DROP is not allowed through the API";
        } elseif ($parsedSQL['alter']) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = "ALTER is not allowed through the API";
        } elseif ($parsedSQL['create']) {
            if (strpos(strtolower($parsedSQL['create']), 'create view') !== false) {
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
        } elseif ($parsedSQL['update'] || $parsedSQL['insert'] || $parsedSQL['delete']) {
            if ($this->apiKey == Input::get('key') && $this->apiKey != false) {
                $api = new \app\models\Sql();
                $this->response = $api->transaction($this->q);
            } else {
                $this->response['success'] = false;
                $this->response['message'] = "Not the right key!";
                $this->response['code'] = 403;
            }
        } elseif ($parsedSQL['select']) {
            $lifetime = (Input::get('lifetime')) ? : 0;
            $options = array('cacheDir' => \app\conf\App::$param['path'] . "app/tmp/", 'lifeTime' => $lifetime);
            $Cache_Lite = new \Cache_Lite($options);
            if ($this->data = $Cache_Lite->get($this->q)) {
                //echo "Cached";
            } else {
                //echo "Not cached";
                ob_start();
                $srs = (Input::get('srs')) ? : "900913";
                $api = new \app\models\Sql($srs);
                //$api->execQuery("set client_encoding='UTF8'", "PDO");
                $this->response = $api->sql($this->q, $clientEncoding);
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