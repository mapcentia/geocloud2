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
    private $rules;
    private $userName;
    private $service;
    private $request;
    private $ipAddress;

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
    private static function throwException(): void
    {
        throw new Exception('DENY');
    }

    /**
     * @throws Exception
     */
    public function walkSelectStatement(Select $statement)
    {
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
            if ($response["access"] == Geofence::DENY_ACCESS) {
                self::throwException();
            }
            if (!empty($response["filters"]["read"])) {
                $statement->where->and($response["filters"]["read"]);
            }
        }
        parent::walkSelectStatement($statement);
    }

    /**
     * @throws Exception
     */
    public function walkUpdateStatement(Update $statement)
    {
        foreach ($statement->from->getIterator() as $from) {
            $schema = $from->name->schema->value ?? "public";
            $relation = $from->name->relation->value;
            $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
            $geofence = new Geofence($userFilter);
            $response = $geofence->authorize($this->rules);
            if ($response["access"] == Geofence::DENY_ACCESS) {
                self::throwException();
            }
            if (!empty($response["filters"]["write"])) {
                $statement->where->and($response["filters"]["write"]);
            }
        }
        $schema = $statement->relation->relation->schema->value ?? "public";
        $relation = $statement->relation->relation->relation->value;
        $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
        $geofence = new Geofence($userFilter);
        $response = $geofence->authorize($this->rules);
        if (isset($response["access"]) && $response["access"] == Geofence::DENY_ACCESS) {
            self::throwException();
        }
        if (!empty($response["filters"]["write"])) {
            $statement->where->and($response["filters"]["write"]);
        }
        parent::walkUpdateStatement($statement);
    }

    /**
     * @throws Exception
     */
    public function walkDeleteStatement(Delete $statement): void
    {
        foreach ($statement->using->getIterator() as $using) {
            $schema = $using->name->schema->value ?? "public";
            $relation = $using->name->relation->value;
            $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
            $geofence = new Geofence($userFilter);
            $response = $geofence->authorize($this->rules);
            if ($response["access"] == Geofence::DENY_ACCESS) {
                self::throwException();
            }
            if (!empty($response["filters"]["write"])) {
                $statement->where->and($response["filters"]["write"]);
            }
        }
        $schema = $statement->relation->relation->schema->value ?? "public";
        $relation = $statement->relation->relation->relation->value;
        $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
        $geofence = new Geofence($userFilter);
        $response = $geofence->authorize($this->rules);
        if ($response["access"] == Geofence::DENY_ACCESS) {
            self::throwException();
        }
        if (!empty($response["filters"]["write"])) {
            $statement->where->and($response["filters"]["write"]);
        }


        parent::walkDeleteStatement($statement);
    }

    /**
     * @throws Exception
     */
    public function walkInsertStatement(Insert $statement)
    {
        $schema = $statement->relation->relation->schema->value ?? "public";
        $relation = $statement->relation->relation->relation->value;
        $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $schema, $relation);
        $geofence = new Geofence($userFilter);
        $response = $geofence->authorize($this->rules);
        if ($response["access"] == Geofence::DENY_ACCESS) {
            self::throwException();
        }
        if (!empty($statement->onConflict) && !empty($response["filters"]["read"])) {
            $statement->onConflict->where->and($response["filters"]["read"]);
        }
        parent::walkInsertStatement($statement); // TODO: Change the autogenerated stub
    }

    public function setRules(array $rules): void
    {
        $this->rules = $rules;
    }

}
