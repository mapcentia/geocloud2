<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;
use app\inc\UserFilter;
use Exception;
use PDOException;
use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_builder\StatementFactory;


/**
 * Class Geofencing
 * @package app\models
 */
class Geofence extends Model
{
    /**
     * @var UserFilter
     */
    private UserFilter $userFilter;
    public const ALLOW_ACCESS = "allow";
    public const DENY_ACCESS = "deny";
    public const LIMIT_ACCESS = "limit";

    /**
     * Geofencing constructor.
     * @param UserFilter|null $userFilter
     */
    public function __construct(UserFilter|null $userFilter)
    {
        parent::__construct();
        if ($userFilter) {
            $this->userFilter = $userFilter;
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
                ($this->userFilter->userName == $rule["username"] || $rule["username"] == "*") &&
                ($this->userFilter->layer == $rule["layer"] || $rule["layer"] == "*") &&
                ($this->userFilter->service == $rule["service"] || $rule["service"] == "*") &&
                ($this->userFilter->ipAddress == $rule["iprange"] || $rule["iprange"] == "*") &&
                ($this->userFilter->schema == $rule["schema"] || $rule["schema"] == "*") &&
                ($this->userFilter->request == $rule["request"] || $rule["request"] == "*")
            ) {
                if ($rule["access"] == self::LIMIT_ACCESS) {
                    $filters["read"] = $rule["read_filter"];
                    $filters["write"] = $rule["write_filter"];
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
     * @return bool
     * @throws Exception
     */
    public function postProcessQuery(Statement $statement, array $rules): bool
    {
        $filters = $this->authorize($rules)["filters"];
        if (empty($filters["write"])) {
            return true;
        }
        $model = new Model();
        $model->connect();
        $model->begin();
        $factory = new StatementFactory();
        $statement->returning[0] = "*";
        $str = $factory->createFromAST($statement)->getSql();
        $str = "create temporary table foo on commit drop as with updated_rows as (" . $str . ") select * from updated_rows";
        $result = $model->prepare($str);
        $result->execute();
        $select = "select * from foo where {$filters["write"]}";
        $res = $model->prepare($select);
        $res->execute();
        $count = $res->rowCount();
        if ($result->rowCount() > $count) {
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
        try {
            $res = $this->prepare($sql);
            $res->execute();
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
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
        try {
            $res->execute($data);
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['data'] = $this->fetchRow($res);
        return $response;
    }

    /**
     * @param array $data
     * @return array
     */
    public function update(array $data): array
    {
        $props = array_keys($data);
        if (!in_array("id", $props)) {
            $response['success'] = false;
            $response['message'] = "id is missing";
            $response['code'] = 400;
            return $response;
        }
        if (sizeof($props) < 2) {
            $response['success'] = false;
            $response['message'] = "Nothing to be set";
            $response['code'] = 400;
            return $response;
        }
        $sets = [];
        foreach ($props as $prop) {
            $sets[] = "$prop=:$prop";
        }
        $setsStr = implode(",", $sets);
        $sql = "update settings.geofence set $setsStr where id=:id returning *";
        $res = $this->prepare($sql);
        try {
            $res->execute($data);
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
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
        try {
            $res = $this->prepare($sql);
            $res->execute(["id" => $id]);
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['data'] = $this->fetchRow($res);
        return $response;
    }
}