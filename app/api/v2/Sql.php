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
use app\inc\TableWalkerRelation;
use app\inc\TableWalkerRule;
use app\models\Rules;
use app\models\Setting;
use app\models\Stream;
use sad_spirit\pg_builder\StatementFactory;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Exception;

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
        $dbSplit = explode("@", $r["user"]);
        if (sizeof($dbSplit) == 2) {
            $this->subUser = $dbSplit[0];
        } elseif (!empty($_SESSION["subuser"])) {
            $this->subUser = $_SESSION["screen_name"];
        } else {
            $this->subUser = null;
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
                    "srs" => !empty($json["srs"]) ? $json["srs"] : Input::$params["srs"],
                    "format" => !empty($json["format"]) ? $json["format"] : null,
                    "geoformat" => !empty($json["geoformat"]) ? $json["geoformat"] : null,
                    "key" => !empty($json["key"]) ? $json["key"] : Input::$params["key"],
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
            $dbSplit = explode("@", $r["user"]);
            if (sizeof($dbSplit) == 2) {
                $this->subUser = $dbSplit[0];
            } elseif (!empty($_SESSION["subuser"])) {
                $this->subUser = $_SESSION["screen_name"];
            } else {
                $this->subUser = null;
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
     * @param string $sql
     * @param string|null $clientEncoding
     * @return string
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function transaction(string $sql, ?string $clientEncoding = null): string
    {
        $response = [];
        $walkerRelation = new TableWalkerRelation();
        $walkerRule = new TableWalkerRule();
        $factory = new StatementFactory();
        try {
            $select = $factory->createFromString($this->q);
        } catch (Exception $e) {
            return serialize(
                [
                    "success" => false,
                    "message" => $e->getMessage(),
                    "code" => 400,
                ]
            );
        }
        $operation = self::getClassName(get_class($select));
        $select->dispatch($walkerRelation);
        $usedRelations = $walkerRelation->getRelations();

        // Check auth on relations
        foreach ($usedRelations as $rel) {
            $response = $this->ApiKeyAuthLayer($rel, $this->subUser, false, Input::get('key'), $usedRelations);
            if (!$response["success"]) {
                return serialize($response);
            }
        }
        // Get rules and set them
        $rules = new Rules();
        $walkerRule->setRules($rules->getRules());
        $select->dispatch($walkerRule);
        $this->q = $factory->createFromAST($select)->getSql();

        if ($operation == "Delete" || $operation == "Update" || $operation == "Insert") {
            $this->response = $this->api->transaction($this->q);
            $this->addAttr($response);
        } elseif ($operation == "Select" || $operation == "SetOpSelect") {
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
                    Cache::save($CachedString);
                    $this->cacheInfo["hit"] = false;
                }
                ob_get_clean();
            }
        } else {
            $this->response['success'] = false;
            $this->response['message'] = "Check your SQL. Could not recognise it as either SELECT, INSERT, UPDATE or DELETE ({$operation})";
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

    /**
     * @param string $classname
     * @return string
     */
    private static function getClassName(string $classname): string
    {
        if ($pos = strrpos($classname, '\\')) return substr($classname, $pos + 1);
        return $pos;
    }
}