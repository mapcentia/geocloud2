<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;
use app\inc\UserFilter;
use Exception;
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
     * @param UserFilter $userFilter
     */
    public function __construct(UserFilter $userFilter)
    {
        parent::__construct();
        $this->userFilter = $userFilter;
    }

    /**
     * @param array<mixed> $rules
     * @return array<mixed>
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
                    $filters["read_spatial"] = $rule["read_spatial_filter"];
                    $filters["write_spatial"] = $rule["write_spatial_filter"];
                }
                $response["access"] = $rule["access"];
                break;
            }
        }
        $response["filters"] = $filters;
        $response["success"] = true;
        return $response;
    }

    /**
     * @param Statement $statement
     * @param Sql $model
     * @param array<string> $filters
     * @return array<mixed>
     * @throws Exception
     */
    public function postProcessQuery(Statement $statement, array $rules): bool
    {
        $filters = $this->authorize($rules)["filters"];
        if (sizeof($filters) == 0) {
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

}