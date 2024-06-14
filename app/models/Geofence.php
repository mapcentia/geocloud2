<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Util;
use app\inc\Model;
use app\inc\UserFilter;
use app\models\User as UserModel;
use Exception;
use PDOException;
use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_builder\StatementFactory;
use sad_spirit\pg_wrapper\converters\DefaultTypeConverterFactory;


/**
 * Class Geofencing
 * @package app\models
 */
class Geofence extends Model
{
    private UserFilter $userFilter;
    public const string ALLOW_ACCESS = "allow";
    public const string DENY_ACCESS = "deny";
    public const string LIMIT_ACCESS = "limit";

    /**
     * Geofencing constructor.
     * @param UserFilter|null $userFilter
     */
    public function __construct(UserFilter|null $userFilter)
    {
        parent::__construct();
        if ($userFilter) {
            $this->userFilter = $userFilter;
            $this->userFilter->ipAddress = Util::clientIp();
        }
    }

    /**
     * @param array $rules
     * @return array
     * @throws GC2Exception
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
    public function postProcessQuery(Statement $statement, array $rules, array $params = null, array $typeHints = null): true
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
                        } catch (\Exception) {
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
            $result->execute();
            $rowCount += $result->rowCount();
        }

        $select = "select count(*) from foo where {$filters['filter']}";
        $res = $model->prepare($select);
        $res->execute();
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
     * @return array
     */
    public function get(): array
    {
        $sql = "select * from settings.geofence order by id";
        $res = $this->prepare($sql);
        $res->execute();
        $response['success'] = true;
        $response['data'] = $this->fetchAll($res, "assoc");
        return $response;
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
        if (sizeof($props) > 1) {
            $fields = ", $fields";
            $values = ", :$values";
        }
        $sql = "insert into settings.geofence (id $fields) values (default $values) returning *";
        $res = $this->prepare($sql);
        $res->execute($data);
        $response['success'] = true;
        $response['data'] = $this->fetchRow($res);
        return $response;
    }

    /**
     * @param array $data
     * @return array
     * @throws GC2Exception
     */
    public function update(array $data): array
    {
        $props = array_keys($data);
        if (!in_array("id", $props)) {
            throw new GC2Exception('Id is missing', 400);
        }
        if (sizeof($props) < 2) {
            throw new GC2Exception('Nothing to be set', 400);
        }
        $sets = [];
        foreach ($props as $prop) {
            $sets[] = "$prop=:$prop";
        }
        $setsStr = implode(",", $sets);
        $sql = "update settings.geofence set $setsStr where id=:id returning *";
        $res = $this->prepare($sql);
        $res->execute($data);
        $response['success'] = true;
        $response['data'] = $this->fetchRow($res);
        return $response;
    }

    /**
     * @param int $id
     * @return array
     */
    public function delete(int $id): array
    {
        $sql = "delete from settings.geofence where id=:id returning id";
        $res = $this->prepare($sql);
        $res->execute(["id" => $id]);
        $response['success'] = true;
        $response['data'] = $this->fetchRow($res);
        return $response;
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