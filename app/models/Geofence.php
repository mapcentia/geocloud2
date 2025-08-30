<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Util;
use app\inc\Model;
use app\inc\UserFilter;
use app\models\User as UserModel;
use Exception;
use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_builder\StatementFactory;
use sad_spirit\pg_wrapper\converters\DefaultTypeConverterFactory;


/**
 * Class Geofencing
 * @package app\models
 */
class Geofence extends Model
{
    public const string ALLOW_ACCESS = "allow";
    public const string DENY_ACCESS = "deny";
    public const string LIMIT_ACCESS = "limit";

    /**
     * Geofencing constructor.
     * @param UserFilter|null $userFilter
     */
    public function __construct(private readonly ?UserFilter $userFilter = null, ?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
        if ($this->userFilter) {
            $this->userFilter->ipAddress = Util::clientIp();
        }
    }

    /**
     * @param array $rules
     * @return array
     */
    public function authorize(array $rules): array
    {
        $filters = [];
        $response = [];
        foreach ($rules as $rule) {
            if (
                ($this->userFilter->userName == $rule["username"] || fnmatch($rule["username"], $this->userFilter->userName)) &&
                ($this->userFilter->service == $rule["service"] || $rule["service"] == "*") &&
                (Util::ipInRange($this->userFilter->ipAddress, $rule["iprange"]) || $rule["iprange"] == "*") &&
                ($this->userFilter->schema == $rule["schema"] || fnmatch($rule["schema"], $this->userFilter->schema)) &&
                ($this->userFilter->layer == $rule["layer"] || fnmatch($rule["layer"], $this->userFilter->layer)) &&
                ($this->userFilter->request == $rule["request"] || $rule["request"] == "*")
            ) {
                if ($rule["access"] == self::LIMIT_ACCESS) {
                    $filters["filter"] = $this->fillPlaceholders($rule["filter"]);
                }
                $response["access"] = $rule["access"];
                $response["request"] = $rule["request"];
                break;
            }
        }
        $response["filters"] = $filters;
        $response["success"] = true;
        return $response;
    }

    /**
     * @param Statement $statement
     * @param array $rules
     * @param array|null $params
     * @param array|null $typeHints
     * @return true
     * @throws GC2Exception
     * @throws Exception
     */
    public function postProcessQuery(Statement $statement, array $rules, ?array $params = null, ?array $typeHints = null): true
    {
        $auth = $this->authorize($rules);
        $filters = $auth["filters"];
        if (empty($filters["filter"])) {
            return true;
        }
        $firstParam = true;
        $rowCount = 0;
        $model = new Model();
        $model->connect();
        $model->begin();
        $factory = new StatementFactory(PDOCompatible: true);
        $statement->returning[0] = "*";
        $str1 = $factory->createFromAST($statement, true)->getSql();
        $str = "create temporary table foo on commit drop as with updated_rows as (" . $str1 . ") select * from updated_rows";
        if ($params) {
            $typeFactory = new DefaultTypeConverterFactory();
            $convertedParameters = [];
            foreach ($params as $param) {
                $paramTmp = [];
                foreach ($param as $field => $value) {
                    $type = gettype($value);
                    if ($type == 'array' || $type == 'object') {
                        $nativeType = $typeHints[$field] ?? 'json';
                        try {
                            $nativeValue = $typeFactory->getConverterForTypeSpecification($nativeType)->output($value);
                        } catch (Exception) {
                            throw new GC2Exception("The value couldn't be parsed as $nativeType", 406, null, "VALUE_PARSE_ERROR");
                        }
                        $paramTmp[$field] = $nativeValue;
                    } elseif ($type == 'boolean') {
                        $nativeValue = $typeFactory->getConverterForTypeSpecification($type)->output($value);
                        $paramTmp[$field] = $nativeValue;
                    } else {
                        $paramTmp[$field] = $value;
                    }
                }
                $convertedParameters[] = $paramTmp;
            }
            $result = $model->prepare($str);
            foreach ($convertedParameters as $param) {
                // After first creation of tmp table we insert instead
                if (!$firstParam) {
                    $str = "with updated_rows as (" . $str1 . ") insert into foo select * from updated_rows";
                    $result = $model->prepare($str);
                }
                $result->execute($param);
                $firstParam = false;
                $rowCount += $result->rowCount();
            }
        } else {
            $result = $model->prepare($str);
            $this->execute($result);
            $rowCount += $result->rowCount();
        }

        $select = "select count(*) from foo where {$filters['filter']}";
        $res = $model->prepare($select);
        $this->execute($res);
        $row = $res->fetch();

        if ($rowCount == 0) {
            throw new Exception('COUNT 0 ERROR');
        }
        if ($rowCount > $row["count"]) {
            throw new Exception('LIMIT ERROR');
        }
        $model->rollback();
        return true;
    }

    /**
     * @param int|null $id
     * @return array
     * @throws GC2Exception
     */
    public function get(?int $id): array
    {
        $sql = "select * from settings.geofence";
        $params = [];
        if ($id != null) {
            $sql .= ' WHERE id = :id';
            $params[':id'] = $id;
        }
        $sql .= ' order by priority';
        $res = $this->prepare($sql);
        $this->execute($res, $params);
        $data = $this->fetchAll($res, "assoc");
        if (sizeof($data) == 0) {
            throw new GC2Exception("No rules", 404, null, 'RULE_NOT_FOUND');
        }
        return $data;
    }

    /**
     * @param array $data
     * @return array
     */
    public function create(array $data): array
    {
        $props = array_keys($data);
        $fields = implode(",", $props);
        $values = implode(",:", $props);

        if (isset($data['id'])) {
            if (sizeof($props) > 0) {
                $values = ":$values";
            }
            $sql = "insert into settings.geofence ($fields) values ($values) returning *";
        } else {

            if (sizeof($props) > 0) {
                $fields = ", $fields";
                $values = ", :$values";
            }
            $sql = "insert into settings.geofence (id $fields) values (default $values) returning *";
        }
        $res = $this->prepare($sql);
        $this->execute($res, $data);
        $response['success'] = true;
        $response['data'] = $this->fetchRow($res);
        return $response;
    }

    /**
     * @param array $data
     * @return void
     * @throws GC2Exception
     */
    public function update(string $id, array $data): int
    {
        $props = array_keys($data);
        if (sizeof($props) < 1) {
            throw new GC2Exception('Nothing to be set', 400);
        }
        $sets = [];
        foreach ($props as $prop) {
            if ($prop == "id") continue;
            if ($prop == "newId") {
                $sets[] = "id=:newId";
            } else {
                $sets[] = "$prop=:$prop";
            }
        }
        $setsStr = implode(",", $sets);
        $sql = "update settings.geofence set $setsStr where id=:id returning *";
        $res = $this->prepare($sql);
        $this->execute($res, [...$data, "id" => $id]);;
        if ($res->rowCount() == 0) {
            throw new GC2Exception("No rule with id", 404, null, 'RULE_NOT_FOUND');
        }
        return $res->fetchColumn();
    }

    /**
     * @param int $id
     * @return void
     * @throws GC2Exception
     */
    public function delete(int $id): void
    {
        $sql = "delete from settings.geofence where id=:id returning id";
        $res = $this->prepare($sql);
        $this->execute($res, ["id" => $id]);
        if ($res->rowCount() == 0) {
            throw new GC2Exception("No rule with id", 404, null, 'RULE_NOT_FOUND');
        }
    }

    /**
     */
    private function fillPlaceholders(string $str): string
    {
        $user = new UserModel($this->userFilter->userName, $this->postgisdb);
        try {
            $userData = (array)$user->getData()['data']['properties'];
            $newArr = [];
            foreach($userData as $key=>$value) {
                $newArr["{{{$key}}}"] = $value;
            }
            return strtr($str, $newArr);
        } catch (Exception) {
           return $str;
        }
    }
}