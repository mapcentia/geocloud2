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
            $this->q = rawurldecode(Input::get('q'));
        }
        $settings_viewer = new \app\models\Setting();
        $res = $settings_viewer->get();
        $this->apiKey = $res['data']['api_key'];
        $callback = Input::get('jsonp_callback');

        $this->response = $this->transaction($this->q);

        // Check if $this->data is set in SELECT section
        if (!$this->data) {
            $this->data = json_encode($this->response);
        }

        if ($callback) {
            echo $callback . '(' . $this->data . ');';
        } else {
            echo $this->data;
        }
    }

    private function transaction($sql)
    {
        $parsedSQL = \app\inc\SqlParser::ParseString($sql)->getArray();
        if ($parsedSQL['from']) {
            if (
                strpos(strtolower($parsedSQL['from']), 'settings.') !== false ||
                strpos(strtolower($parsedSQL['from']), 'geometry_columns') !== false
            ) {
                $this->response['success'] = false;
                $this->response['message'] = "Can't complete the query";
                return $this->response;
            }
        }
        if (strpos($sql, ';') !== false) {
            $this->response['success'] = false;
            $this->response['message'] = "You can't use ';'. Use the bulk transaction API instead";
        } elseif (strpos($sql, '--') !== false) {
            $this->response['success'] = false;
            $this->response['message'] = "SQL comments '--' are not allowed";
        } elseif ($parsedSQL['drop']) {
            $this->response['success'] = false;
            $this->response['message'] = "DROP is not allowed through the API";
        } elseif ($parsedSQL['alter']) {
            $this->response['success'] = false;
            $this->response['message'] = "ALTER is not allowed through the API";
        } elseif ($parsedSQL['create']) {
            $this->response['success'] = false;
            $this->response['message'] = "CREATE is not allowed through the API";
        } elseif ($parsedSQL['update'] || $parsedSQL['insert'] || $parsedSQL['delete']) {
            if ($this->apiKey == Input::get('key') || $this->apiKey == false) {
                $api = new \app\models\Sql();
                $this->response = $api->transaction($this->q);
            } else {
                $this->response['success'] = false;
                $this->response['message'] = "Not the right key!";
            }
        } elseif ($parsedSQL['select']) {
            $id = $this->q;
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
                $api->execQuery("set client_encoding='UTF8'", "PDO");
                $this->response = $api->sql($id);
                echo json_encode($this->response);
                // Cache script
                $this->data = ob_get_contents();
                $Cache_Lite->save($this->data, $id);
                ob_get_clean();
            }
        } else {
            $this->response['success'] = false;
            $this->response['message'] = "Check your SQL. Could not recognise it as either SELECT, INSERT, UPDATE or DELETE";
        }
        return $this->response;
    }
}