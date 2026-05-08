<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Faithful port of legacy doQuery("Select") + doSelect() from wfs/server.php.
 * Worker-safe: cursor is always released via Model::withTransaction().
 */
namespace app\wfs\handlers;

use app\exceptions\OwsException;
use app\inc\TableWalkerRule;
use app\inc\WfsFilter;
use app\models\Layer;
use app\models\Rule;
use app\models\Table as TableModel;
use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;
use PDOException;
use sad_spirit\pg_builder\StatementFactory;

final class GetFeature implements HandlerInterface
{
    private const SPECIAL_CHARS = "/['^£\$%&*()}{@#~?><>,|=+¬]/";
    private const FEATURE_LIMIT = 1000000;

    public function __construct(private readonly Context $ctx) {}

    /**
     * @throws OwsException
     */
    public function handle(Request $req, GmlWriter $writer): void
    {
        $srs = $this->ctx->srs ?? $req->srs;
        if (!$srs) {
            throw new OwsException('You need to specify a srid in the URL.');
        }
        if (empty($req->typeNames)) {
            throw new OwsException(
                'Missing typeName',
                attributes: ['exceptionCode' => 'MissingParameterValue', 'locator' => 'typeName']
            );
        }

        $writer->writeXmlProlog();

        // Per-table state gathered in the build-SQL pass
        /** @var array<string, array{sql: string, from: string, sql2: string|null, tableObj: TableModel}> $perTable */
        $perTable = [];
        foreach ($req->typeNames as $table) {
            $perTable[$table] = $this->buildTableState($req, $table, $srs);
        }

        // Count features (use maxFeatures directly if provided, else COUNT)
        $totalCount = $this->countFeatures($req, $perTable, $srs);

        // Emit FeatureCollection opening tag with numberOfFeatures
        $countForAttr = ($req->version === '1.1.0') ? $totalCount : null;
        $writer->writeFeatureCollectionOpen($req, $this->ctx, $countForAttr);

        if ($req->resultType === 'hits') {
            $writer->writeFeatureCollectionClose();
            return;
        }

        // Bounding box (use first table that has a geometry)
        $this->writeBoundedBy($writer, $req, $perTable, $srs);

        // Feature members
        $writer->writeFeatureMembersOpen($req->version);
        foreach ($req->typeNames as $table) {
            $this->streamFeatures($writer, $req, $table, $perTable[$table], $srs);
        }
        $writer->writeFeatureMembersClose($req->version);

        $writer->writeFeatureCollectionClose();
    }

    /**
     * Builds the SELECT sql, FROM clause, and bounds sql for one typeName.
     *
     * @return array{sql: string, from: string, sql2: string|null, tableObj: TableModel}
     * @throws OwsException
     */
    private function buildTableState(Request $req, string $table, int $srs): array
    {
        $postgisschema = $this->ctx->schema;
        $postgisObject = $this->ctx->model();
        $tableObj      = new TableModel("{$postgisschema}.{$table}", connection: $this->ctx->connection);

        if (!$tableObj->exists) {
            throw new OwsException(
                "Relation doesn't exist",
                attributes: ['exceptionCode' => 'InvalidParameterValue', 'locator' => 'typeName']
            );
        }

        $primeryKey = $tableObj->primaryKey;
        $geomField  = $postgisObject->getGeometryColumns("{$postgisschema}.{$table}", 'f_geometry_column');
        $geomType   = $postgisObject->getGeometryColumns("{$postgisschema}.{$table}", 'type');

        // Load per-column configuration
        $fieldConfArr = json_decode(
            (string) (new \app\controllers\Layer())->getValueFromKey("{$postgisschema}.{$table}.{$geomField}", 'fieldconf'),
            true
        ) ?? [];

        // Resolve field list
        if (!empty($req->properties)) {
            // Filter down to properties belonging to this table
            $fieldsArr = [];
            foreach ($req->properties as $property) {
                $parts = explode('.', $property, 2);
                if (count($parts) === 2 && $parts[0] === $table) {
                    $fieldsArr[] = $parts[1];
                } elseif (count($parts) === 1) {
                    $fieldsArr[] = $parts[0];
                }
            }
        } else {
            $fieldsArr = [];
            foreach ($postgisObject->getMetaData($table, false, false, null, null, false, false) as $key => $value) {
                if (!preg_match(self::SPECIAL_CHARS, $key)) {
                    $fieldsArr[] = $key;
                }
            }
        }

        // Sort by sort_id
        $arr = [];
        foreach ($fieldsArr as $value) {
            $arr[] = [(!empty($fieldConfArr[$value]['sort_id']) ? $fieldConfArr[$value]['sort_id'] : 0), $value];
        }
        usort($arr, static fn($a, $b) => $a[0] - $b[0]);

        // Filter out ignored fields
        $arr = array_filter($arr, static function ($item) use (&$fieldConfArr) {
            return empty($fieldConfArr[$item[1]]['ignore']);
        });

        $fieldsArr = [];
        foreach ($arr as $value) {
            $fieldsArr[] = $value[1];
        }

        // Double-quote field names to avoid SQL keyword conflicts
        $quotedFields = array_map(static fn($f) => "\"{$f}\"", $fieldsArr);

        $sql  = 'SELECT ' . implode(',', $quotedFields) . ",\"{$primeryKey['attname']}\" as fid";
        $sql2 = null;

        // Geometry and bytea rewrites
        foreach ($tableObj->metaData as $key => $colInfo) {
            if ($colInfo['type'] === 'geometry') {
                $gmlVersion = $req->outputFormat === 'GML3' ? '3' : '2';
                $longCrs    = $req->version === '1.1.0' ? 1 : 0;
                $flipAxis   = ($req->version === '1.1.0' && $srs == 4326) ? 16 : 0;
                $options    = (string) ($longCrs + $flipAxis + 4);

                if (str_contains((string) $geomType, 'POINT')) {
                    $type = 1;
                } elseif (str_contains((string) $geomType, 'LINE')) {
                    $type = 2;
                } elseif (str_contains((string) $geomType, 'POLYGON')) {
                    $type = 3;
                } else {
                    $type = -999;
                }

                if ($type === -999) {
                    $sql = str_replace(
                        "\"{$key}\"",
                        "ST_AsGml({$gmlVersion},ST_Transform(\"{$key}\",{$srs}),5,{$options}) as \"{$key}\"",
                        $sql
                    );
                } else {
                    $sql = str_replace(
                        "\"{$key}\"",
                        "ST_AsGml({$gmlVersion},ST_Transform(ST_CollectionExtract(\"{$key}\",{$type}),{$srs}),7,{$options}) as \"{$key}\"",
                        $sql
                    );
                }

                $sql2 = "SELECT ST_Xmin(ST_Extent(ST_Transform(\"{$key}\",{$srs}))) AS TXMin,"
                    . "ST_Xmax(ST_Extent(ST_Transform(\"{$key}\",{$srs}))) AS TXMax,"
                    . "ST_Ymin(ST_Extent(ST_Transform(\"{$key}\",{$srs}))) AS TYMin,"
                    . "ST_Ymax(ST_Extent(ST_Transform(\"{$key}\",{$srs}))) AS TYMax ";
            }
            if ($colInfo['type'] === 'bytea') {
                $sql = str_replace("\"{$key}\"", "encode(\"{$key}\",'escape') as {$key}", $sql);
            }
        }

        // Build FROM clause
        $from = " FROM \"{$postgisschema}\".\"{$table}\"";
        $timeSlice = $req->timeSlice;

        if ($tableObj->versioning && $timeSlice !== false && $timeSlice !== null && $timeSlice !== 'all') {
            $from .= ",(SELECT gc2_version_gid as _gc2_version_gid,"
                . "max(gc2_version_start_date) as max_gc2_version_start_date "
                . "from \"{$postgisschema}\".\"{$table}\" "
                . "where gc2_version_start_date <= '{$timeSlice}' "
                . "AND (gc2_version_end_date > '{$timeSlice}' OR gc2_version_end_date is null) "
                . "GROUP BY gc2_version_gid) as gc2_join";
        }

        // Resolve WHERE conditions
        $wheres   = $this->buildWhereFromRequest($req, $table, $postgisObject, $postgisschema, $srs);
        $filters  = $req->filter;

        $wheresFlag = false;
        $hasBbox    = !empty($req->bbox);
        $hasWhere   = !empty($wheres);
        $hasFilter  = !empty($filters);

        if ($hasBbox || $hasWhere || $hasFilter) {
            $from .= ' WHERE ';
            $wheresFlag = true;
        }
        if ($hasWhere) {
            $from .= '(' . $wheres . ')';
        }
        if ($hasFilter) {
            $f       = $postgisObject->getGeometryColumns("{$postgisschema}.{$table}", '*');
            $where   = WfsFilter::explode(
                $filters,
                $f['srid'],
                (string) $srs,
                $primeryKey['attname'],
                $f['f_geometry_column']
            );
            if ($where) {
                $from .= ($hasWhere ? ' AND ' : '') . '(' . $where . ')';
            }
        }

        // Versioning filter
        if ($tableObj->versioning && $timeSlice !== 'all') {
            if (!$wheresFlag) {
                $from .= ' WHERE ';
                $wheresFlag = true;
            } else {
                $from .= ' AND ';
            }
            if (!$timeSlice) {
                $from .= 'gc2_version_end_date is null';
            } else {
                $from .= 'gc2_join._gc2_version_gid = gc2_version_gid AND gc2_version_start_date = gc2_join.max_gc2_version_start_date';
            }
        }

        // Workflow filter
        if ($tableObj->workflow && !$this->ctx->parentUser) {
            $layerObj = new Layer();
            $roleObj  = $layerObj->getRole($postgisschema, $table);
            $role     = $roleObj['data'][$this->ctx->user] ?? null;
            switch ($role) {
                case 'author':
                    $from .= " AND (gc2_status = 3 OR gc2_workflow @> 'author => {$this->ctx->user}')";
                    break;
                case 'publisher':
                case 'reviewer':
                    // No additional filtering
                    break;
                default:
                    $from .= ' AND (gc2_status = 3)';
                    break;
            }
        }

        return [
            'sql'      => $sql,
            'from'     => $from,
            'sql2'     => $sql2,
            'tableObj' => $tableObj,
        ];
    }

    /**
     * Build the WHERE portion for BBOX and FEATUREID from the request.
     */
    private function buildWhereFromRequest(
        Request $req,
        string $table,
        \app\inc\Model $postgisObject,
        string $postgisschema,
        int $srs
    ): string {
        $wheres      = '';
        $wheresArr   = [];

        // FEATUREID
        if (!empty($req->featureIds)) {
            foreach ($req->featureIds as $featureid) {
                $u = explode('.', $featureid, 2);
                if ($u[0] !== $table) continue;
                $primeryKey    = $postgisObject->getPrimeryKey("{$postgisschema}.{$table}");
                $wheresArr[]   = "{$primeryKey['attname']}='{$u[1]}'";
            }
            if (!empty($wheresArr)) {
                $wheres = implode(' OR ', $wheresArr);
            }
        }

        // BBOX
        if (!empty($req->bbox)) {
            $bbox = $req->bbox;
            $bbox[4] = $bbox[4] ?? $req->srsName ?? (string) $srs;
            $axisOrder = WfsFilter::getAxisOrder($bbox[4]);
            $bboxSrid  = WfsFilter::parseEpsgCode($bbox[4]) ?? (string) $srs;
            $tableSrid = $postgisObject->getGeometryColumns("{$postgisschema}.{$table}", 'srid');
            $geomCol   = $postgisObject->getGeometryColumns("{$postgisschema}.{$table}", 'f_geometry_column');

            if ($axisOrder === 'longitude') {
                $bboxWhere = "ST_intersects("
                    . "ST_Transform(ST_GeometryFromText('POLYGON(("
                    . "{$bbox[0]} {$bbox[1]},{$bbox[0]} {$bbox[3]},{$bbox[2]} {$bbox[3]},{$bbox[2]} {$bbox[1]},{$bbox[0]} {$bbox[1]}))',"
                    . "{$bboxSrid}),{$tableSrid}),"
                    . "{$geomCol})";
            } else {
                $bboxWhere = "ST_intersects("
                    . "ST_Transform(ST_GeometryFromText('POLYGON(("
                    . "{$bbox[1]} {$bbox[0]},{$bbox[3]} {$bbox[0]},{$bbox[3]} {$bbox[2]},{$bbox[1]} {$bbox[2]},{$bbox[1]} {$bbox[0]}))',"
                    . "{$bboxSrid}),{$tableSrid}),"
                    . "{$geomCol})";
            }

            if (!empty($wheres) && !empty($req->featureIds)) {
                $wheres .= ' AND ';
            }
            $wheres .= $bboxWhere;
        }

        return $wheres;
    }

    /**
     * Returns total feature count across all typeNames.
     * Uses maxFeatures directly if set; otherwise runs COUNT queries.
     *
     * @param array<string, array{sql: string, from: string, sql2: string|null, tableObj: TableModel}> $perTable
     * @throws OwsException
     */
    private function countFeatures(Request $req, array $perTable, int $srs): int
    {
        if ($req->maxFeatures !== null) {
            return $req->maxFeatures;
        }

        $factory     = new StatementFactory(PDOCompatible: true);
        $rule        = new Rule($this->ctx->connection);
        $walkerRule  = new TableWalkerRule($this->ctx->user, 'wfst', 'select', '');
        $total       = 0;
        $postgisObject = $this->ctx->model();

        foreach ($perTable as $table => $state) {
            $countSql = 'SELECT COUNT(*) ' . $state['from'] . ' LIMIT ' . self::FEATURE_LIMIT;
            $select   = $factory->createFromString($countSql);
            $rules    = $rule->get();
            $walkerRule->setRules($rules);
            try {
                $select->dispatch($walkerRule);
            } catch (\Exception $e) {
                throw new OwsException($e->getMessage());
            }
            $rewrittenCount = $factory->createFromAST($select, true)->getSql();
            try {
                $res   = $postgisObject->prepare($rewrittenCount);
                $res->execute();
                $row   = $postgisObject->fetchRow($res);
                $total += (int) ($row['count'] ?? 0);
            } catch (PDOException $e) {
                throw new OwsException($e->getMessage());
            }
        }
        return $total;
    }

    /**
     * Emit gml:boundedBy using the first table that has a geometry (sql2 != null).
     *
     * @param array<string, array{sql: string, from: string, sql2: string|null, tableObj: TableModel}> $perTable
     * @throws OwsException
     */
    private function writeBoundedBy(GmlWriter $writer, Request $req, array $perTable, int $srs): void
    {
        $postgisObject = $this->ctx->model();

        foreach ($perTable as $table => $state) {
            if ($state['sql2'] === null) continue;

            try {
                $result = $postgisObject->execQuery($state['sql2'] . $state['from']);
            } catch (PDOException $e) {
                // If bounds query fails, skip boundedBy
                return;
            }
            if ($result === null) return;

            $myrow = $postgisObject->fetchRow($result);
            if (empty($myrow['txmin'])) return;

            $txmin = $myrow['txmin'];
            $tymin = $myrow['tymin'];
            $txmax = $myrow['txmax'];
            $tymax = $myrow['tymax'];

            $writer->writeTag('open', 'gml', 'boundedBy', null, true);
            if ($req->version === '1.1.0') {
                $writer->writeTag('open', 'gml', 'Envelope', ['srsName' => "urn:ogc:def:crs:EPSG::{$srs}"], true);
                $writer->writeTag('open', 'gml', 'lowerCorner', null, false);
                $writer->write($srs == 4326 ? "{$tymin} {$txmin}" : "{$txmin} {$tymin}");
                $writer->writeTag('close', 'gml', 'lowerCorner', null, true);
                $writer->writeTag('open', 'gml', 'upperCorner', null, false);
                $writer->write($srs == 4326 ? "{$tymax} {$txmax}" : "{$txmax} {$tymax}");
                $writer->writeTag('close', 'gml', 'upperCorner', null, true);
                $writer->writeTag('close', 'gml', 'Envelope', null, true);
            } else {
                $writer->writeTag('open', 'gml', 'Box', ['srsName' => "EPSG:{$srs}"], true);
                $writer->writeTag('open', 'gml', 'coordinates', ['decimal' => '.', 'cs' => ',', 'ts' => ' '], false);
                $writer->write("{$txmin},{$tymin} {$txmax},{$tymax}");
                $writer->writeTag('close', 'gml', 'coordinates', null, true);
                $writer->writeTag('close', 'gml', 'Box', null, true);
            }
            $writer->writeTag('close', 'gml', 'boundedBy', null, true);
            return;   // Only emit one boundedBy for the first table with geometry
        }
    }

    /**
     * Stream features for one typeName via cursor inside a transaction.
     *
     * @param array{sql: string, from: string, sql2: string|null, tableObj: TableModel} $state
     * @throws OwsException
     */
    private function streamFeatures(GmlWriter $writer, Request $req, string $table, array $state, int $srs): void
    {
        $factory       = new StatementFactory(PDOCompatible: true);
        $rule          = new Rule($this->ctx->connection);
        $walkerRule    = new TableWalkerRule($this->ctx->user, 'wfst', 'select', '');
        $postgisObject = $this->ctx->model();

        $fullSql = $state['sql'] . $state['from'] . ' LIMIT ' . ($req->maxFeatures ?? self::FEATURE_LIMIT);
        $select  = $factory->createFromString($fullSql);
        $rules   = $rule->get();
        $walkerRule->setRules($rules);
        try {
            $select->dispatch($walkerRule);
        } catch (\Exception $e) {
            throw new OwsException($e->getMessage());
        }
        $rewrittenSql = $factory->createFromAST($select, true)->getSql();

        try {
            $postgisObject->withTransaction(function () use ($postgisObject, $rewrittenSql, $writer, $req, $table, $state) {
                try {
                    $postgisObject->prepare("DECLARE curs CURSOR FOR {$rewrittenSql}")->execute();
                } catch (PDOException $e) {
                    throw new OwsException(
                        $e->getMessage(),
                        attributes: ['exceptionCode' => 'InvalidParameterValue', 'locator' => 'typeName']
                    );
                }

                $fetch = $postgisObject->prepare('FETCH 1 FROM curs');
                while ($fetch->execute() && $row = $fetch->fetch(\PDO::FETCH_ASSOC)) {
                    $writer->writeFeature($row, $table, $state['tableObj'], $req, $this->ctx);
                }

                $postgisObject->execQuery('CLOSE curs', 'PDO', 'transaction');
            });
        } catch (OwsException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new OwsException($e->getMessage());
        }
    }
}
