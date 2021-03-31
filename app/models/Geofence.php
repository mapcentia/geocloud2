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
use PDOException;
use Generator;

use sad_spirit\pg_builder\Delete;
use sad_spirit\pg_builder\Insert;
use sad_spirit\pg_builder\nodes\range\UpdateOrDeleteTarget;
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\Update;
use sad_spirit\pg_builder\StatementFactory,
    sad_spirit\pg_builder\BlankWalker,
    sad_spirit\pg_builder\nodes\range\RelationReference;


class TableWalker extends BlankWalker
{
    /**
     * @var array<string>
     */
    private $relations;

    public function walkRelationReference(RelationReference $rangeItem): void
    {
//        $this->relations[] = (string)$rangeItem->name;
    }

    public function walkInsertStatement(Insert $statement): void
    {
//        $this->relations[] = (string)$statement->relation->relation;
    }

    public function walkUpdateStatement(Update $statement): void
    {
//        $this->relations[] = (string)$statement->relation->relation;
    }

    public function walkDeleteStatement(Delete $statement): void
    {
//        $this->relations[] = (string)$statement->relation->relation;
    }
    public function walkUpdateOrDeleteTarget(UpdateOrDeleteTarget $target): void
    {
        $this->relations[] = (string)$target->relation->relation;
    }

    /**
     * @return array<string>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }
}

/**
 * Class Geofencing
 * @package app\models
 */
class Geofence extends Model
{
    /**
     * @var UserFilter
     */
    private $userFilter;

    /**
     * Geofencing constructor.
     * @param UserFilter $userFilter
     */
    public function __construct(UserFilter $userFilter)
    {
        parent::__construct();
        $this->userFilter = $userFilter;
        $factory = new StatementFactory();
        $select = $factory->createFromString("WITH t AS (DELETE FROM foo) DELETE FROM bar");
        $walker = new TableWalker();
        $select->dispatch($walker);
//        print_r($walker->getRelations());
    }

    /**
     * @return array<mixed>
     */
    public function authorize(): array
    {
        $filters = [];
        $response = [];
        $rules = $this->getRules();
        foreach ($rules as $rule) {
            if (
                ($this->userFilter->userName == $rule["username"] || $rule["username"] == "*") &&
                ($this->userFilter->layer == $rule["layer"] || $rule["layer"] == "*") &&
                ($this->userFilter->service == $rule["service"] || $rule["service"] == "*") &&
                ($this->userFilter->ipAddress == $rule["ipaddress"] || $rule["ipaddress"] == null) &&
                ($this->userFilter->request == $rule["request"] || $rule["request"] == "*")
            ) {
                if ($rule["access"] == UserFilter::ALLOW) {
                    $filters = [];
                    $response["access"] = UserFilter::ALLOW;
                    break;

                } elseif ($rule["access"] == UserFilter::DENY) {
                    $filters = [];
                    $response["access"] = UserFilter::DENY;
                    break;

                } else {
                    $response["access"] = UserFilter::LIMIT;
                    $filters["read"] = $rule["read_filter"];
                    $filters["write"] = $rule["write_filter"];
                    $filters["read_spatial"] = $rule["read_spatial_filter"];
                    $filters["write_spatial"] = $rule["write_spatial_filter"];
                    break;
                }
            }
        }
        $response["filters"] = $filters;
        $response["success"] = true;
        return $response;
    }

    /**
     * @return Generator<array<mixed>>
     */
    private function getRules(): Generator
    {
        $gen = function () {
            $sql = "SELECT * FROM settings.geofence order by priority";
            $res = $this->prepare($sql);
            $res->execute();
            while ($row = $this->fetchRow($res)) {
                yield $row;
            }
        };
        return $gen();
    }
}