<?php
/**
 * Long description for file
 *
 * Long description for file (if any)...
 *
 * @category   API
 * @package    app\api\v1
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 * @since      File available since Release 2013.1
 *
 */

namespace app\api\v1;

use \app\inc\Input;

/**
 * Class Sql
 * @package app\api\v1
 */
class Sql extends \app\inc\Controller
{
    /**
     * @var array
     */
    public $response;

    /**
     * @var string
     */
    private $q;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var array
     */
    private $data;

    /**
     * @var string
     */
    private $subUser;

    /**
     * @var array
     */
    private $usedRelations;

    const USEDRELSKEY = "checked_relations";

    /**
     * Sql constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     */
    public function get_index() : array
    {
        include_once 'Cache_Lite/Lite.php';

        $db = Input::getPath()->part(4);
        $dbSplit = explode("@", $db);

        if (sizeof($dbSplit) == 2) {
            $this->subUser = $dbSplit[0];
        } elseif (isset($_SESSION["subuser"])) {
            $this->subUser = $_SESSION["screen_name"];
        } else {
            $this->subUser = null;
        }
        if (Input::get('base64') === "true") {
            $this->q = urldecode(base64_decode(urldecode(Input::get('q'))));
        } else {
            $this->q = urldecode(Input::get('q'));
        }

        //die($this->q);

        $settings_viewer = new \app\models\Setting();
        $res = $settings_viewer->get();
        $this->apiKey = $res['data']->api_key;

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

    /**
     * TODO is it used?
     */
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

    /**
     * @param array $array
     * @param string $needle
     * @return array
     */
    private function recursiveFind(array $array, string $needle) : array
    {
        $iterator = new \RecursiveArrayIterator($array);
        $recursive = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);
        $aHitList = [];
        foreach ($recursive as $key => $value) {
            if ($key === $needle) {
                array_push($aHitList, $value);
            }
        }
        return $aHitList;
    }

    /**
     * @param array $fromArr
     */
    private function parseSelect(array $fromArr = null)
    {
        foreach ($fromArr as $table) {
            if ($table["expr_type"] == "subquery") {

                // Recursive call
                $this->parseSelect($table["sub_tree"]["FROM"]);
            }
            $table["no_quotes"] = str_replace('"', '', $table["no_quotes"]);
            if (
                explode(".", $table["no_quotes"])[0] == "settings" ||
                explode(".", $table["no_quotes"])[0] == "information_schema" ||
                explode(".", $table["no_quotes"])[0] == "sqlapi" ||
                explode(".", $table["no_quotes"])[1] == "geometry_columns" ||
                $table["no_quotes"] == "geometry_columns"
            ) {
                $this->response['success'] = false;
                $this->response['message'] = "Can't complete the query";
                $this->response['code'] = 403;
                die(\app\inc\Response::toJson($this->response));
            }
            if ($table["no_quotes"]) {
                $this->usedRelations[] = $table["no_quotes"];
            }
            $this->usedRelations = array_unique($this->usedRelations);
        }
    }

    /**
     * @param string $sql
     * @param string|null $clientEncoding
     * @return string
     */
    private function transaction(string $sql, string $clientEncoding = null)
    {

        $response = [];
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
        try {
            $parser = new \PHPSQLParser($sql, false);
        } catch (\UnableToCalculatePositionException $e) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = $e->getMessage();
            return serialize($this->response);
        }
        $parsedSQL = $parser->parsed;
        $this->usedRelations = array();

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
                if (
                    explode(".", $table["no_quotes"])[0] == "settings" ||
                    explode(".", $table["no_quotes"])[0] == "information_schema" ||
                    explode(".", $table["no_quotes"])[0] == "sqlapi" ||
                    explode(".", $table["no_quotes"])[1] == "geometry_columns" ||
                    $table["no_quotes"] == "geometry_columns"
                ) {
                    $this->response['success'] = false;
                    $this->response['message'] = "Can't complete the query";
                    $this->response['code'] = 406;
                    return serialize($this->response);
                }
                array_push($this->usedRelations, $table["no_quotes"]);
                $response = $this->ApiKeyAuthLayer($table["no_quotes"], $this->subUser, true, Input::get('key'), $this->usedRelations);
                if (!$response["success"]) {
                    return serialize($response);
                }
            }

            // Check SQL DELETE
        } elseif (isset($parsedSQL['DELETE'])) {
            foreach ($parsedSQL['FROM'] as $table) {
                if (
                    explode(".", $table["no_quotes"])[0] == "settings" ||
                    explode(".", $table["no_quotes"])[0] == "information_schema" ||
                    explode(".", $table["no_quotes"])[0] == "sqlapi" ||
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
                if (
                    explode(".", $table["no_quotes"])[0] == "settings" ||
                    explode(".", $table["no_quotes"])[0] == "information_schema" ||
                    explode(".", $table["no_quotes"])[0] == "sqlapi" ||
                    explode(".", $table["no_quotes"])[1] == "geometry_columns" ||
                    $table["no_quotes"] == "geometry_columns"
                ) {
                    $this->response['success'] = false;
                    $this->response['message'] = "Can't complete the query";
                    $this->response['code'] = 406;
                    return serialize($this->response);
                }
                array_push($this->usedRelations, $table["no_quotes"]);
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
            if (isset($parsedSQL['CREATE']) && isset($parsedSQL['VIEW'])) {
                if ($this->apiKey == Input::get('key') && $this->apiKey != false) {
                    $api = new \app\models\Sql();
                    $this->response = $api->transaction($this->q);
                    $this->addAttr($response);
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
            $this->addAttr($response);
        } elseif (isset($parsedSQL['SELECT']) || isset($parsedSQL['UNION'])) {
            $lifetime = (Input::get('lifetime')) ?: 0;
            $options = array('cacheDir' => \app\conf\App::$param['path'] . "app/tmp/", 'lifeTime' => $lifetime);
            $Cache_Lite = new \Cache_Lite($options);
            if ($this->data = $Cache_Lite->get($this->q)) {
                //echo "Cached";
            } else {
                //echo "Not cached";
                ob_start();
                $srs = Input::get('srs') ?: "900913";

                $format = Input::get('format') ?: "geojson";
                if (!in_array($format, ["geojson", "csv", "excel"])) {
                    die("{$format} is not a supported format.");
                }

                $geoformat = Input::get('geoformat') ?: null;
                if (!in_array($geoformat, [null, "geojson", "wkt"])) {
                    die("{$geoformat} is not a supported geom format.");
                }
                $csvAllToStr = Input::get('allstr') ?: null;

                $alias = Input::get('alias') ?: null;


                $api = new \app\models\Sql($srs);
                $this->response = $api->sql($this->q, $clientEncoding, $format, $geoformat, $csvAllToStr, $alias);
                $this->addAttr($response);

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

    /**
     * @param $arr
     */
    private function addAttr(array $arr)
    {
        foreach ($arr as $key => $value) {
            if ($key != "code") {
                $this->response["auth_check"][$key] = $value;
            }
        }

    }
}