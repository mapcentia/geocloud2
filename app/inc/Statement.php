<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;
;
use app\exceptions\GC2Exception;
use app\models\Authorization;
use app\models\Geofence;
use app\models\Rule;
use app\models\Sql;
use Exception;
use sad_spirit\pg_builder\StatementFactory;

class Statement
{
    private string|null $q;
    private ?string $subUser;
    private ?string $userGroup;
    private Sql $sql;
    private array $cacheInfo;
    private array $params;

    function __construct(private readonly Connection $connection, private readonly bool $convertReturning = true) {

    }

    /**
     * Executes the run method, initializing parameters and processing the input data.
     *
     * @param string $user The user identifier.
     * @param Sql $api An instance of the Sql class for database interactions.
     * @param mixed $json The input parameters, typically in JSON format.
     * @param bool $subuser Optional. Indicates whether the user is a subuser. Defaults to false.
     * @return array The processed response, including additional metadata such as cache information and memory usage.
     * @throws GC2Exception
     */
    public function run(string $user, Sql $api, array $json, bool $subuser, ?string $userGroup): array
    {
        $this->sql = $api;
        if ($subuser) {
            $this->subUser = $user;
            $this->userGroup = $userGroup;
        } else {
            $this->subUser = null;
            $this->userGroup = null;
        }
        $this->params = $json;
        if (!empty($this->params['base64'])) {
            $this->q = Util::base64urlDecode($this->params['q']);
        } else {
            $qInput = $this->params['q'];
            if (!empty($qInput)) {
                // Only urldecode if it looks URL-encoded (contains percent-escapes)
                if (preg_match('/%[0-9a-fA-F]{2}/', $qInput)) {
                    $this->q = urldecode($qInput);
                } else {
                    $this->q = $qInput;
                }
            } else {
                $this->q = null;
            }
        }
        $response = $this->process($this->params['client_encoding'] ?? null, $this->params['type_hints'] ?? null, $this->params['type_formats'] ?? null);
        if (!empty($this->cacheInfo)) {
            $response["cache"] = $this->cacheInfo;
        }
        $response["_peak_memory_usage"] = round(memory_get_peak_usage() / 1024) . " KB";
        return $response;
    }

    /**
     * Processes the given SQL query and executes it based on its type (SELECT, INSERT, UPDATE, DELETE).
     * Performs authorization checks, applies rules, handles caching, and executes the SQL operation
     * with the provided configurations and parameters.
     *
     * @param string|null $clientEncoding The character set for the SQL query execution.
     * @param array|null $typeHints Optional type hints for parameter bindings in the query.
     * @param array|null $typeFormats Optional type formats for parameter processing in the query.
     * @return array The processed and executed SQL result, including metadata and potential filters.
     *
     * @throws Exception If there is an error during the authorization, rule application, or query execution.
     * @throws GC2Exception If the SQL statement type is not recognized.
     */
    private function process(?string $clientEncoding = null, ?array $typeHints = null, ?array $typeFormats = null): array
    {
        $response = [];
        $authResponse = [];
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
            $authResponse = (new Authorization(connection: $this->connection))->check(relName: $rel, transaction: $type == "t", isAuth: true, subUser: $this->subUser, userGroup: $this->userGroup, rels: $usedRelationsWithType);
            if (!$authResponse["success"]) {
                return $authResponse;
            }
        }

        // Get rules and set them
        $walkerRule = new TableWalkerRule(!empty($authResponse["is_auth"]) ? $this->subUser ?: $this->connection->database : "*", "sql", strtolower($operation), '');
        $rules = $rule->get($this->sql);
        $walkerRule->setRules($rules);
        $select->dispatch($walkerRule);

        // TODO Set this in TableWalkerRule
        if ($operation == "Update" || $operation == "Insert" || $operation == "Delete") {
            if ($operation == "Insert") {
                $split = explode(".", $usedRelations["insert"][0]);
            } else {
                $split = explode(".", $usedRelations["updateAndDelete"][0]);
            }
            $userFilter = new UserFilter($this->subUser ?: $this->connection->database, "sql", strtolower($operation), "*", $split[0], $split[1]);
            $geofence = new Geofence($userFilter);
            $auth = $geofence->authorize($rules);
            $finaleStatement = $factory->createFromAST($select, true)->getSql();
            if ($auth["access"] == Geofence::LIMIT_ACCESS) {
                try {
                    $geofence->postProcessQuery($select, $rules, $this->params['params'], $typeHints);
                } catch (Exception $e) {
                    $response["code"] = 401;
                    $response["success"] = false;
                    $response["message"] = $e->getMessage();
                    $response["statement"] = $finaleStatement;
                    $response["filters"] = $auth["filters"];
                    return $response;
                }
            }
            $response = $this->sql->transaction($finaleStatement, $this->params['params'], $this->params['type_hints'], $this->convertReturning, $this->params['type_formats']);
            $response["filters"] = $auth["filters"];
            $response["statement"] = $finaleStatement;
        } elseif ($operation == "Select" || $operation == "SetOpSelect") {
            $this->q = $factory->createFromAST($select, true)->getSql();
            $lifetime = $this->params['lifetime'] ?? 0;
            $key = md5($this->connection->database . "_" . $this->q . "_" . $lifetime);
            if ($lifetime > 0) {
                $CachedString = Cache::getItem($key);
            }
            if ($lifetime > 0 && !empty($CachedString) && $CachedString->isHit()) {
                $response = $CachedString->get();
                try {
                    $CreationDate = $CachedString->getCreationDate();
                } catch (Exception $e) {
                    $CreationDate = $e->getMessage();
                }
                $this->cacheInfo["hit"] = $CreationDate;
                $this->cacheInfo["tags"] = $CachedString->getTags();
                $this->cacheInfo["signature"] = md5(serialize($response));
            } else {
                ob_start();
                $response = $this->sql->sql($this->q, $clientEncoding, $this->params['format'] ?: "geojson", $this->params['geoformat'] ?? null, $this->params['allstr'] ?? null, $this->params['alias'] ?? null, null, null, $this->params['convert_types'], $this->params['params'] ?? null, $typeHints, $typeFormats);
                if (count($response) > 0) {
                    $response["statement"] = $this->q;
                    if ($lifetime > 0 && !empty($CachedString)) {
                        $CachedString->set($response)->expiresAfter($lifetime ?: 1);// Because 0 secs means cache will life for ever, we set cache to one sec
                        Cache::save($CachedString);
                        $this->cacheInfo["hit"] = false;
                    }
                }
            }
        } else {
            throw new GC2Exception("Check your SQL. Could not recognise it as either SELECT, INSERT, UPDATE or DELETE ($operation)", 403, null, "SQL_STATEMENT_NOT_RECOGNISED");
        }
        unset($authResponse["code"]);
        unset($authResponse["success"]);
        $response["_auth_check"] = $authResponse;
        return $response;
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