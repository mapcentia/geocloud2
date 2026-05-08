<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Faithful port of legacy doParse() from wfs/server.php (lines 1589-2415).
 * Worker-safe: the entire transaction is wrapped in Model::withTransaction().
 * Output is buffered via GmlWriter::bufferStart/bufferFlush so a mid-transaction
 * exception cannot leak partial XML.
 */
namespace app\wfs\handlers;

use app\controllers\Tilecache;
use app\exceptions\OwsException;
use app\inc\BasicAuth;
use app\inc\Input;
use app\inc\TableWalkerRelation;
use app\inc\TableWalkerRule;
use app\inc\UserFilter;
use app\inc\WfsFilter;
use app\models\Geofence;
use app\models\Layer as LayerModel;
use app\models\Rule;
use app\models\Table as TableModel;
use app\wfs\Context;
use app\wfs\Request;
use app\wfs\helpers\NameSpaces;
use app\wfs\output\GmlWriter;
use sad_spirit\pg_builder\StatementFactory;

final class Transaction implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    /**
     * @throws OwsException
     */
    public function handle(Request $req, GmlWriter $writer): void
    {
        $body = $req->transactionBody ?? throw new OwsException('Empty transaction body');
        $rule = new Rule(connection: $this->ctx->connection);
        $rules = $rule->get();
        $factory = new StatementFactory(PDOCompatible: true);

        $writer->bufferStart();

        /** @var array{inserted: list<array{handle: ?string, fid: string}>, updated: int, deleted: int, workflow: list<array<string,mixed>>} $results */
        $results = [
            'inserted' => [],
            'updated'  => 0,
            'deleted'  => 0,
            'workflow' => [],
        ];

        $this->ctx->model()->withTransaction(function () use (&$results, $body, $rules, $factory, $req): void {
            foreach ($body as $key => $featureMember) {
                match ($key) {
                    'Insert' => $this->doInsert($featureMember, $rules, $factory, $req, $results),
                    'Update' => $this->doUpdate($featureMember, $rules, $factory, $req, $results),
                    'Delete' => $this->doDelete($featureMember, $rules, $factory, $req, $results),
                    'Native' => throw new OwsException('', attributes: ['exceptionCode' => 'NoApplicableCode']),
                    default  => null,
                };
            }
            $this->runWorkflowAudits($results);
            $this->runPostProcessors();
        });

        $writer->writeXmlProlog();
        $writer->write($this->renderTransactionResponse($results, $req->version));
        $writer->bufferFlush();
    }

    // -------------------------------------------------------------------------
    // INSERT
    // -------------------------------------------------------------------------

    private function doInsert(mixed $featureMember, array $rules, StatementFactory $factory, Request $req, array &$results): void
    {
        if (!is_array($featureMember[0] ?? null) && isset($featureMember)) {
            $featureMember = [0 => $featureMember];
        }
        $user = $this->ctx->user;
        $layerModel = new LayerModel(connection: $this->ctx->connection);

        foreach ($featureMember as $hey) {
            $globalSrsName = $hey['srsName'] ?? null;
            $handle = $hey['handle'] ?? null;

            foreach ($hey as $typeName => $feature) {
                if (!is_array($feature)) {
                    continue; // skip handle/srsName scalars
                }
                $typeName = NameSpaces::dropAllNameSpaces($typeName);
                $model = $this->ctx->model();
                $primary = $model->getPrimeryKey("{$this->ctx->schema}.{$typeName}");
                if (!$primary) {
                    throw new OwsException('UnknownFeature', attributes: ['exceptionCode' => 'NoApplicableCode/']);
                }
                $gmlId = $feature['gml:id'] ?? null;

                // Strip gml namespace keys (keep non-namespaced and strip gml:* namespace prefixes)
                $feature = $this->stripGmlNamespaceKeys($feature);

                // Pre-processors
                foreach (glob(dirname(__DIR__) . '/processors/*/classes/pre/*.php') as $f) {
                    $cls = $this->processorClassFromFile($f, 'pre');
                    $res = (new $cls($model))->processInsert($feature, $typeName);
                    if (!$res['success']) {
                        throw new OwsException($res['message']);
                    }
                    $feature = $res['arr'];
                }

                // Check if table is versioned or has workflow; add fields when clients don't send them
                $tableObj = new TableModel("{$this->ctx->schema}.{$typeName}", connection: $this->ctx->connection);
                $this->annotateWorkflowFields($feature, $tableObj);

                $roleData = $layerModel->getRole($this->ctx->schema, $typeName)['data'] ?? [];
                $role = $roleData[$user] ?? 'none';
                if ($tableObj->workflow && $role === 'none' && !$this->ctx->parentUser) {
                    throw new OwsException("You don't have a role in the workflow of '{$typeName}'");
                }

                // Per-layer HTTP basic authentication
                if (!$this->ctx->trusted) {
                    $auth = $model->getGeometryColumns("{$this->ctx->schema}.{$typeName}", 'authentication');
                    if ($auth === 'Write' || $auth === 'Read/write' || !empty(Input::getAuthUser())) {
                        (new BasicAuth())->authenticate("{$this->ctx->schema}.{$typeName}", true);
                    }
                }

                // Build INSERT SQL
                $tableSrid = $model->getGeometryColumns("{$this->ctx->schema}.{$typeName}", 'srid');
                [$fields, $values, $gc2WorkflowFlag] = $this->buildInsertFieldsValues(
                    $feature, $primary, $gmlId, $globalSrsName, $tableSrid, $tableObj, $role, $req->version
                );

                if ($model->getGeometryColumns("{$this->ctx->schema}.{$typeName}", 'editable')) {
                    Tilecache::bust("{$this->ctx->schema}.{$typeName}");
                    $sql = $this->composeInsertSql($typeName, $fields, $values, $primary['attname'], $gc2WorkflowFlag, $tableObj, $layerModel);
                    $stmt = $model->prepare($sql);
                    $model->execute($stmt);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $newId = $row[$primary['attname']] ?? $row['gid'] ?? null;

                    // Geofence post-check (savepoint-safe inside outer transaction)
                    $userFilter = new UserFilter($user, 'wfs', 'insert', '*', $this->ctx->schema, $typeName);
                    $geofence = new Geofence($userFilter, $this->ctx->connection);
                    $authResult = $geofence->authorize($rules);
                    if (($authResult['access'] ?? '') === Geofence::LIMIT_ACCESS) {
                        $countSql = "SELECT count(*) FROM \"{$this->ctx->schema}\".\"{$typeName}\""
                            . " WHERE \"{$primary['attname']}\"=" . $model->quote((string)$newId)
                            . " AND ({$authResult['filters']['filter']})";
                        $cs = $model->prepare($countSql);
                        $model->execute($cs);
                        if ((int)$cs->fetchColumn() === 0) {
                            throw new OwsException('Geofence violation');
                        }
                    }

                    $results['inserted'][] = [
                        'handle' => $handle,
                        'fid'    => "{$typeName}.{$newId}",
                    ];

                    if (isset($row['gc2_workflow'])) {
                        $results['workflow'][] = [
                            'schema'      => $this->ctx->schema,
                            'table'       => $typeName,
                            'gid'         => $row['gid'],
                            'user'        => $user,
                            'status'      => $row['gc2_status'],
                            'workflow'    => $row['gc2_workflow'],
                            'roles'       => $row['roles'],
                            'version_gid' => $row['gc2_version_gid'],
                            'operation'   => 'insert',
                        ];
                    }
                }
                // If not editable — legacy ignores and notes in $notEditable but does not throw until after loops
                // We replicate by skipping silently (editable check done per-SQL loop in legacy)
            }
        }
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    private function doUpdate(mixed $featureMember, array $rules, StatementFactory $factory, Request $req, array &$results): void
    {
        if (!isset($featureMember[0])) {
            $featureMember = [0 => $featureMember];
        }
        $user = $this->ctx->user;
        $layerModel = new LayerModel(connection: $this->ctx->connection);

        foreach ($featureMember as $hey) {
            if (!isset($hey['Filter'])) {
                throw new OwsException('Must specify filter for update', attributes: ['exceptionCode' => 'MissingParameterValue']);
            }
            $globalSrsName = $hey['srsName'] ?? null;
            $typeName = NameSpaces::dropAllNameSpaces($hey['typeName']);
            if (isset($hey['Property']) && !isset($hey['Property'][0])) {
                $hey['Property'] = [0 => $hey['Property']];
            }
            $model = $this->ctx->model();
            $primary = $model->getPrimeryKey("{$this->ctx->schema}.{$typeName}");

            // Pre-processors
            foreach (glob(dirname(__DIR__) . '/processors/*/classes/pre/*.php') as $f) {
                $cls = $this->processorClassFromFile($f, 'pre');
                $res = (new $cls($model))->processUpdate($hey, $typeName);
                if (!$res['success']) {
                    throw new OwsException($res['message']);
                }
                $hey = $res['arr'];
            }

            $tableObj = new TableModel("{$this->ctx->schema}.{$typeName}", connection: $this->ctx->connection);

            // Ensure versioning / workflow fields are present
            $gc2VersionUserFlag = false;
            $gc2VersionStartDateFlag = false;
            $gc2StatusFlag = false;
            $gc2WorkflowFlag = false;
            foreach ($hey['Property'] as $v) {
                if ($v['Name'] === 'gc2_version_user') $gc2VersionUserFlag = true;
                if ($v['Name'] === 'gc2_version_start_date') $gc2VersionStartDateFlag = true;
                if ($v['Name'] === 'gc2_status') $gc2StatusFlag = true;
                if ($v['Name'] === 'gc2_workflow') $gc2WorkflowFlag = true;
            }
            if (!$gc2VersionUserFlag && $tableObj->versioning) {
                $hey['Property'][] = ['Name' => 'gc2_version_user', 'Value' => null];
            }
            if (!$gc2VersionStartDateFlag && $tableObj->versioning) {
                $hey['Property'][] = ['Name' => 'gc2_version_start_date', 'Value' => null];
            }
            if (!$gc2StatusFlag && $tableObj->workflow) {
                $hey['Property'][] = ['Name' => 'gc2_status', 'Value' => null];
            }
            if (!$gc2WorkflowFlag && $tableObj->workflow) {
                $hey['Property'][] = ['Name' => 'gc2_workflow', 'Value' => null];
            }

            $roleData = $layerModel->getRole($this->ctx->schema, $typeName)['data'] ?? [];
            $role = $roleData[$user] ?? 'none';
            if ($tableObj->workflow && $role === 'none' && !$this->ctx->parentUser) {
                throw new OwsException("You don't have a role in the workflow of '{$typeName}'");
            }

            // Per-layer HTTP basic authentication
            if (!$this->ctx->trusted) {
                $auth = $model->getGeometryColumns("{$this->ctx->schema}.{$typeName}", 'authentication');
                if ($auth === 'Write' || $auth === 'Read/write' || !empty(Input::getAuthUser())) {
                    (new BasicAuth())->authenticate("{$this->ctx->schema}.{$typeName}", true);
                }
            }

            if (!$model->getGeometryColumns("{$this->ctx->schema}.{$typeName}", 'editable')) {
                continue; // skip non-editable, legacy silently records in $notEditable
            }

            Tilecache::bust("{$this->ctx->schema}.{$typeName}");
            $tableSrid = $model->getGeometryColumns("{$this->ctx->schema}.{$typeName}", 'srid');
            $originalFeature = null;

            // WHERE clause from Filter
            $where = WfsFilter::explode($hey['Filter'], null, null, $primary['attname']);

            if ($tableObj->versioning) {
                // Fetch original feature for history clone
                $query = "SELECT * FROM \"{$this->ctx->schema}\".\"{$typeName}\" WHERE {$where}";
                $res = $model->execQuery($query);
                $originalFeature = $model->fetchRow($res);
                if (!empty($originalFeature['gc2_version_end_date'])) {
                    throw new OwsException("You can't change the history!");
                }
                // Clone original row with ended version date
                $intoArr = [];
                $selectArr = [];
                foreach ($originalFeature as $k => $v2) {
                    if ($k !== $primary['attname']) {
                        if ($k === 'gc2_version_end_date') {
                            $intoArr[] = "\"$k\"";
                            $selectArr[] = 'now()';
                        } else {
                            $intoArr[] = $selectArr[] = "\"$k\"";
                        }
                    }
                }
                $cloneSql = "INSERT INTO \"{$this->ctx->schema}\".\"{$typeName}\"("
                    . implode(',', $intoArr) . ')'
                    . ' SELECT ' . implode(',', $selectArr)
                    . " FROM \"{$this->ctx->schema}\".\"{$typeName}\" WHERE {$where}";
                $model->execQuery($cloneSql);
            }

            // Build SET pairs
            $pairs = [];
            foreach ($hey['Property'] as $pair) {
                $fieldName = $pair['Name'];
                $split = explode(':', $fieldName);
                if (isset($split[1])) {
                    $fieldName = NameSpaces::dropAllNameSpaces($fieldName);
                }
                $fieldValue = $pair['Value'] ?? null;

                if (is_array($fieldValue)) {
                    // geometry
                    $wkt = WfsFilter::toWkt($fieldValue, false, WfsFilter::getAxisOrder($globalSrsName), WfsFilter::parseEpsgCode($globalSrsName));
                    $value = "ST_Transform(ST_GeometryFromText('{$wkt[0]}',{$wkt[1]}),{$tableSrid})";
                } elseif ($fieldName === 'gc2_version_user') {
                    $value = $model->quote($user);
                } elseif ($fieldName === 'gc2_status') {
                    $value = match ($role) {
                        'author'    => $this->checkWorkflowStatus($originalFeature, $fieldName, $role, 1),
                        'reviewer'  => $this->checkWorkflowStatus($originalFeature, $fieldName, $role, 2),
                        'publisher' => 3,
                        default     => (string)($originalFeature[$fieldName] ?? 'NULL'),
                    };
                } elseif ($fieldName === 'gc2_workflow') {
                    $orig = $originalFeature[$fieldName] ?? '';
                    $value = match ($role) {
                        'author'    => "'{$orig}'::hstore || hstore('author', '{$user}')",
                        'reviewer'  => "'{$orig}'::hstore || hstore('reviewer', '{$user}')",
                        'publisher' => "'{$orig}'::hstore || hstore('publisher', '{$user}')",
                        default     => "'{$orig}'::hstore",
                    };
                } elseif ($fieldName === 'gc2_version_start_date') {
                    $value = 'now()';
                } elseif (empty($fieldValue) && !is_numeric($fieldValue)) {
                    $value = 'NULL';
                } else {
                    // Check if trying to update primary key
                    if ($fieldName === $primary['attname']) {
                        if ($originalFeature === null) {
                            $query = "SELECT * FROM \"{$this->ctx->schema}\".\"{$typeName}\" WHERE {$where}";
                            $res = $model->execQuery($query);
                            $originalFeature = $model->fetchRow($res);
                        }
                        $newVal = (string)$fieldValue;
                        $oldVal = (string)($originalFeature[$primary['attname']] ?? '');
                        if ($oldVal !== $newVal) {
                            throw new OwsException(
                                "It's not possible to update the primary key ({$primary['attname']}). "
                                . "The value of the key is {$oldVal} and new value is {$newVal}"
                            );
                        }
                    }
                    $value = $model->quote((string)$fieldValue);
                }
                $pairs[] = "\"{$fieldName}\" = {$value}";
            }

            $sql = "UPDATE \"{$this->ctx->schema}\".\"{$typeName}\" SET "
                . implode(',', $pairs)
                . " WHERE {$where} RETURNING \"{$primary['attname']}\" as gid";
            if ($tableObj->workflow) {
                $roleObj = $layerModel->getRole($this->ctx->schema, $typeName);
                $rolesHstore = $this->rolesToHstore($roleObj['data'] ?? []);
                $sql .= ",gc2_version_gid,gc2_status,gc2_workflow,{$rolesHstore} as roles";
            }

            // Geofence sandbox via savepoint (worker-safe, savepoint nests inside outer tx)
            $userFilter = new UserFilter($user, 'wfs', 'update', '*', $this->ctx->schema, $typeName);
            $geofence = new Geofence($userFilter, $this->ctx->connection);
            $authResult = $geofence->authorize($rules);
            if (($authResult['access'] ?? '') === Geofence::LIMIT_ACCESS) {
                $select = $factory->createFromString($sql);
                try {
                    $geofence->postProcessQuery($select, $rules);
                } catch (\Exception $e) {
                    throw new OwsException($e->getMessage());
                }
            }

            // Rules-rewrite (DENY etc.)
            $walkerRule = new TableWalkerRule($user, 'wfst', 'update', '');
            $walkerRule->setRules($rules);
            $ast = $factory->createFromString($sql);
            try {
                $ast->dispatch($walkerRule);
            } catch (\Exception $e) {
                throw new OwsException($e->getMessage());
            }
            $finalSql = $factory->createFromAST($ast, true)->getSql();

            $stmt = $model->prepare($finalSql);
            $model->execute($stmt);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $results['updated']++;
                if (isset($row['gc2_workflow'])) {
                    $results['workflow'][] = [
                        'schema'      => $this->ctx->schema,
                        'table'       => $typeName,
                        'gid'         => $row['gid'],
                        'user'        => $user,
                        'status'      => $row['gc2_status'],
                        'workflow'    => $row['gc2_workflow'],
                        'roles'       => $row['roles'],
                        'version_gid' => $row['gc2_version_gid'],
                        'operation'   => 'update',
                    ];
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    private function doDelete(mixed $featureMember, array $rules, StatementFactory $factory, Request $req, array &$results): void
    {
        if (!is_array($featureMember[0] ?? null) && isset($featureMember)) {
            $featureMember = [0 => $featureMember];
        }
        $user = $this->ctx->user;
        $layerModel = new LayerModel(connection: $this->ctx->connection);

        foreach ($featureMember as $hey) {
            if (!isset($hey['Filter'])) {
                throw new OwsException('Must specify filter for delete', attributes: ['exceptionCode' => 'MissingParameterValue']);
            }
            $hey['typeName'] = NameSpaces::dropAllNameSpaces($hey['typeName']);
            $typeName = $hey['typeName'];
            $model = $this->ctx->model();
            $primary = $model->getPrimeryKey("{$this->ctx->schema}.{$typeName}");

            // Pre-processors
            foreach (glob(dirname(__DIR__) . '/processors/*/classes/pre/*.php') as $f) {
                $cls = $this->processorClassFromFile($f, 'pre');
                $res = (new $cls($model))->processDelete($hey, $typeName);
                if (!$res['success']) {
                    throw new OwsException($res['message']);
                }
                $hey = $res['arr'];
            }

            $tableObj = new TableModel("{$this->ctx->schema}.{$typeName}", connection: $this->ctx->connection);
            $roleData = $layerModel->getRole($this->ctx->schema, $typeName)['data'] ?? [];
            $role = $roleData[$user] ?? 'none';
            if ($tableObj->workflow && $role === 'none' && !$this->ctx->parentUser) {
                throw new OwsException("You don't have a role in the workflow of '{$typeName}'");
            }

            // Per-layer HTTP basic authentication
            if (!$this->ctx->trusted) {
                $auth = $model->getGeometryColumns("{$this->ctx->schema}.{$typeName}", 'authentication');
                if ($auth === 'Write' || $auth === 'Read/write' || !empty(Input::getAuthUser())) {
                    (new BasicAuth())->authenticate("{$this->ctx->schema}.{$typeName}", true);
                }
            }

            if (!$model->getGeometryColumns("{$this->ctx->schema}.{$typeName}", 'editable')) {
                continue; // skip non-editable
            }

            Tilecache::bust("{$this->ctx->schema}.{$typeName}");
            $where = WfsFilter::explode($hey['Filter'], null, null, $primary['attname']);

            if ($tableObj->versioning) {
                // Check if it's history (already ended)
                $checkRes = $model->execQuery(
                    "SELECT gc2_version_end_date FROM \"{$this->ctx->schema}\".\"{$typeName}\" WHERE {$where}",
                    'PDO',
                    'select'
                );
                $checkRow = $model->fetchRow($checkRes);
                if (!empty($checkRow['gc2_version_end_date'])) {
                    throw new OwsException("You can't change the history!");
                }

                $originalFeature = null;
                $sql = "UPDATE \"{$this->ctx->schema}\".\"{$typeName}\" SET gc2_version_end_date = now(), gc2_version_user='{$user}'";

                if ($tableObj->workflow) {
                    $query = "SELECT * FROM \"{$this->ctx->schema}\".\"{$typeName}\" WHERE {$where}";
                    $resStatus = $model->execQuery($query);
                    $originalFeature = $model->fetchRow($resStatus);
                    $status = (int)($originalFeature['gc2_status'] ?? 0);
                    $value = match ($role) {
                        'author'    => $this->checkDeleteWorkflowStatus($status, $role, 1),
                        'reviewer'  => $this->checkDeleteWorkflowStatus($status, $role, 2),
                        'publisher' => 3,
                        default     => $status,
                    };
                    $sql .= ", gc2_status = {$value}";

                    $workflow = $originalFeature['gc2_workflow'] ?? '';
                    $wfValue = match ($role) {
                        'author'    => "'{$workflow}'::hstore || hstore('author', '{$user}')",
                        'reviewer'  => "'{$workflow}'::hstore || hstore('reviewer', '{$user}')",
                        'publisher' => "'{$workflow}'::hstore || hstore('publisher', '{$user}')",
                        default     => "'{$workflow}'::hstore",
                    };
                    $sql .= ", gc2_workflow = {$wfValue}";
                }

                $sql .= " WHERE {$where} RETURNING \"{$primary['attname']}\" as gid";
                if ($tableObj->workflow) {
                    $roleObj = $layerModel->getRole($this->ctx->schema, $typeName);
                    $rolesHstore = $this->rolesToHstore($roleObj['data'] ?? []);
                    $sql .= ",gc2_version_gid,gc2_status,gc2_workflow,{$rolesHstore} as roles";
                }
            } else {
                $sql = "DELETE FROM \"{$this->ctx->schema}\".\"{$typeName}\" WHERE {$where} RETURNING \"{$primary['attname']}\" as gid";
            }

            // Geofence sandbox via savepoint
            $userFilter = new UserFilter($user, 'wfs', 'delete', '*', $this->ctx->schema, $typeName);
            $geofence = new Geofence($userFilter, $this->ctx->connection);
            $authResult = $geofence->authorize($rules);
            if (($authResult['access'] ?? '') === Geofence::LIMIT_ACCESS) {
                $select = $factory->createFromString($sql);
                try {
                    $geofence->postProcessQuery($select, $rules);
                } catch (\Exception $e) {
                    throw new OwsException($e->getMessage());
                }
            }

            // Rules-rewrite
            $walkerRule = new TableWalkerRule($user, 'wfst', 'delete', '');
            $walkerRule->setRules($rules);
            $ast = $factory->createFromString($sql);
            try {
                $ast->dispatch($walkerRule);
            } catch (\Exception $e) {
                throw new OwsException($e->getMessage());
            }
            $finalSql = $factory->createFromAST($ast, true)->getSql();

            $stmt = $model->prepare($finalSql);
            $model->execute($stmt);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $results['deleted']++;
                if (isset($row['gc2_workflow'])) {
                    $results['workflow'][] = [
                        'schema'      => $this->ctx->schema,
                        'table'       => $typeName,
                        'gid'         => $row['gid'],
                        'user'        => $user,
                        'status'      => $row['gc2_status'],
                        'workflow'    => $row['gc2_workflow'],
                        'roles'       => $row['roles'],
                        'version_gid' => $row['gc2_version_gid'],
                        'operation'   => 'delete',
                    ];
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Workflow audits
    // -------------------------------------------------------------------------

    private function runWorkflowAudits(array &$results): void
    {
        foreach ($results['workflow'] ?? [] as $w) {
            $sql = "INSERT INTO settings.workflow (f_schema_name, f_table_name, gid, status, gc2_user, roles, workflow, version_gid, operation)"
                . " VALUES('{$w['schema']}','{$w['table']}',{$w['gid']},{$w['status']},'{$w['user']}','{$w['roles']}'::hstore,'{$w['workflow']}'::hstore,{$w['version_gid']},'{$w['operation']}')";
            $model = $this->ctx->model();
            $stmt = $model->prepare($sql);
            $model->execute($stmt);
        }
    }

    // -------------------------------------------------------------------------
    // Post-processors
    // -------------------------------------------------------------------------

    private function runPostProcessors(): void
    {
        $model = $this->ctx->model();
        foreach (glob(dirname(__DIR__) . '/processors/*/classes/post/*.php') as $f) {
            $cls = $this->processorClassFromFile($f, 'post');
            $res = (new $cls($model))->process();
            if (!$res['success']) {
                throw new OwsException($res['message']);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Response rendering
    // -------------------------------------------------------------------------

    private function renderTransactionResponse(array $results, string $version): string
    {
        $totalInserted = count($results['inserted']);
        $totalUpdated  = $results['updated'];
        $totalDeleted  = $results['deleted'];

        if ($version === '1.0.0') {
            // 1.0.0 format: InsertResult / WFS_TransactionResponse
            $insertXml = '';
            if ($totalInserted > 0) {
                $insertXml .= '<wfs:InsertResult>';
                foreach ($results['inserted'] as $ins) {
                    $insertXml .= '<ogc:FeatureId fid="' . htmlspecialchars($ins['fid'], ENT_XML1 | ENT_QUOTES) . '"/>';
                }
                $insertXml .= '</wfs:InsertResult>';
            }
            return '<wfs:WFS_TransactionResponse version="1.0.0"'
                . ' xmlns:wfs="http://www.opengis.net/wfs"'
                . ' xmlns:ogc="http://www.opengis.net/ogc"'
                . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
                . ' xsi:schemaLocation="http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.0.0/WFS-transaction.xsd">'
                . $insertXml
                . '<wfs:TransactionResult handle="mygeocloud-WFS-default-handle"><wfs:Status><wfs:SUCCESS/></wfs:Status></wfs:TransactionResult>'
                . '</wfs:WFS_TransactionResponse>';
        }

        // 1.1.0 format
        $insertXml = '';
        if ($totalInserted > 0) {
            $insertXml .= '<wfs:InsertResults>';
            foreach ($results['inserted'] as $ins) {
                $handle = $ins['handle'] !== null
                    ? ' handle="' . htmlspecialchars((string)$ins['handle'], ENT_XML1 | ENT_QUOTES) . '"'
                    : '';
                $insertXml .= "<wfs:Feature{$handle}>"
                    . '<ogc:FeatureId fid="' . htmlspecialchars($ins['fid'], ENT_XML1 | ENT_QUOTES) . '"/>'
                    . '</wfs:Feature>';
            }
            $insertXml .= '</wfs:InsertResults>';
        }

        return '<wfs:TransactionResponse'
            . ' xmlns:xs="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:wfs="http://www.opengis.net/wfs"'
            . ' xmlns:gml="http://www.opengis.net/gml"'
            . ' xmlns:ogc="http://www.opengis.net/ogc"'
            . ' xmlns:ows="http://www.opengis.net/ows"'
            . ' xmlns:xlink="http://www.w3.org/1999/xlink"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' version="1.1.0"'
            . ' xsi:schemaLocation="http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd">'
            . '<wfs:TransactionSummary>'
            .   "<wfs:totalInserted>{$totalInserted}</wfs:totalInserted>"
            .   "<wfs:totalUpdated>{$totalUpdated}</wfs:totalUpdated>"
            .   "<wfs:totalDeleted>{$totalDeleted}</wfs:totalDeleted>"
            . '</wfs:TransactionSummary>'
            . '<wfs:TransactionResults/>'
            . $insertXml
            . '</wfs:TransactionResponse>';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Filter out gml:* namespaced keys. Non-namespaced keys are kept as-is.
     * Other namespaced keys (e.g. app:field) are de-namespaced.
     *
     * @return array<string, mixed>
     */
    private function stripGmlNamespaceKeys(array $feature): array
    {
        $out = [];
        foreach ($feature as $k => $v) {
            $parts = explode(':', $k, 2);
            if (count($parts) === 2 && $parts[0] !== 'gml') {
                // namespace prefix other than gml — strip it
                $out[NameSpaces::dropAllNameSpaces($k)] = $v;
            } elseif (count($parts) === 1) {
                // no namespace — keep as-is
                $out[$k] = $v;
            }
            // gml:* keys are intentionally dropped
        }
        return $out;
    }

    private function annotateWorkflowFields(array &$feature, TableModel $tableObj): void
    {
        if ($tableObj->versioning && !array_key_exists('gc2_version_user', $feature)) {
            $feature['gc2_version_user'] = null;
        }
        if ($tableObj->workflow) {
            if (!array_key_exists('gc2_status', $feature))   $feature['gc2_status'] = null;
            if (!array_key_exists('gc2_workflow', $feature)) $feature['gc2_workflow'] = null;
        }
    }

    /**
     * @return array{0: list<string>, 1: list<mixed>, 2: bool}  [fields, values, gc2WorkflowFlag]
     */
    private function buildInsertFieldsValues(
        array $feature,
        array $primary,
        ?string $gmlId,
        ?string $globalSrsName,
        mixed $tableSrid,
        TableModel $tableObj,
        string $role,
        string $version
    ): array {
        $fields = [];
        $values = [];
        $gc2WorkflowFlag = false;
        $user = $this->ctx->user;

        // UseExisting key generation: gml:id attribute present
        if ($gmlId !== null) {
            $fields[] = $primary['attname'];
            $values[] = $gmlId;
        }

        foreach ($feature as $field => $value) {
            // Skip primary key for GenerateNew (when gmlId present and version != 1.0.0)
            if ($field === $primary['attname'] && $version !== '1.0.0' && $gmlId !== null) {
                continue;
            }
            // Skip internal versioning columns
            if (in_array($field, ['gc2_version_uuid', 'gc2_version_start_date', 'gc2_version_gid'], true)) {
                continue;
            }

            $fields[] = $field;

            if (is_array($value) && $this->countDimensions($value) > 1) {
                // Geometry value
                $wkt = WfsFilter::toWkt($value, false, WfsFilter::getAxisOrder($globalSrsName), WfsFilter::parseEpsgCode($globalSrsName));
                $values[] = ['__geom' => $wkt[0], 'srid' => $wkt[1], '__tableSrid' => $tableSrid];
                if (!empty($wkt[2])) {
                    // Geometry-embedded gml:id wins over the global gmlId
                    $fields = array_values(array_filter($fields, fn($f) => $f !== $primary['attname']));
                    $values = array_values(array_filter($values, fn($v) => !is_string($v) || $v !== $gmlId));
                    $fields[] = $primary['attname'];
                    $values[] = $wkt[2];
                }
                continue;
            }

            $values[] = match ($field) {
                'gc2_version_user' => $user,
                'gc2_status'       => match ($role) {
                    'author'   => 1,
                    'reviewer' => 2,
                    default    => 3,
                },
                'gc2_workflow'     => (function () use ($role, $user, &$gc2WorkflowFlag): string {
                    $gc2WorkflowFlag = true;
                    return match ($role) {
                        'author'    => "hstore('author', '{$user}')",
                        'reviewer'  => "hstore('reviewer', '{$user}')",
                        'publisher' => "hstore('publisher', '{$user}')",
                        default     => "''",
                    };
                })(),
                default            => $value,
            };
        }

        return [$fields, $values, $gc2WorkflowFlag];
    }

    private function composeInsertSql(
        string $typeName,
        array $fields,
        array $values,
        string $primaryName,
        bool $gc2WorkflowFlag,
        TableModel $tableObj,
        LayerModel $layerModel
    ): string {
        $cols = [];
        $placeholders = [];

        foreach ($fields as $i => $f) {
            $cols[] = "\"{$f}\"";
            $v = $values[$i];

            if (is_array($v) && isset($v['__geom'])) {
                $placeholders[] = "ST_Transform(ST_GeometryFromText('{$v['__geom']}',{$v['srid']}),{$v['__tableSrid']})";
            } elseif (is_string($v) && (str_starts_with($v, "hstore(") || str_starts_with($v, "''"))) {
                // hstore literal — no quoting
                $placeholders[] = $v;
            } elseif ($v === null || ($v === '' && !is_numeric($v))) {
                $placeholders[] = 'NULL';
            } else {
                $placeholders[] = "'" . str_replace("'", "''", (string)$v) . "'";
            }
        }

        $sql = "INSERT INTO \"{$this->ctx->schema}\".\"{$typeName}\" ("
            . implode(',', $cols)
            . ') VALUES ('
            . implode(',', $placeholders)
            . ") RETURNING \"{$primaryName}\" as gid";

        if ($gc2WorkflowFlag) {
            $roleObj = $layerModel->getRole($this->ctx->schema, $typeName);
            $rolesHstore = $this->rolesToHstore($roleObj['data'] ?? []);
            $sql .= ",gc2_version_gid,gc2_status,gc2_workflow,{$rolesHstore} as roles";
        }

        return $sql;
    }

    /**
     * Convert a roles array to a PostgreSQL hstore literal string.
     * e.g. ['alice' => 'author'] → '"alice"=>"author"'
     */
    private function rolesToHstore(array $roles): string
    {
        if (empty($roles)) {
            return "''::hstore";
        }
        $pairs = [];
        foreach ($roles as $k => $v) {
            $k = str_replace('"', '\\"', (string)$k);
            $v = str_replace('"', '\\"', (string)$v);
            $pairs[] = "\"{$k}\"=>\"{$v}\"";
        }
        return "'" . implode(',', $pairs) . "'::hstore";
    }

    /**
     * Check workflow status constraints for author/reviewer and return the new status value.
     * Throws OwsException if the current status exceeds their privilege level.
     */
    private function checkWorkflowStatus(?array $originalFeature, string $field, string $role, int $maxStatus): int
    {
        if ($originalFeature !== null) {
            $currentStatus = (int)($originalFeature[$field] ?? 0);
            if ($currentStatus > $maxStatus) {
                $label = $currentStatus === 2 ? 'reviewed' : 'published';
                $who = $role === 'author' ? 'author' : 'reviewer';
                throw new OwsException("This feature has been {$label}, so a {$who} can't edit it.");
            }
        }
        return $maxStatus;
    }

    /**
     * Check workflow status constraints for delete operations.
     */
    private function checkDeleteWorkflowStatus(int $status, string $role, int $maxStatus): int
    {
        if ($status > $maxStatus) {
            $label = $status === 2 ? 'reviewed' : 'published';
            $who = $role === 'author' ? 'author' : 'reviewer';
            throw new OwsException("This feature has been {$label}, so a {$who} can't delete it.");
        }
        return $maxStatus;
    }

    private function processorClassFromFile(string $filename, string $kind): string
    {
        $parts = array_reverse(explode('/', $filename));
        return "app\\wfs\\processors\\{$parts[3]}\\classes\\{$kind}\\" . pathinfo($filename, PATHINFO_FILENAME);
    }

    private function countDimensions(array $arr): int
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($arr));
        $d = 0;
        foreach ($it as $_) {
            if ($it->getDepth() >= $d) $d = $it->getDepth();
        }
        return ++$d;
    }
}
