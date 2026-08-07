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

    public const STYLE_KEYS = ['color', 'outlinecolor', 'symbol', 'size', 'width', 'angle', 'gap',
        'opacity', 'pattern', 'linecap', 'geomtransform', 'minsize', 'maxsize',
        'offsetx', 'offsety', 'polaroffsetr', 'polaroffsetd'];

    /**
     * Maps the new unprefixed style keys to their legacy flat-format names (used only
     * on the base class object, optionally prefixed with 'overlay' for the second symbol).
     */
    private const STYLE_KEY_LEGACY = [
        'opacity' => 'style_opacity',
        'offsetx' => 'style_offsetx',
        'offsety' => 'style_offsety',
        'polaroffsetr' => 'style_polaroffsetr',
        'polaroffsetd' => 'style_polaroffsetd',
    ];

    /**
     * Maps new unprefixed class-level keys to the legacy/interim key they replace.
     * These are identically named in both the legacy-flat and interim-new formats.
     */
    private const CLASS_KEY_LEGACY = [
        'minscaledenom' => 'class_minscaledenom',
        'maxscaledenom' => 'class_maxscaledenom',
    ];

    public const LABEL_KEYS = ['force', 'text', 'minscaledenom', 'maxscaledenom', 'position', 'size',
        'color', 'outlinecolor', 'buffer', 'repeatdistance', 'angle', 'backgroundcolor',
        'backgroundpadding', 'offsetx', 'offsety', 'font', 'fontweight', 'expression',
        'maxsize', 'minfeaturesize'];

    /**
     * Convert a class object from the legacy flat format (Symbol1/Symbol2/Label1/Label2 keys)
     * to the new format with styles[] and labels[] arrays. Idempotent: new-format input passes
     * through unchanged (apart from null values inside entries becoming empty strings and the
     * styles/labels keys being guaranteed to exist). Legacy flat keys are always stripped.
     */
    public static function normalizeClass(array $class): array
    {
        // Normalize any nested stdClass objects to plain arrays
        $class = json_decode(json_encode($class), true);
        $hasNewFormat = isset($class['styles']) || isset($class['labels']);

        // Class-level scale denominators: class_minscaledenom/class_maxscaledenom are named
        // identically in both the legacy-flat and interim-new formats, so a single rename
        // pass covers both. If both old and new keys are present, the new key wins.
        foreach (self::CLASS_KEY_LEGACY as $new => $old) {
            if (array_key_exists($old, $class)) {
                if (!array_key_exists($new, $class)) {
                    $class[$new] = $class[$old];
                }
                unset($class[$old]);
            }
        }

        $legacyStyles = [];
        foreach ([['', 10, 'Symbol 1'], ['overlay', 20, 'Symbol 2']] as [$prefix, $sortid, $name]) {
            $style = [];
            foreach (self::STYLE_KEYS as $key) {
                $legacyKey = $prefix . (self::STYLE_KEY_LEGACY[$key] ?? $key);
                if (!empty($class[$legacyKey])) {
                    $style[$key] = $class[$legacyKey];
                }
                unset($class[$legacyKey]);
            }
            if (!empty($style)) {
                $legacyStyles[] = array_merge(['sortid' => $sortid, 'name' => $name], $style);
            }
        }

        $legacyLabels = [];
        foreach ([['label', 10, 'Label 1'], ['label2', 20, 'Label 2']] as [$prefix, $sortid, $name]) {
            $label = [];
            foreach (self::LABEL_KEYS as $key) {
                if (!empty($class[$prefix . '_' . $key])) {
                    $label[$key] = $class[$prefix . '_' . $key];
                }
                unset($class[$prefix . '_' . $key]);
            }
            $on = !empty($class[$prefix]);
            unset($class[$prefix]);
            if ($on || !empty($label)) {
                $legacyLabels[] = array_merge(['sortid' => $sortid, 'name' => $name, 'on' => $on], $label);
            }
        }

        if ($hasNewFormat) {
            $class['styles'] = $class['styles'] ?? [];
            $class['labels'] = $class['labels'] ?? [];
        } else {
            $class['styles'] = $legacyStyles;
            $class['labels'] = $legacyLabels;
        }

        foreach (['styles', 'labels'] as $k) {
            foreach ($class[$k] as $i => $entry) {
                if (!is_array($entry)) continue;
                // Interim-new-format rename pass: styles[] entries may still carry the old
                // style_* keys as persisted by earlier versions of this code. New key wins
                // when both are present.
                if ($k === 'styles') {
                    foreach (self::STYLE_KEY_LEGACY as $new => $old) {
                        if (array_key_exists($old, $entry)) {
                            if (!array_key_exists($new, $entry)) {
                                $entry[$new] = $entry[$old];
                            }
                            unset($entry[$old]);
                        }
                    }
                }
                foreach ($entry as $prop => $v) {
                    if ($v === null) $entry[$prop] = "";
                }
                $class[$k][$i] = $entry;
            }
        }
        return $class;
    }

    /**
     * Generates a short random id (8 hex chars) for classes, styles and labels.
     */
    public static function generateId(): string
    {
        return bin2hex(random_bytes(4));
    }

    /**
     * Normalizes every class (see normalizeClass) and assigns a missing `id` to each
     * class and to each entry in its styles/labels arrays. Idempotent: existing ids
     * are never changed. Ids are unique among classes and among entries within a class.
     */
    public static function ensureIds(array $classes): array
    {
        $classes = array_values(array_map([self::class, 'normalizeClass'], $classes));
        $classIds = array_filter(array_column($classes, 'id'));
        foreach ($classes as $i => $class) {
            if (empty($class['id']) || !is_string($class['id'])) {
                do {
                    $id = self::generateId();
                } while (in_array($id, $classIds, true));
                $classIds[] = $id;
                $classes[$i]['id'] = $id;
            }
            foreach (['styles', 'labels'] as $kind) {
                $entryIds = array_filter(array_column($classes[$i][$kind], 'id'));
                foreach ($classes[$i][$kind] as $j => $entry) {
                    if (empty($entry['id']) || !is_string($entry['id'])) {
                        do {
                            $id = self::generateId();
                        } while (in_array($id, $entryIds, true));
                        $entryIds[] = $id;
                        $classes[$i][$kind][$j]['id'] = $id;
                    }
                }
            }
        }
        return $classes;
    }

    /**
     * Default sortid for a new entry: highest existing sortid + 10 (10 when empty).
     */
    public static function nextSortId(array $entries): int
    {
        $max = 0;
        foreach ($entries as $entry) {
            $max = max($max, (int)($entry['sortid'] ?? 0));
        }
        return $max + 10;
    }

    /**
     * Reads the raw class JSON for the layer.
     */
    private function readRawClasses(): array
    {
        $sql = "SELECT class FROM settings.geometry_columns_join WHERE _key_=:layer";
        $res = $this->prepare($sql);
        $this->execute($res, ['layer' => $this->layer]);
        $row = $this->fetchRow($res);
        return !empty($row['class']) && is_array(json_decode($row['class'], true)) ? json_decode($row['class'], true) : [];
    }

    /**
     * Returns all classes in normalized form with ids on classes, styles and labels.
     * Missing ids are persisted back, so ids are stable from the first call onward.
     */
    public function getAllWithIds(): array
    {
        $raw = $this->readRawClasses();
        $classes = self::ensureIds($raw);
        if ($classes !== $raw) {
            $this->store(json_encode($classes));
        }
        return $classes;
    }

    /**
     * @throws GC2Exception
     */
    public function getClassById(string $id): array
    {
        foreach ($this->getAllWithIds() as $class) {
            if ($class['id'] === $id) {
                return $class;
            }
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }

    /**
     * Replaces the whole class array (declarative provisioning). Ids are assigned.
     */
    public function replaceClasses(array $classes): array
    {
        $classes = self::ensureIds($classes);
        $this->store(json_encode($classes));
        return $classes;
    }

    /**
     * Appends new classes and returns their ids. A missing sortid defaults to
     * highest existing + 10.
     */
    public function insertClasses(array $newClasses): array
    {
        $classes = $this->getAllWithIds();
        $count = count($classes);
        foreach ($newClasses as $newClass) {
            if (!isset($newClass['sortid']) || $newClass['sortid'] === '') {
                $newClass['sortid'] = self::nextSortId($classes);
            }
            $classes[] = $newClass;
        }
        $classes = self::ensureIds($classes);
        $this->store(json_encode($classes));
        return array_column(array_slice($classes, $count), 'id');
    }

    /**
     * Key-merges $props into the class. `id`, `styles` and `labels` are ignored.
     * @throws GC2Exception
     */
    public function patchClassById(string $id, array $props): void
    {
        unset($props['id'], $props['styles'], $props['labels']);
        $classes = $this->getAllWithIds();
        foreach ($classes as $i => $class) {
            if ($class['id'] === $id) {
                $classes[$i] = array_merge($class, $props);
                $this->store(json_encode($classes));
                return;
            }
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }

    /**
     * @throws GC2Exception
     */
    public function deleteClassById(string $id): void
    {
        $classes = $this->getAllWithIds();
        foreach ($classes as $i => $class) {
            if ($class['id'] === $id) {
                array_splice($classes, $i, 1);
                $this->store(json_encode($classes));
                return;
            }
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }

    /**
     * @throws GC2Exception
     */
    public function getEntries(string $classId, string $kind): array
    {
        return $this->getClassById($classId)[$kind];
    }

    /**
     * Appends entries to a class's styles or labels and returns their ids.
     * A missing sortid defaults to highest existing + 10.
     * @throws GC2Exception
     */
    public function insertEntries(string $classId, string $kind, array $entries): array
    {
        if (count($entries) === 0) {
            return [];
        }
        $classes = $this->getAllWithIds();
        foreach ($classes as $i => $class) {
            if ($class['id'] === $classId) {
                foreach ($entries as $entry) {
                    if (!isset($entry['sortid']) || $entry['sortid'] === '') {
                        $entry['sortid'] = self::nextSortId($classes[$i][$kind]);
                    }
                    $classes[$i][$kind][] = $entry;
                }
                $classes = self::ensureIds($classes);
                $this->store(json_encode($classes));
                return array_column(array_slice($classes[$i][$kind], -count($entries)), 'id');
            }
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }

    /**
     * Key-merges $props into a style/label entry. `id` is ignored.
     * @throws GC2Exception
     */
    public function patchEntryById(string $classId, string $kind, string $id, array $props): void
    {
        unset($props['id']);
        $classes = $this->getAllWithIds();
        foreach ($classes as $i => $class) {
            if ($class['id'] !== $classId) {
                continue;
            }
            foreach ($class[$kind] as $j => $entry) {
                if ($entry['id'] === $id) {
                    $classes[$i][$kind][$j] = array_merge($entry, $props);
                    $this->store(json_encode($classes));
                    return;
                }
            }
            throw new GC2Exception(ucfirst(rtrim($kind, 's')) . " not found", 404, null, strtoupper(rtrim($kind, 's')) . "_NOT_FOUND");
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
    }

    /**
     * @throws GC2Exception
     */
    public function deleteEntryById(string $classId, string $kind, string $id): void
    {
        $classes = $this->getAllWithIds();
        foreach ($classes as $i => $class) {
            if ($class['id'] !== $classId) {
                continue;
            }
            foreach ($class[$kind] as $j => $entry) {
                if ($entry['id'] === $id) {
                    array_splice($classes[$i][$kind], $j, 1);
                    $this->store(json_encode($classes));
                    return;
                }
            }
            throw new GC2Exception(ucfirst(rtrim($kind, 's')) . " not found", 404, null, strtoupper(rtrim($kind, 's')) . "_NOT_FOUND");
        }
        throw new GC2Exception("Class not found", 404, null, "CLASS_NOT_FOUND");
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
        $arr = array_map([self::class, 'normalizeClass'], $arr);
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
        if (!isset($arr['name'])) {
            $arr['name'] = "Unnamed Class";
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

        // Normalize all three inputs to the new styles[]/labels[] format before merging, so the
        // merge never mixes legacy flat keys with new-format keys and externally-edited legacy
        // values are not silently discarded.
        $existingClass = array_map([self::class, 'normalizeClass'], $existingClass);
        $cachedClass = array_map([self::class, 'normalizeClass'], $cachedClass);
        $newClass = array_map([self::class, 'normalizeClass'], $newClass);

        $mergedClass = $this->mergeClasses($cachedClass, $existingClass, $newClass);

        $merged['_key_'] = $this->layer;
        $merged['class'] = self::ensureIds($mergedClass);
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
    /**
     * Removes every `id` key, recursively, from a value. Used to make comparisons
     * between stored classes (which carry server-assigned ids) and wizard-cache
     * classes (which do not) id-agnostic.
     */
    private static function stripIds(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        unset($value['id']);
        foreach ($value as $key => $item) {
            $value[$key] = self::stripIds($item);
        }
        return $value;
    }

    static function mergeClasses(array $cachedClass, array $existingClass, array $newClass): array
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
                    // Only update property if not changed externally (existing == cached).
                    // Server-assigned ids never count as an external edit — the wizard
                    // cache does not carry them.
                    if (self::stripIds($existingVal) === self::stripIds($cachedVal)) {
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
        $classes = array_map([self::class, 'normalizeClass'], $this->readRawClasses());
        $classes[] = ["name" => "Unnamed class"];
        $this->store(json_encode(self::ensureIds($classes), JSON_UNESCAPED_UNICODE));
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
        $classes = array_map([self::class, 'normalizeClass'], $this->readRawClasses());
        foreach ((array)$data as $k => $v) {
            if ($k === 'id') {
                continue; // the client's positional id must never overwrite the stored fixed id
            }
            $classes[$id][$k] = $v;
        }
        $this->store(json_encode(self::ensureIds($classes), JSON_UNESCAPED_UNICODE));
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
        $classes = array_map([self::class, 'normalizeClass'], $this->readRawClasses());
        array_splice($classes, $id, 1);
        $this->store(json_encode(self::ensureIds($classes), JSON_UNESCAPED_UNICODE));
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
            $this->execute($res);
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $rows = $this->fetchAll($res, "assoc");
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
            if ($row['value'] === null) {
                $row['value'] = '';
            }
            if ($type == "number" || $type == "int") {
                $expression = "[$field]={$row['value']}";
            }
            if ($type == "text" || $type == "string") {
                $expression = "'[$field]'='{$row['value']}'";
            }
            if ($data->custom->colorramp !== false && $data->custom->colorramp != "-1") {
                $c = current($colorBrewer);
                next($colorBrewer);
            } else {
                $c = null;
            }
            $cArr[$key] = self::createClass($geometryType, $row['value'], $expression, ($key * 10) + 10, $c, $data);
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
        $this->execute($res);
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
        $this->execute($res);
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
        $styles = [(object)[
            "sortid" => 10,
            "name" => "Symbol 1",
            "color" => $color,
            "outlinecolor" => !empty($outlineColor) ? $outlineColor : "",
            "symbol" => $symbol,
            "angle" => !empty($data->angle) ? $data->angle : "",
            "size" => $size,
            "width" => !empty($data->lineWidth) ? $data->lineWidth : "",
            "opacity" => !empty($data->opacity) ? $data->opacity : "",
            "gap" => !empty($data->gap) ? $data->gap : "",
            "minsize" => !empty($data->minsize) ? $data->minsize : "",
            "maxsize" => !empty($data->maxsize) ? $data->maxsize : "",
            "offsetx" => !empty($data->offsetx) ? $data->offsetx : "",
            "offsety" => !empty($data->offsety) ? $data->offsety : "",
            "polaroffsetr" => !empty($data->polaroffsetr) ? $data->polaroffsetr : "",
            "polaroffsetd" => !empty($data->polaroffsetd) ? $data->polaroffsetd : "",
        ]];
        if (!empty($data->overlayColor) || !empty($data->overlaySymbol) || !empty($data->overlaySize) || !empty($data->overlayOpacity)) {
            $styles[] = (object)[
                "sortid" => 20,
                "name" => "Symbol 2",
                "color" => !empty($data->overlayColor) ? $data->overlayColor : "",
                "outlinecolor" => "",
                "symbol" => !empty($data->overlaySymbol) ? $data->overlaySymbol : "",
                "size" => !empty($data->overlaySize) ? $data->overlaySize : "",
                "width" => "",
                "opacity" => !empty($data->overlayOpacity) ? $data->overlayOpacity : "",
            ];
        }
        $labels = [];
        if (!empty($data->labelText)) {
            $labels[] = (object)[
                "sortid" => 10,
                "name" => "Label 1",
                "on" => true,
                "text" => $data->labelText,
                "size" => !empty($data->labelSize) ? $data->labelSize : "",
                "color" => !empty($data->labelColor) ? $data->labelColor : "",
                "position" => !empty($data->labelPosition) ? $data->labelPosition : "",
                "font" => !empty($data->labelFont) ? $data->labelFont : "",
                "fontweight" => !empty($data->labelFontWeight) ? $data->labelFontWeight : "",
                "angle" => !empty($data->labelAngle) ? $data->labelAngle : "",
                "backgroundcolor" => !empty($data->labelBackgroundcolor) ? $data->labelBackgroundcolor : "",
                "force" => !empty($data->force),
                "outlinecolor" => !empty($data->label_outlinecolor) ? $data->label_outlinecolor : "",
                "buffer" => !empty($data->label_buffer) ? $data->label_buffer : "",
                "repeatdistance" => !empty($data->label_repeatdistance) ? $data->label_repeatdistance : "",
                "backgroundpadding" => !empty($data->label_backgroundpadding) ? $data->label_backgroundpadding : "",
                "offsetx" => !empty($data->label_offsetx) ? $data->label_offsetx : "",
                "offsety" => !empty($data->label_offsety) ? $data->label_offsety : "",
                "expression" => !empty($data->label_expression) ? $data->label_expression : "",
                "maxsize" => !empty($data->label_maxsize) ? $data->label_maxsize : "",
                "minfeaturesize" => !empty($data->label_minfeaturesize) ? $data->label_minfeaturesize : "",
                "minscaledenom" => !empty($data->label_minscaledenom) ? $data->label_minscaledenom : "",
                "maxscaledenom" => !empty($data->label_maxscaledenom) ? $data->label_maxscaledenom : "",
            ];
        }
        return (object)[
            "sortid" => $sortid,
            "name" => $name,
            "expression" => $expression,
            "styles" => $styles,
            "labels" => $labels,
        ];
    }
}