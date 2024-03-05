<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use app\conf\Connection;
use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\inc\Controller;
use app\inc\Input;
use app\inc\TableWalkerRelation;
use app\inc\TableWalkerRule;
use app\inc\UserFilter;
use app\models\Geofence;
use app\models\Rule;
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
    public array $response;

    private string|null $q;

    private string $apiKey;

    private string $data;

    private ?string $subUser;

    private \app\models\Sql $api;

    const USEDRELSKEY = "checked_relations";

    private array $cacheInfo;

    private bool $streamFlag;

    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    public function get_index(): array
    {
        // Get the URI params from request
        // /{user}
        $r = func_get_arg(0);
        $dbSplit = explode("@", $r["user"]);
        if (sizeof($dbSplit) == 2) {
            $this->subUser = $dbSplit[0];
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
                    "convert_types" => !empty($json["convert_types"]) ? $json["convert_types"] : Input::$params["convert_types"],
                    "params" => !empty($json["params"]) ? $json["params"] : Input::$params["params"],
                    "type_hints" => !empty($json["type_hints"]) ? $json["type_hints"] : Input::$params["type_hints"],
                ]
            );
        }

        if (Input::get('format') == "ndjson") {
            $this->streamFlag = true;
        }

        if (Input::get('base64') === true || Input::get('base64') === "true") {
            $this->q = Util::base64urlDecode(Input::get("q"));
        } else {
            $this->q = !empty(Input::get('q')) ? urldecode(Input::get('q')) : null;
        }

        if (!$this->q) {
            throw new GC2Exception("Query is missing (the 'q' parameter)", 403, null, "MISSING_PARAMETER");
        }

        $settings = new Setting();
        $res = $settings->get();

        // Check if success
        // ================
        if (!$res["success"]) {
            return $res;
        }

        $srs = Input::get('srs') ?: "3857";
        $this->api = new \app\models\Sql($srs);
        $this->api->connect();
        $this->apiKey = $res['data']->api_key;

        $serializedResponse = $this->transaction(Input::get('client_encoding'), Input::get('type_hints'));

        // Check if $this->data is set in SELECT section
        if (!isset($this->data)) {
            $this->data = $serializedResponse;
        }
        $response = unserialize($this->data);
        if (!empty($this->cacheInfo)) {
            $response["cache"] = $this->cacheInfo;
        }
        $response["peak_memory_usage"] = round(memory_get_peak_usage() / 1024) . " KB";
        return $response;
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
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

            $settings = new Setting();
            $res = $settings->get();

            // Check if success
            // ================
            if (!$res["success"]) {
                return $res;
            }

            $this->api = new \app\models\Sql();
            $this->api->connect();
            $this->apiKey = $res['data']->api_key;

            if (empty(Input::getBody())) {
                throw new GC2Exception("Empty text body", 403, null, "EMPTY_BODY");
            }

            $sqls = explode("\n", Input::getBody());
            $this->api->begin(); // Start transaction
            $res = [];
            foreach ($sqls as $q) {
                $this->q = $q;
                if ($this->q != "") {
                    $res = unserialize($this->transaction());
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
     * @param string|null $clientEncoding
     * @return string
     * @throws PhpfastcacheInvalidArgumentException
     * @throws Exception
     */
    private function transaction(?string $clientEncoding = null, ?array $typeHints = null): string
    {
        $response = [];
        $rule = new Rule();
        $walkerRelation = new TableWalkerRelation();
        $factory = new StatementFactory(PDOCompatible: true);
        $select = $factory->createFromString($this->q);
        $operation = self::getClassName(get_class($select));
        $select->dispatch($walkerRelation);
        $usedRelations = $walkerRelation->getRelations();
        $usedRelationsWithType = [];

        // Check auth on relations
        foreach (array_merge($usedRelations["insert"], $usedRelations["updateAndDelete"]) as $rel) {
            $usedRelationsWithType[$rel] = "t";
        }
        foreach ($usedRelations["all"] as $rel) {
            if (!isset($usedRelationsWithType[$rel])) {
                $usedRelationsWithType[$rel] = "s";
            }
        }
        foreach ($usedRelationsWithType as $rel => $type) {
            $response = $this->ApiKeyAuthLayer($rel, $type == "t", $usedRelationsWithType, $this->subUser, Input::get('key'));
            if (!$response["success"]) {
                return serialize($response);
            }
        }

        // Get rules and set them
        $walkerRule = new TableWalkerRule(!empty($response["is_auth"]) ? $this->subUser ?: Connection::$param['postgisdb'] : "*", "sql", strtolower($operation), '');
        $rules = $rule->get();
        $walkerRule->setRules($rules);
        $select->dispatch($walkerRule);

        // TODO Set this in TableWalkerRule
        if ($operation == "Update" || $operation == "Insert" || $operation == "Delete") {
            if ($operation == "Insert") {
                $split = explode(".", $usedRelations["insert"][0]);
            } else {
                $split = explode(".", $usedRelations["updateAndDelete"][0]);
            }
            $userFilter = new UserFilter($this->subUser ?: Connection::$param['postgisdb'], "sql", strtolower($operation), "*", $split[0], $split[1]);
            $geofence = new Geofence($userFilter);
            $auth = $geofence->authorize($rules);
            $finaleStatement = $factory->createFromAST($select, true)->getSql();
            if ($auth["access"] == Geofence::LIMIT_ACCESS) {
                try {
                    $geofence->postProcessQuery($select, $rules, Input::get('params'), $typeHints);
                } catch (Exception $e) {
                    $response = [];
                    $response["code"] = 401;
                    $response["success"] = false;
                    $response["message"] = $e->getMessage();
                    $response["statement"] = $finaleStatement;
                    $response["filters"] = $auth["filters"];
                    return serialize($response);
                }
            }
            $this->response = $this->api->transaction($finaleStatement, Input::get('params') ?: null, Input::get('type_hints') ?: null);
            $response["filters"] = $auth["filters"];
            $response["statement"] = $finaleStatement;
            $this->addAttr($response);
        } elseif ($operation == "Select" || $operation == "SetOpSelect") {
            $this->q = $factory->createFromAST($select, true)->getSql();
            if (isset($this->streamFlag)) {
                $stream = new Stream();
                $stream->runSql($this->q);
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
                $this->response = $this->api->sql($this->q, $clientEncoding, Input::get('format') ?: "geojson", Input::get('geoformat') ?: null, Input::get('allstr') ?: null, Input::get('alias') ?: null, null, null, Input::get('convert_types') ?: null, Input::get('params') ?: null);
                $response["statement"] = $this->q;
                $this->addAttr($response);
                echo serialize($this->response);
                $this->data = ob_get_contents();
                if ($lifetime > 0 && !empty($CachedString)) {
                    $CachedString->set($this->data)->expiresAfter($lifetime ?: 1);// Because 0 secs means cache will life for ever, we set cache to one sec
                    // $CachedString->addTags(["sql", Connection::$param["postgisdb"]]);
                    Cache::save($CachedString);
                    $this->cacheInfo["hit"] = false;
                }
                ob_get_clean();
            }
        } else {
            throw new GC2Exception("Check your SQL. Could not recognise it as either SELECT, INSERT, UPDATE or DELETE ($operation)", 403, null, "SQL_STATEMENT_NOT_RECOGNISED");
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
     * @return string|false
     */
    private static function getClassName(string $classname): string|false
    {
        if ($pos = strrpos($classname, '\\')) {
            return substr($classname, $pos + 1);
        }
        return false;
    }
}