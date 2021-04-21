<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use app\conf\Connection;
use app\inc\Cache;
use app\inc\Controller;
use app\inc\Input;
use app\inc\Response;
use app\inc\Session;
use app\models\Setting;
use app\models\Stream;
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use PHPSQLParser;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

use app\inc\Util;

/**
 * Class Sql
 * @package app\api\v1
 */
class Sql extends Controller
{
    /**
     * @var array<mixed>
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
     * @var string
     */
    private $data;

    /**
     * @var string|null
     */
    private $subUser;

    /**
     * @var \app\models\Sql
     */
    private $api;

    /**
     * @var array<string>
     */
    private $usedRelations;

    /**
     *
     */
    const USEDRELSKEY = "checked_relations";

    /**
     * @var array<mixed>
     */
    private $cacheInfo;

    /**
     * @var boolean
     */
    private $streamFlag;

    /**
     * @var string
     */
    private $db;

    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_index(): array
    {
        // Get the URI params from request
        // /{user}
        $r = func_get_arg(0);


        $db = $r["user"];
        $dbSplit = explode("@", $db);

        if (sizeof($dbSplit) == 2) {
            $this->subUser = $dbSplit[0];
            $this->db = $dbSplit[1];
        } elseif (!empty($_SESSION["subuser"])) {
            $this->subUser = $_SESSION["screen_name"];
            $this->db = Session::getDatabase();
        } else {
            $this->subUser = null;
            $this->db = $db;
        }

        // Check if body is JSON
        // Supports both GET and POST
        // ==========================
        $json = json_decode(Input::getBody(), true);

        // If JSON body when set GET input params
        // ======================================
        if ($json != null) {

            // Set input params from JSON
            // ==========================
            Input::setParams(
                [
                    "q" => !empty($json["q"]) ? $json["q"] : null,
                    "client_encoding" => !empty($json["client_encoding"]) ? $json["client_encoding"] : null,
                    "srs" => !empty($json["srs"]) ? $json["srs"] : null,
                    "format" => !empty($json["format"]) ? $json["format"] : null,
                    "geoformat" => !empty($json["geoformat"]) ? $json["geoformat"] : null,
                    "key" => !empty($json["key"]) ? $json["key"] : null,
                    "geojson" => !empty($json["geojson"]) ? $json["geojson"] : null,
                    "allstr" => !empty($json["allstr"]) ? $json["allstr"] : null,
                    "alias" => !empty($json["alias"]) ? $json["alias"] : null,
                    "lifetime" => !empty($json["lifetime"]) ? $json["lifetime"] : null,
                    "base64" => !empty($json["base64"]) ? $json["base64"] : null,
                ]
            );
        }

        if (Input::get('format') == "ndjson") {
            $this->streamFlag = true;
        }

        if (Input::get('base64') === true || Input::get('base64') === "true") {
            $this->q = Util::base64urlDecode(Input::get("q"));
        } else {
            $this->q = urldecode(Input::get('q'));
        }

        if (!$this->q) {
            $response['success'] = false;
            $response['code'] = 403;
            $response['message'] = "Query is missing (the 'q' parameter)";
            return $response;
        }

        $settings_viewer = new Setting();
        $res = $settings_viewer->get();

        // Check if success
        // ================
        if (!$res["success"]) {
            return $res;
        }

        $srs = Input::get('srs') ?: "900913";
        $this->api = new \app\models\Sql($srs);
        $this->api->connect();
        $this->apiKey = $res['data']->api_key;

        $serializedResponse = $this->transaction($this->q, Input::get('client_encoding'));

        // Check if $this->data is set in SELECT section
        if (!$this->data) {
            $this->data = $serializedResponse;
        }
        $response = unserialize($this->data);
        if ($this->cacheInfo) {
            $response["cache"] = $this->cacheInfo;
        }
        $response["peak_memory_usage"] = round(memory_get_peak_usage() / 1024) . " KB";
        return $response;
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_index(): array
    {
        $r = func_get_arg(0);

        // Use bulk if content type is text/plain
        if (Input::getContentType() == Input::TEXT_PLAIN) {
            $db = $r["user"];
            $dbSplit = explode("@", $db);
            if (sizeof($dbSplit) == 2) {
                $this->subUser = $dbSplit[0];
                $this->db = $dbSplit[1];
            } elseif (!empty($_SESSION["subuser"])) {
                $this->subUser = $_SESSION["screen_name"];
                $this->db = Session::getDatabase();
            } else {
                $this->subUser = null;
                $this->db = $db;
            }

            // Set API key from headers
            Input::setParams(
                [
                    "key" => Input::getApiKey()
                ]
            );

            $settings_viewer = new Setting();
            $res = $settings_viewer->get();

            // Check if success
            // ================
            if (!$res["success"]) {
                return $res;
            }

            $this->api = new \app\models\Sql();
            $this->api->connect();
            $this->apiKey = $res['data']->api_key;


            if (empty(Input::getBody())) {
                return [
                    "success" => false,
                    "message" => "Empty text body",
                    "code" => "400"
                ];
            }

            $sqls = explode("\n", Input::getBody());
            $this->api->begin(); // Start transaction
            $res = [];
            foreach ($sqls as $q) {
                $this->q = $q;
                if ($this->q != "") {
                    $res = unserialize($this->transaction($this->q));
                    if (!$res["success"]) {
                        $this->api->rollback();
                        return $res;
                    }
                }
            }
            $this->api->commit();
            return $res;

        } else {
            return $this->get_index($r);
        }
    }

    /**
     * @param array<mixed> $array
     * @param string $needle
     * @return array<mixed>
     */
    private function recursiveFind(array $array, string $needle): array
    {
        $iterator = new RecursiveArrayIterator($array);
        $recursive = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);
        $aHitList = [];
        foreach ($recursive as $key => $value) {
            if ($key === $needle) {
                array_push($aHitList, $value);
            }
        }
        return $aHitList;
    }

    /**
     * @param array<mixed>|null $fromArr
     */
    private function parseSelect(?array $fromArr = null): void
    {
        if (is_array($fromArr)) {
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
                    die(Response::toJson($this->response));
                }
                if ($table["no_quotes"]) {
                    $this->usedRelations[] = $table["no_quotes"];
                }
                $this->usedRelations = array_unique($this->usedRelations);
            }
        }
    }

    /**
     * @param string $sql
     * @param string|null $clientEncoding
     * @return string
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function transaction(string $sql, ?string $clientEncoding = null): string
    {
        $response = [];
        require_once dirname(__FILE__) . '/../../libs/PHP-SQL-Parser/src/PHPSQLParser.php';
        $parser = new PHPSQLParser($sql, false);
        $parsedSQL = $parser->parsed ?: []; // Make its an array
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
                array_push($this->usedRelations, $table["no_quotes"]);
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

        if (!empty($parsedSQL['DROP'])) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = "DROP is not allowed through the API";
        } elseif (!empty($parsedSQL['ALTER'])) {
            $this->response['success'] = false;
            $this->response['code'] = 403;
            $this->response['message'] = "ALTER is not allowed through the API";
        } elseif (!empty($parsedSQL['CREATE'])) {
            if (!empty($parsedSQL['CREATE']) && (!empty($parsedSQL['VIEW']) || !empty($parsedSQL['TABLE']))) {
                if ($this->apiKey == Input::get('key') && $this->apiKey != false) {
                    $this->response = $this->api->transaction($this->q);
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
        } elseif (!empty($parsedSQL['UPDATE']) || !empty($parsedSQL['INSERT']) || !empty($parsedSQL['DELETE'])) {
            $this->response = $this->api->transaction($this->q);
            $this->addAttr($response);
        } elseif (!empty($parsedSQL['SELECT']) || !empty($parsedSQL['UNION'])) {
            if ($this->streamFlag) {
                $stream = new Stream();
                $res = $stream->runSql($this->q);
                return ($res);
            }

            $lifetime = (Input::get('lifetime')) ?: 0;

            $key = md5(Connection::$param["postgisdb"] . "_" . $this->q . "_" . $lifetime);

            if ($lifetime > 0) {
                $CachedString = Cache::getItem($key);
            }

            if ($lifetime > 0 && !empty($CachedString) && $CachedString->isHit()) {
                $this->data = $CachedString->get();
                try {
                    $CreationDate = $CachedString->getCreationDate();
                } catch (Exception $e) {
                    $CreationDate = $e->getMessage();
                }
                $this->cacheInfo["hit"] = $CreationDate;
                $this->cacheInfo["tags"] = $CachedString->getTags();
                $this->cacheInfo["signature"] = md5(serialize($this->data));

            } else {
                ob_start();
                $format = Input::get('format') ?: "geojson";
                $geoformat = Input::get('geoformat') ?: null;
                $csvAllToStr = Input::get('allstr') ?: null;
                $alias = Input::get('alias') ?: null;
                $this->response = $this->api->sql($this->q, $clientEncoding, $format, $geoformat, $csvAllToStr, $alias);
                if (!$this->response["success"]) {
                    return serialize([
                        "success" => false,
                        "code" => 500,
                        "format" => $format,
                        "geoformat" => $geoformat,
                        "message" => $this->response["message"],
                        "query" => $this->q,
                    ]);
                }
                $this->addAttr($response);
                echo serialize($this->response);
                $this->data = ob_get_contents();
                if ($lifetime > 0 && !empty($CachedString)) {
                    $CachedString->set($this->data)->expiresAfter($lifetime ?: 1);// Because 0 secs means cache will life for ever, we set cache to one sec
                    $CachedString->addTags(["sql", Connection::$param["postgisdb"]]);
                    Cache::save($CachedString); // Save the cache item just like you do with doctrine and entities
                    $this->cacheInfo["hit"] = false;
                }
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
     * @param array<string> $arr
     */
    private function addAttr(array $arr): void
    {
        foreach ($arr as $key => $value) {
            if ($key != "code") {
                $this->response["auth_check"][$key] = $value;
            }
        }

    }
}