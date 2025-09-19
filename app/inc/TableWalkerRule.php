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
use sad_spirit\pg_builder\Merge;
use sad_spirit\pg_builder\nodes\range\RelationReference;
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

    private const string DEFAULT_SCHEMA = "public";

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
        global $relations;
        $this->request = "select";
        $relations = [];
        foreach ($statement->from->getIterator() as $from) {

            $getLeft = function ($from, &$relations) use (&$getRight, &$getLeft): void {
                if (isset($from->right) && $from->right instanceof RelationReference) {
                    $getRight($from, $relations);
                }
                if ($from->left instanceof RelationReference) {
                    $relations[] = [
                        "schema" => ($from->left->name->schema->value ?? self::DEFAULT_SCHEMA),
                        "table" => $from->left->name->relation->value
                    ];
                } else {
                    $getLeft($from->left, $relations);
                }
            };
            $getRight = function ($from, &$relations) use (&$getRight): void {
                if ($from->right instanceof RelationReference) {
                    $relations[] = [
                        "schema" => ($from->right->name->schema->value ?? self::DEFAULT_SCHEMA),
                        "table" => $from->right->name->relation->value
                    ];
                } else {
                    $getRight($from->right, $relations);
                }
            };
            // Check if we have a join
            if (isset($from->left)) {
                $getLeft($from, $relations);
            } else {
                // A sub-select doesn't have name
                if (!isset($from->name)) {
                    continue;
                }
                $relations[] = [
                    "schema" => ($from->name->schema->value ?? self::DEFAULT_SCHEMA),
                    "table" => $from->name->relation->value
                ];
            }
        }
        foreach ($relations as $relation) {
            $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $relation["schema"], $relation["table"]);
            $geofence = new Geofence($userFilter);
            $response = $geofence->authorize($this->rules);
            if (isset($response["access"]) && $response["access"] == Geofence::DENY_ACCESS) {
                $this->throwException($relation["schema"] . "." . $relation["table"]);
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
            $schema = $from->name->schema->value ?? self::DEFAULT_SCHEMA;
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
        $schema = $statement->relation->relation->schema->value ?? self::DEFAULT_SCHEMA;
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
            // A sub-select doesn't have name
            if (!isset($using->name)) {
                continue;
            }
            $schema = $using->name->schema->value ?? self::DEFAULT_SCHEMA;
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
        $schema = $statement->relation->relation->schema->value ?? self::DEFAULT_SCHEMA;
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
        $schema = $statement->relation->relation->schema->value ?? self::DEFAULT_SCHEMA;
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
        parent::walkInsertStatement($statement);
    }
    public function walkMergeStatement(Merge $statement): void
    {
        // Handle USING sources like SELECT: authorize SELECT and apply filters to ON condition
        // Collect relations from USING, including joins, skip sub-selects
        $relations = [];
        $using = $statement->using;
        $getLeft = function ($from, &$relations) use (&$getRight, &$getLeft): void {
            if (isset($from->right) && $from->right instanceof RelationReference) {
                $getRight($from, $relations);
            }
            if ($from->left instanceof RelationReference) {
                $relations[] = [
                    "schema" => ($from->left->name->schema->value ?? self::DEFAULT_SCHEMA),
                    "table" => $from->left->name->relation->value
                ];
            } else {
                $getLeft($from->left, $relations);
            }
        };
        $getRight = function ($from, &$relations) use (&$getRight): void {
            if ($from->right instanceof RelationReference) {
                $relations[] = [
                    "schema" => ($from->right->name->schema->value ?? self::DEFAULT_SCHEMA),
                    "table" => $from->right->name->relation->value
                ];
            } else {
                $getRight($from->right, $relations);
            }
        };
        if (isset($using->left)) {
            $getLeft($using, $relations);
        } else {
            if (isset($using->name)) {
                $relations[] = [
                    "schema" => ($using->name->schema->value ?? self::DEFAULT_SCHEMA),
                    "table" => $using->name->relation->value
                ];
            }
        }
        foreach ($relations as $relation) {
            $this->request = "select";
            $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $relation["schema"], $relation["table"]);
            $geofence = new Geofence($userFilter);
            $response = $geofence->authorize($this->rules);
            if (isset($response["access"]) && $response["access"] == Geofence::DENY_ACCESS) {
                $this->throwException($relation["schema"] . "." . $relation["table"]);
            }
            if (!empty($response["filters"]["filter"])) {
                $filter = $response["filters"]["filter"];
                $parser = $statement->getParser();
                if (null === $parser) {
                    // Fallback: wrap via StatementFactory default parser
                    $parser = (new \sad_spirit\pg_builder\StatementFactory())->getParser();
                }
                $filterExpr = $parser->parseExpression($filter);
                if (null !== $statement->on) {
                    $left = $statement->on;
                    $builder = (new \sad_spirit\pg_builder\StatementFactory())->getBuilder();
                    $leftSql = $left->dispatch($builder);
                    $combined = $parser->parseExpression('(' . $leftSql . ') AND (' . $filter . ')');
                    $statement->setOn($combined);
                } else {
                    $statement->setOn($filterExpr);
                }
            }
        }

        // Handle target relation per WHEN actions: UPDATE / DELETE / INSERT
        $targetSchema = $statement->relation->relation->schema->value ?? self::DEFAULT_SCHEMA;
        $targetTable = $statement->relation->relation->relation->value;

        foreach ($statement->when as $whenClause) {
            // Determine action type and corresponding request string
            $request = null;
            $action = $whenClause->action ?? null;
            if ($action) {
                if ($action instanceof \sad_spirit\pg_builder\nodes\merge\MergeUpdate) {
                    $request = 'update';
                } elseif ($action instanceof \sad_spirit\pg_builder\nodes\merge\MergeDelete) {
                    $request = 'delete';
                } elseif ($action instanceof \sad_spirit\pg_builder\nodes\merge\MergeInsert) {
                    $request = 'insert';
                }
            }
            if (null === $request) {
                continue;
            }

            $this->request = $request;
            $userFilter = new UserFilter($this->userName, $this->service, $this->request, $this->ipAddress, $targetSchema, $targetTable);
            $geofence = new Geofence($userFilter);
            $response = $geofence->authorize($this->rules);
            if (isset($response["access"]) && $response["access"] == Geofence::DENY_ACCESS) {
                $this->throwException($targetSchema . "." . $targetTable);
            }
            if (!empty($response["filters"]["filter"])) {
                $filter = $response["filters"]["filter"];
                $parser = $statement->getParser();
                if (null === $parser) {
                    $parser = (new \sad_spirit\pg_builder\StatementFactory())->getParser();
                }
                $filterExpr = $parser->parseExpression($filter);
                if (null !== $whenClause->condition) {
                    $left = $whenClause->condition;
                    $builder = (new \sad_spirit\pg_builder\StatementFactory())->getBuilder();
                    $leftSql = $left->dispatch($builder);
                    $combined = $parser->parseExpression('(' . $leftSql . ') AND (' . $filter . ')');
                    $whenClause->setCondition($combined);
                } else {
                    $whenClause->setCondition($filterExpr);
                }
            }
        }

        parent::walkMergeStatement($statement);
    }

    public function setRules(array $rules): void
    {
        $this->rules = $rules;
    }

}
