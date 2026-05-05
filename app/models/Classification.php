<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\ColorBrewer;
use app\inc\Connection;
use app\inc\Model;
use app\inc\Util;
use PDO;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;


/**
 * Class Classification
 * @package app\models
 */
class Classification extends Model
{
    private string $layer;
    private Table $table;
    private array $def;
    private ?string $geometryType;
    private Tile $tile;

    /**
     * Classification constructor.
     * @param string $table
     * @param Connection|null $connection
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    function __construct(string $table, ?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
        $this->layer = $table;
        $bits = explode(".", $this->layer);
        $this->table = new Table(table: $bits[0] . "." . $bits[1], connection: $this->connection);
        $this->tile = new Tile(table: $table, connection: $this->connection);
        // Check if geom type is overridden
        $def = new Tile(table: $table, connection: $this->connection);
        $this->def = $def->get();
        if (($this->def['data'][0]['geotype']) && $this->def['data'][0]['geotype'] != "Default") {
            $this->geometryType = $this->def['data'][0]['geotype'];
        } else {
            $this->geometryType = null;
        }
    }

    /**
     * Retrieves all records from the settings.geometry_columns_join table for a specific layer,
     * processes and structures the data, and returns the result.
     *
     * @return array Processed data including success status and structured information.
     * @throws PDOException If database operations or data handling fails.
     */
    public function getAll(): array
    {
        $sql = "SELECT class FROM settings.geometry_columns_join WHERE _key_=:layer";
        $res = $this->prepare($sql);
        $this->execute($res, ['layer' => $this->layer]);
        $arrNew = [];
        $response['success'] = true;
        $row = $this->fetchRow($res);
        $arr = $arr2 = !empty($row['class']) && is_array(json_decode($row['class'], true)) ? json_decode($row['class'], true) : [];
        for ($i = 0; $i < sizeof($arr); $i++) {
            $last = 10000;
            foreach ($arr2 as $key => $value) {
                if (isset($value->sortid) && $value->sortid < $last) {
                    $del = $key;
                    $last = $value->sortid;
                }
            }
            if (isset($del) && isset($arr2[$del])) {
                unset($arr2[$del]);
            }
        }
        for ($i = 0; $i < sizeof($arr); $i++) {
            $arrNew[$i] = (array)Util::casttoclass('stdClass', $arr[$i]);
            $arrNew[$i]['id'] = $i;
        }
        $response['data'] = $arrNew;
        return $response;
    }

    /**
     * Retrieves and processes class data identified by the given ID.
     *
     * @param int $id The identifier of the class to retrieve.
     * @return array An array containing processed class data and success status.
     */
    public function get(int $id): array
    {
        $classes = $this->getAll();
        $response['success'] = true;
        $arr = $classes['data'][$id];
        unset($arr['id']);
        foreach ($arr as $key => $value) {
            if ($value === null) { // Never send null to client
                $arr[$key] = "";
            }
        }
        $props = [
            "name" => "Unnamed Class",
            "label" => false,
            "label_text" => "",
            "label2_text" => "",
            "force_label" => false,
            "color" => "#FF0000",
            "outlinecolor" => "#FF0000",
            "size" => "2",
            "width" => "1"];
        foreach ($arr as $ignored) {
            foreach ($props as $key2 => $value2) {
                if (!isset($arr[$key2])) {
                    $arr[$key2] = $value2;
                }
            }
        }
        $response['data'] = array($arr);
        return $response;
    }

    /**
     * @param string $class The class name to be stored in the database.
     * @return void
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    private function store(string $class): void
    {
        $tableObj = new Table(table: "settings.geometry_columns_join", connection: $this->connection);
        $data['_key_'] = $this->layer;
        $data['class'] = $class;
        $tableObj->updateRecord($data, '_key_');
    }

    /**
     * @param string $class The class name to be stored in the database.
     * @return void
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    private function storeForce(string $class): void
    {
        $tableObj = new Table(table: "settings.geometry_columns_join", connection: $this->connection);
        $data['_key_'] = $this->layer;
        $data['class'] = $class;
        $tableObj->updateRecord($data, '_key_');
        $data['class_cache'] = $class;
        $tableObj->updateRecord($data, '_key_');
    }

    /**
     * Updates the database records with class data derived from the wizard.
     *
     * @param string $class The JSON-encoded class data received from the wizard.
     * @return void
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    private function storeFromWizard(string $class): void
    {
        $tableObj = new Table(table: "settings.geometry_columns_join", connection: $this->connection);

        $existingClass = $tableObj->getGeometryColumns($this->layer, "*")["class"];
        $classCache = $tableObj->getGeometryColumns($this->layer, "*")["class_cache"];

        $newClass = json_decode($class, true);

        $existingClass = $existingClass ? json_decode($tableObj->getGeometryColumns($this->layer, "*")["class"], true) : [];
        $cachedClass = $classCache ? json_decode($classCache, true) : [];
        $mergedClass = $this->mergeClasses($cachedClass, $existingClass, $newClass);

        $merged['_key_'] = $this->layer;
        $merged['class'] = $mergedClass;
        $tableObj->updateRecord($merged, '_key_');

        $cached['_key_'] = $this->layer;
        $cached['class_cache'] = $newClass;
        $tableObj->updateRecord($cached, '_key_');
    }

    /**
     * Merges class definitions from cached, existing, and new class arrays.
     * Ensures that externally modified properties are preserved, while allowing
     * updates from new data where appropriate.
     *
     * @param array $cachedClass The cached version of class definitions, used to track previous state.
     * @param array $existingClass The existing version of class definitions, representing current external modifications.
     * @param array $newClass The new class definitions to merge into the existing state.
     * @return array The merged array of class definitions, preserving external changes and incorporating valid updates.
     */
    function mergeClasses(array $cachedClass, array $existingClass, array $newClass): array
    {
        // Helper to map by name for comparison
        $byName = function ($arr) {
            $out = [];
            foreach ($arr as $item) {
                if (is_array($item) && isset($item['name'])) {
                    $out[$item['name']] = $item;
                }
            }
            return $out;
        };

        $cached = $byName($cachedClass);
        $existing = $byName($existingClass);
        $incoming = $byName($newClass);

        // Merge, property by property
        $result = [];

        $allNames = array_unique(array_merge(array_keys($cached), array_keys($existing), array_keys($incoming)));
        foreach ($allNames as $name) {
            // Start from current existing if it exists, otherwise cache
            $target = $existing[$name] ?? $cached[$name] ?? $incoming[$name] ?? [];

            // If property has NOT been edited externally, we can update from wizard
            // Compare cached vs. existing for this class by property
            if (isset($cached[$name]) && isset($existing[$name]) && isset($incoming[$name])) {
                // Compare all public properties
                foreach ($incoming[$name] as $prop => $newVal) {
                    $cachedVal = $cached[$name][$prop] ?? null;
                    $existingVal = $existing[$name][$prop] ?? null;
                    // Only update property if not changed externally (existing == cached)
                    if ($existingVal === $cachedVal) {
                        $target[$prop] = $newVal;
                    }
                    // else: keep the externally modified value
                }
            } elseif (isset($incoming[$name])) {
                // New class or wasn't in cache/existing
                $target = $incoming[$name];
            }
            $result[$name] = $target;
        }
        return array_values($result);
    }

    /**
     * Updates the geometry columns join table with the provided wizard data.
     *
     * @param string $classWizard The wizard object or data to be stored in the database.
     * @return void
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    private function storeWizard(string $classWizard): void
    {
        $tableObj = new Table(table: "settings.geometry_columns_join", connection: $this->connection);
        $data['_key_'] = $this->layer;
        $data['classwizard'] = $classWizard;
        $tableObj->updateRecord($data, "_key_");
    }

    /**
     * Inserts a new unnamed class into the existing collection of classes and updates the storage.
     *
     * @return array An associative array containing the success status and a message indicating the outcome.
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    public function insert(): array
    {
        $classes = $this->getAll();
        $classes['data'][] = ["name" => "Unnamed class"];
        $this->store(json_encode($classes['data'], JSON_UNESCAPED_UNICODE));
        $response['success'] = true;
        $response['message'] = "Inserted one class";
        return $response;
    }

    /**
     * Updates the specified class data with the provided values.
     *
     * @param mixed $id The identifier of the class to be updated.
     * @param object $data The key-value pairs of the data to update the class with.
     * @return array An associative array containing the success status and message.
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function update(int $id, object $data): array
    {
        $classes = $this->getAll();
        foreach ((array)$data as $k => $v) {
            $classes['data'][$id][$k] = $v;
        }
        $this->store(json_encode($classes['data'], JSON_UNESCAPED_UNICODE));
        $response['success'] = true;
        $response['message'] = "Updated one class";
        return $response;
    }

    /**
     * Deletes a specific class by its ID and reindexes the remaining data.
     *
     * @param int $id The ID of the class to be deleted from the data set.
     * @return array An associative array containing the success status and a message.
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    public function destroy(int $id): array // Geometry columns
    {
        $arr = [];
        $classes = $this->getAll();
        unset($classes['data'][$id]);
        foreach ($classes['data'] as $value) { // Reindex array
            unset($value['id']);
            $arr[] = $value;
        }
        $classes['data'] = $arr;
        $this->store(json_encode($classes['data'], JSON_UNESCAPED_UNICODE));
        $response['success'] = true;
        $response['message'] = "Deleted one class";
        return $response;
    }

    /**
     * Resets the stored data to an empty JSON array.
     *
     * @return void
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    private function reset(): void
    {
        $this->store(json_encode([]));
    }

    /**
     * Sets the layer definition by retrieving, modifying, and updating tile data.
     *
     * @return void
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    private function setLayerDef(): void
    {
        $def = $this->tile->get();
        $def["data"][0]["cluster"] = null;
        $defJson = (object)$def["data"][0];
        $this->tile->update($defJson);
    }

    /**
     * Creates a single class definition and stores it based on the provided data and color.
     *
     * @param object $data The data object containing the class details and custom settings.
     * @param string $color Hexadecimal color code used for the class representation.
     * @return array An array containing a success message and status.
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    public function createSingle(object $data, string $color): array
    {
        $this->setLayerDef();
        $layer = new Layer(connection: $this->connection);
        $geometryType = $this->geometryType ?: $layer->getValueFromKey($this->layer, "type");
        $classes = [self::createClass($geometryType, $layer->getValueFromKey($this->layer, "f_table_title") ?: $layer->getValueFromKey($this->layer, "f_table_name"), null, 10, "#" . $color, $data)];
        if ($data->custom->force) {
            $this->storeForce(json_encode($classes, JSON_UNESCAPED_UNICODE));
        } else {
            $this->storeFromWizard(json_encode($classes, JSON_UNESCAPED_UNICODE));
        }
        $response['success'] = true;
        $response['message'] = "Updated one class";
        $this->storeWizard(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    /**
     * Creates unique classes based on the provided field and data configuration.
     * The method fetches distinct values for the specified field, performs conditional
     * operations, and applies color ramps or expressions to generate a structured response.
     *
     * @param string $field The field name from which unique values will be determined.
     * @param object $data The data object containing settings and customizations for class generation.
     * @return array The response containing the success status, message, and additional details.
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    public function createUnique(string $field, object $data): array
    {
        $this->setLayerDef();
        $layer = new Layer(connection: $this->connection);
        $geometryType = ($this->geometryType) ?: $layer->getValueFromKey($this->layer, "type");
        $fieldObj = $this->table->metaData[$field];
        $query = "SELECT distinct($field) as value FROM " . $this->table->table . " ORDER BY $field";
        $res = $this->prepare($query);
        try {
            $res->execute();
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $rows = $this->fetchAll($res);
        $type = $fieldObj['type'];
        if (sizeof($rows) > 1000) {
            $response['success'] = false;
            $response['message'] = "Too many classes. Stopped after 1000.";
            $response['code'] = 405;
            return $response;
        }
        $colorBrewer = [];
        if ($data->custom->colorramp !== false && $data->custom->colorramp != "-1") {
            $colorBrewer = ColorBrewer::getQualitative($data->custom->colorramp);
        }
        $cArr = array();
        $expression = '';
        foreach ($rows as $key => $row) {
            if ($type == "number" || $type == "int") {
                $expression = "[$field]={$row['value']}";
            }
            if ($type == "text" || $type == "string") {
                $expression = "'[$field]'='{$row['value']}'";
            }
            $name = $row['value'];
            if ($data->custom->colorramp !== false && $data->custom->colorramp != "-1") {
                $c = current($colorBrewer);
                next($colorBrewer);
            } else {
                $c = null;
            }
            $cArr[$key] = self::createClass($geometryType, $name, $expression, ($key * 10) + 10, $c, $data);
        }
        $response['success'] = true;
        $response['message'] = "Updated " . sizeof($rows) . " classes";
        if ($data->custom->force) {
            $this->storeForce(json_encode($cArr, JSON_UNESCAPED_UNICODE));
        } else {
            $this->storeFromWizard(json_encode($cArr, JSON_UNESCAPED_UNICODE));
        }
        $this->storeWizard(json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response;
    }

    /**
     * Creates equal intervals based on a specified field and generates classes with corresponding styles and expressions.
     *
     * @param string $field The database field to be used for determining interval ranges.
     * @param int $num The number of intervals (classes) to create.
     * @param string $startColor The starting color of the gradient for the intervals.
     * @param string $endColor The ending color of the gradient for the intervals.
     * @param object $data Additional data object containing custom parameters or settings.
     * @return array An associative array containing success status and message after processing the intervals.
     * @throws PDOException|InvalidArgumentException|GC2Exception
    */
    public function createEqualIntervals(string $field, int $num, string $startColor, string $endColor, object $data): array
    {
        $this->setLayerDef();
        $layer = new Layer(connection: $this->connection);
        $geometryType = ($this->geometryType) ?: $layer->getValueFromKey($this->layer, "type");
        if ($geometryType == "RASTER") {
            $query = "SELECT (ST_SummaryStatsAgg(rast, 1, true)).* FROM {$this->table->table}";
        } else {
            $query = "SELECT max($field) as max, min($field) FROM {$this->table->table}";
        }
        $res = $this->prepare($query);
        $res->execute();
        $row = $this->fetchRow($res);
        $diff = $row["max"] - $row["min"];
        $interval = $diff / $num;

        $grad = Util::makeGradient($startColor, $endColor, $num);
        $classes = [];
        for ($i = 1; $i <= ($num); $i++) {
            $top = $row['min'] + ($interval * $i);
            $bottom = $top - $interval;
            if ($i == $num) {
                $expression = "[$field]>=" . $bottom . " AND [$field]<=" . $top;
            } else {
                $expression = "[$field]>=" . $bottom . " AND [$field]<" . $top;
            }
            $name = " < " . round(($top), 2);
            $class = self::createClass($geometryType, $name, $expression, ((($i - 1) * 10) + 10), $grad[$i - 1], $data);
            $classes[] = $class;
        }
        if ($data->custom->force) {
            $this->storeForce(json_encode($classes, JSON_UNESCAPED_UNICODE));
        } else {
            $this->storeFromWizard(json_encode($classes, JSON_UNESCAPED_UNICODE));
        }
        $response['success'] = true;
        $response['message'] = "Updated " . $num . " classes";
        $this->storeWizard(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    /**
     * Creates quantile-based classifications for data visualization.
     *
     * @param string $field The field to be used for quantile generation.
     * @param int $num The number of quantile classes to generate.
     * @param string $startColor The starting color of the gradient for classes.
     * @param string $endColor The ending color of the gradient for classes.
     * @param object $data Additional metadata or configuration for the quantile generation process.
     * @return array An array containing the response, including success status, values of the tops of the classes,
     *               and a message indicating the outcome.
     * @throws PDOException|InvalidArgumentException|GC2Exception
     */
    public function createQuantile(string $field, int $num, string $startColor, string $endColor, object $data): array
    {
        $this->setLayerDef();
        $layer = new Layer(connection: $this->connection);
        $geometryType = $layer->getValueFromKey($this->layer, "type");
        $query = "SELECT count(*) AS count FROM " . $this->table->table;
        $res = $this->prepare($query);
        $this->execute($res);
        $row = $this->fetchRow($res);
        $count = $row["count"];
        $numPerClass = $temp = ($count / $num);
        $query = "SELECT * FROM " . $this->table->table . " ORDER BY $field";
        $res = $this->prepare($query);
        $res->execute();
        $grad = Util::makeGradient($startColor, $endColor, $num);
        $bottom = 0;
        $top = 0;
        $tops = [];
        $u = 0;
        $classes = [];
        for ($i = 1; $i <= $count; $i++) {
            $row = $res->fetch(PDO::FETCH_ASSOC);
            if ($i == 1) {
                $bottom = $row[$field] ?? 0;
            }
            if ($i >= $temp || $i == $count) {
                if ($top) {
                    $bottom = $top;
                }
                $top = $row[$field] ?? 0;
                if ($i == $count) {
                    $expression = "[$field]>=" . $bottom . " AND [$field]<=" . $top;
                } else {
                    $expression = "[$field]>=" . $bottom . " AND [$field]<" . $top;
                }
                $name = " < " . round(($top), 2);
                $tops[] = [$top, $grad[$u]];
                $class = self::createClass($geometryType, $name, $expression, (($u + 1) * 10), $grad[$u], $data);
                $classes[] = $class;

                $u++;
                $temp = $temp + $numPerClass;
            }
        }
        if ($data->custom->force) {
            $this->storeForce(json_encode($classes, JSON_UNESCAPED_UNICODE));
        } else {
            $this->storeFromWizard(json_encode($classes, JSON_UNESCAPED_UNICODE));
        }
        $response['success'] = true;
        $response['values'] = $tops;
        $response['message'] = "Updated " . $num . " classes";
        $this->storeWizard(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    /**
     * Creates a cluster configuration for a given distance and data, updating the layer or wizard settings as needed.
     *
     * @param int $distance The clustering distance to be applied.
     * @param object $data The data object containing configuration and properties for clustering.
     * @return array An associative array containing the success status, message, and response code.
     * @throws InvalidArgumentException If the layer geometry type is not compatible with clustering.
     * @throws PDOException If there is an issue with database-related operations during persistence.
     * @throws GC2Exception If an error occurs during the handling of map-related configurations.
     */
    public function createCluster(int $distance, object $data): array
    {
        $layer = new Layer(connection: $this->connection);
        $geometryType = ($this->geometryType) ?: $layer->getValueFromKey($this->layer, "type");
        if ($geometryType != "POINT" && $geometryType != "MULTIPOINT") {
            $response['success'] = false;
            $response['message'] = "Only point layers can be clustered";
            $response['code'] = 400;
            return $response;
        }
        $classes = [];
        // Set layer def
        $def = $this->tile->get();
        $def["data"][0]["cluster"] = $distance;
        $def["data"][0]["meta_tiles"] = true;
        $def["data"][0]["meta_size"] = 4;
        $defJson = (object)$def["data"][0];
        $this->tile->update($defJson);
        //Set single class
        $ClusterFeatureCount = "Cluster_FeatureCount";
        $expression = "[$ClusterFeatureCount]=1";
        $name = "Single";
        $classes[] = self::createClass($geometryType, $name, $expression, 10, "#0000FF", $data);
        //Set cluster class
        $expression = "[$ClusterFeatureCount]>1";
        $name = "Cluster";
        $data->labelText = "[$ClusterFeatureCount]";
        $data->labelSize = "9";
        $data->labelPosition = "cc";
        $data->symbolSize = "50";
        $data->overlaySize = "35";
        $data->overlayColor = "#00FF00";
        $data->overlaySymbol = "circle";
        $data->symbol = "circle";
        $data->opacity = "25";
        $data->overlayOpacity = "70";
        $data->force = true;
        $classes[] = self::createClass($geometryType, $name, $expression, 20, "#00FF00", $data);
        if ($data->custom->force) {
            $this->storeForce(json_encode($classes, JSON_UNESCAPED_UNICODE));
        } else {
            $this->storeFromWizard(json_encode($classes, JSON_UNESCAPED_UNICODE));
        }
        $response['success'] = true;
        $response['message'] = "Updated 2 classes";
        $this->storeWizard(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    /**
     * Copies class configuration from one key to another within the geometry columns join settings.
     *
     * @param string $to The destination key to which the class configuration will be copied.
     * @param string $from The source key from which the class configuration will be retrieved.
     * @return array An array containing the result of the update operation.
     * @throws PDOException If a database error occurs during query execution.
     * @throws InvalidArgumentException If invalid arguments are provided.
     * @throws GC2Exception If an error specific to GC2 handling occurs.
     */
    public function copyClasses(string $to, string $from): array
    {
        $query = "SELECT class FROM settings.geometry_columns_join WHERE _key_ =:from";
        $res = $this->prepare($query);
        $this->execute($res, ["from" => $from]);
        $row = $this->fetchRow($res);
        $data['class'] = $row["class"];
        $data['_key_'] = $to;
        $geometryColumnsObj = new table(table: "settings.geometry_columns_join", connection: $this->connection);
        return $geometryColumnsObj->updateRecord($data, "_key_");
    }


    /**
     * Creates a class object based on the specified parameters and data input.
     *
     * @param string $type The type of the geometry (e.g., POINT, MULTIPOINT).
     * @param string $name The name of the class. Defaults to "Unnamed class".
     * @param string|null $expression The expression to filter features associated with the class.
     * @param int $sortid The sort order identifier for the class. Defaults to 1.
     * @param string|null $color The primary color of the class. If not provided, a random color is generated.
     * @param object|null $data Additional data for customizing the class properties.
     **/
    static function createClass(string $type, string $name = "Unnamed class", ?string $expression = null, int $sortid = 1, ?string $color = null, ?object $data = null): object
    {
        $symbol = $data->symbol ?? "";
        $size = $data->symbolSize ?? "";
        $outlineColor = $data->outlineColor ?? "";
        $color = ($color) ?: Util::randHexColor();
        if ($type == "POINT" || $type == "MULTIPOINT") {
            $symbol = $data->symbol ?? "circle";
            $size = $data->symbolSize ?? 10;
        }
        return (object)[
            "sortid" => $sortid,
            "name" => $name,
            "expression" => $expression,
            "label" => !empty($data->labelText),
            "label_size" => !empty($data->labelSize) ? $data->labelSize : "",
            "label_color" => !empty($data->labelColor) ? $data->labelColor : "",
            "color" => $color,
            "outlinecolor" => !empty($outlineColor) ? $outlineColor : "",
            "symbol" => $symbol,
            "angle" => !empty($data->angle) ? $data->angle : "",
            "size" => $size,
            "width" => !empty($data->lineWidth) ? $data->lineWidth : "",
            "overlaycolor" => !empty($data->overlayColor) ? $data->overlayColor : "",
            "overlayoutlinecolor" => "",
            "overlaysymbol" => !empty($data->overlaySymbol) ? $data->overlaySymbol : "",
            "overlaysize" => !empty($data->overlaySize) ? $data->overlaySize : "",
            "overlaywidth" => "",
            "label_text" => !empty($data->labelText) ? $data->labelText : "",
            "label_position" => !empty($data->labelPosition) ? $data->labelPosition : "",
            "label_font" => !empty($data->labelFont) ? $data->labelFont : "",
            "label_fontweight" => !empty($data->labelFontWeight) ? $data->labelFontWeight : "",
            "label_angle" => !empty($data->labelAngle) ? $data->labelAngle : "",
            "label_backgroundcolor" => !empty($data->labelBackgroundcolor) ? $data->labelBackgroundcolor : "",
            "style_opacity" => !empty($data->opacity) ? $data->opacity : "",
            "overlaystyle_opacity" => !empty($data->overlayOpacity) ? $data->overlayOpacity : "",
            "label_force" => !empty($data->force) ? $data->force : "",
            "gap" => !empty($data->gap) ? $data->gap : "",
            "minsize" => !empty($data->minsize) ? $data->minsize : "",
            "maxsize" => !empty($data->maxsize) ? $data->maxsize : "",
            "style_offsetx" => !empty($data->style_offsetx) ? $data->style_offsetx : "",
            "style_offsety" => !empty($data->style_offsety) ? $data->style_offsety : "",
            "style_polaroffsetr" => !empty($data->style_polaroffsetr) ? $data->style_polaroffsetr : "",
            "style_polaroffsetd" => !empty($data->style_polaroffsetd) ? $data->style_polaroffsetd : "",
            "label_outlinecolor" => !empty($data->label_outlinecolor) ? $data->label_outlinecolor : "",
            "label_buffer" => !empty($data->label_buffer) ? $data->label_buffer : "",
            "label_repeatdistance" => !empty($data->label_repeatdistance) ? $data->label_repeatdistance : "",
            "label_backgroundpadding" => !empty($data->label_backgroundpadding) ? $data->label_backgroundpadding : "",
            "label_offsetx" => !empty($data->label_offsetx) ? $data->label_offsetx : "",
            "label_offsety" => !empty($data->label_offsety) ? $data->label_offsety : "",
            "label_expression" => !empty($data->label_expression) ? $data->label_expression : "",
            "label_maxsize" => !empty($data->label_maxsize) ? $data->label_maxsize : "",
            "label_minfeaturesize" => !empty($data->label_minfeaturesize) ? $data->label_minfeaturesize : "",
            "label_minscaledenom" => !empty($data->label_minscaledenom) ? $data->label_minscaledenom : "",
            "label_maxscaledenom" => !empty($data->label_maxscaledenom) ? $data->label_maxscaledenom : "",
        ];
    }
}