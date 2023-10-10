<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\models\Geofence;
use Exception;
use sad_spirit\pg_builder\Delete;
use sad_spirit\pg_builder\Insert;
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\Update;
use sad_spirit\pg_builder\BlankWalker;


class TableWalkerRule extends BlankWalker
{
    private array $rules;
    private string $userName;
    private string $service;
    private string $request;
    private string $ipAddress;

    public function __construct($userName, $service, $request, $ipAddress)
    {
        $this->userName = $userName;
        $this->service = $service;
        $this->request = $request;
        $this->ipAddress = $ipAddress;
    }

    /**
     * @throws Exception
     */
    private function throwException(string $rel = ""): void
    {
        throw new Exception("DENY for '$this->request' on '$rel' for '$this->userName' using '$this->service'");
    }

    /**
     * @throws Exception
     */
    public function walkSelectStatement(Select $statement): void
    {
        $this->request = "select";
        foreach ($statement->from->getIterator() as $from) {
            // A sub-select doesn't have name
            if (!isset($from->name)) {
                continue;
            }
            $schema = $from->name->schema->value ?? "public";
            $relation = $from->name->relation->value;
            $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
            $geofence = new Geofence($userFilter);
            $response = $geofence->authorize($this->rules);
            if (isset($response["access"]) && $response["access"] == Geofence::DENY_ACCESS) {
                $this->throwException($schema . "." . $relation);
            }
            if (!empty($response["filters"]["filter"])) {
                $statement->where->and($response["filters"]["filter"]);
            }
        }
        parent::walkSelectStatement($statement);
    }

    /**
     * @throws Exception
     */
    public function walkUpdateStatement(Update $statement): void
    {
        $this->request = "update";
        foreach ($statement->from->getIterator() as $from) {
            $schema = $from->name->schema->value ?? "public";
            $relation = $from->name->relation->value;
            $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
            $geofence = new Geofence($userFilter);
            $response = $geofence->authorize($this->rules);
            if ($response["access"] == Geofence::DENY_ACCESS) {
                $this->throwException($schema . "." . $relation);
            }
            if (!empty($response["filters"]["filter"])) {
                $statement->where->and($response["filters"]["filter"]);
            }
        }
        $schema = $statement->relation->relation->schema->value ?? "public";
        $relation = $statement->relation->relation->relation->value;
        $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
        $geofence = new Geofence($userFilter);
        $response = $geofence->authorize($this->rules);
        if (isset($response["access"]) && $response["access"] == Geofence::DENY_ACCESS) {
            $this->throwException($schema . "." . $relation);
        }
        if (!empty($response["filters"]["filter"])) {
            $statement->where->and($response["filters"]["filter"]);
        }
        parent::walkUpdateStatement($statement);
    }

    /**
     * @throws Exception
     */
    public function walkDeleteStatement(Delete $statement): void
    {
        $this->request = "delete";
        foreach ($statement->using->getIterator() as $using) {
            $schema = $using->name->schema->value ?? "public";
            $relation = $using->name->relation->value;
            $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
            $geofence = new Geofence($userFilter);
            $response = $geofence->authorize($this->rules);
            if ($response["access"] == Geofence::DENY_ACCESS) {
                $this->throwException($schema . "." . $relation);
            }
            if (!empty($response["filters"]["filter"])) {
                $statement->where->and($response["filters"]["filter"]);
            }
        }
        $schema = $statement->relation->relation->schema->value ?? "public";
        $relation = $statement->relation->relation->relation->value;
        $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
        $geofence = new Geofence($userFilter);
        $response = $geofence->authorize($this->rules);
        if ($response["access"] == Geofence::DENY_ACCESS) {
            $this->throwException($schema . "." . $relation);
        }
        if (!empty($response["filters"]["filter"])) {
            $statement->where->and($response["filters"]["filter"]);
        }
        parent::walkDeleteStatement($statement);
    }

    /**
     * @throws Exception
     */
    public function walkInsertStatement(Insert $statement): void
    {
        $this->request = "insert";
        $schema = $statement->relation->relation->schema->value ?? "public";
        $relation = $statement->relation->relation->relation->value;
        $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
        $geofence = new Geofence($userFilter);
        $response = $geofence->authorize($this->rules);
        if ($response["access"] == Geofence::DENY_ACCESS) {
            $this->throwException($schema . "." . $relation);
        }
        if (!empty($statement->onConflict) && !empty($response["filters"]["filter"])) {
            $statement->onConflict->where->and($response["filters"]["filter"]);
        }
        parent::walkInsertStatement($statement); // TODO: Change the autogenerated stub
    }

    public function setRules(array $rules): void
    {
        $this->rules = $rules;
    }

}
