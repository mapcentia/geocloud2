<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Connection;
use app\inc\Model;
use app\inc\Cache;
use Psr\Cache\InvalidArgumentException;


/**
 * Class Tile
 * @package app\models
 */
class Tile extends Model
{
    public string $table;

    function __construct(string $table, ?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
        $this->table = $table;
    }

    /**
     * Clears cached metadata related to schema changes for the current table.
     *
     * This method identifies the relevant cache keys based on the table's schema and name,
     * constructs a set of patterns, and deletes all matching cache entries to ensure
     * consistency after schema modifications.
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function clearCacheOnSchemaChanges(): void
    {
        $split = explode('.', $this->table);
        $relName = $split[0] . '.' . $split[1];
        $patterns = [
            $this->postgisdb . '_' . $relName  . '_metadata_*',
        ];
        Cache::deleteByPatterns($patterns);
    }

    /**
     * Retrieves and processes data from the settings.geometry_columns_join table.
     *
     * @return array An associative array containing two keys:
     *               'success' (boolean) indicating the operation's status,
     *               and 'data' (array) containing the processed data retrieved from the database.
     */
    public function get(): array
    {
        $sql = "SELECT def FROM settings.geometry_columns_join WHERE _key_=:layer";
        $res = $this->prepare($sql);
        $this->execute($res, ['layer' => $this->table]);
        $row = $this->fetchRow($res);
        $response['success'] = true;
        $arr = !empty($row['def']) ? json_decode($row['def'], true): []; // Cast stdclass to array
        foreach ($arr as $key => $value) {
            if ($value === null) { // Never send null to client
                $arr[$key] = "";
            }
        }
        $response['data'] = [$arr];
        return $response;
    }

    /**
     * Updates the "def" field in the settings.geometry_columns_join table with new schema data.
     *
     * @param object $data An object containing the new schema data to be updated.
     *                     The object's properties should correspond to the keys in the predefined schema.
     *
     * @return array An associative array containing two keys:
     *               'success' (boolean) indicating whether the update operation was successful,
     *               and 'message' (string) with a confirmation message.
     * @throws InvalidArgumentException
     */
    public function update(object $data): array
    {
        $this->clearCacheOnSchemaChanges();
        $schema = [
            "theme_column",
            "label_column",
            "opacity",
            "label_max_scale",
            "label_min_scale",
            "cluster",
            "meta_tiles",
            "meta_size",
            "meta_buffer",
            "ttl",
            "auto_expire",
            "maxscaledenom",
            "minscaledenom",
            "symbolscaledenom",
            "geotype",
            "offsite",
            "format",
            "lock",
            "layers",
            "bands",
            "cache",
            "s3_tile_set",
            "label_no_clip",
            "polyline_no_clip",
        ];
        $oldData = $this->get();
        $newData = [];
        foreach ($schema as $k) {
            $newData[$k] = (isset($data->$k) || (property_exists($data, $k) && $data->$k === null)) ? $data->$k : $oldData["data"][0][$k];
        }
        $newData = json_encode($newData);
        $sql = "UPDATE settings.geometry_columns_join SET def='$newData' WHERE _key_=:layer";
        $res = $this->prepare($sql);
        $this->execute($res, ['layer' => $this->table]);
        $response['success'] = true;
        $response['message'] = "Def updated";
        return $response;
    }
}