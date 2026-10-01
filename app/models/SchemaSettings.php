<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\models;

use app\inc\Connection;
use app\inc\Model;

/**
 * settings.schema_settings — the tile settings of the merged per-schema tileset
 * (<schema> and <schema>.mvt), in a def JSONB of the same shape as a layer's.
 *
 * The row deliberately outlives its schema: dropping and recreating a schema is
 * a normal workflow, and the settings should survive it. That is safe by
 * construction, because Mapcachefile's per-schema loop iterates the schemas
 * that actually have layers, so a row for an absent schema is never read.
 *
 * This class knows nothing about XML or HTTP: Mapcachefile resolves the row into
 * config values, and the v4 controller validates what may enter it.
 */
final class SchemaSettings extends Model
{
    public function __construct(?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $schema): ?array
    {
        $res = $this->prepare('SELECT schema, def, created FROM settings.schema_settings WHERE schema = :schema');
        $this->execute($res, ['schema' => $schema]);
        return $this->fetchRow($res) ?: null;
    }

    /**
     * Every row, keyed by schema name. Mapcachefile reads this once per config
     * generation rather than querying per schema.
     *
     * Answers an empty list when the table is not there yet. Mapcachefile calls
     * this unconditionally, so on an install where the code lands before
     * `migration/run.php` has run, raising here would stop EVERY database from
     * being able to regenerate a config that worked before — a deployment-order
     * footgun out of all proportion to the feature. Missing settings simply mean
     * the fallbacks, which is exactly the old behaviour.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $res = $this->prepare("SELECT to_regclass('settings.schema_settings') IS NOT NULL AS present");
        $this->execute($res);
        if (!$this->fetchRow($res)['present']) {
            return [];
        }
        $res = $this->prepare('SELECT schema, def, created FROM settings.schema_settings');
        $this->execute($res);
        $out = [];
        foreach ($this->fetchAll($res, 'assoc') as $row) {
            $out[$row['schema']] = $row;
        }
        return $out;
    }

    /**
     * Merge $values into the schema's def, creating the row when absent. A value
     * of null removes that key, returning it to its fallback — which is why this
     * cannot be a plain jsonb concatenation in SQL.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed> the stored row
     */
    public function patch(string $schema, array $values): array
    {
        $current = $this->get($schema);
        $def = $current && $current['def'] ? (array)json_decode($current['def'], true) : [];
        foreach ($values as $key => $value) {
            if ($value === null) {
                unset($def[$key]);
            } else {
                $def[$key] = $value;
            }
        }
        $res = $this->prepare('INSERT INTO settings.schema_settings (schema, def) VALUES (:schema, :def)
                               ON CONFLICT (schema) DO UPDATE SET def = EXCLUDED.def
                               RETURNING schema, def, created');
        $this->execute($res, ['schema' => $schema, 'def' => json_encode($def)]);
        return $this->fetchRow($res);
    }

    public function delete(string $schema): void
    {
        $res = $this->prepare('DELETE FROM settings.schema_settings WHERE schema = :schema');
        $this->execute($res, ['schema' => $schema]);
    }
}
