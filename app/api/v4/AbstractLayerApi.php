<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Input;
use app\models\Classification;
use app\models\Layer as LayerModel;
use app\models\Mapcachefile as MapcachefileModel;
use app\models\Mapfile as MapfileModel;
use Throwable;

/**
 * Shared behavior for the api/v4/layers/... controllers: layer-key validation,
 * authorization, layer existence check and mapfile regeneration.
 */
abstract class AbstractLayerApi extends AbstractApi
{
    public ?string $layerKey = null;
    protected Classification $classification;

    /**
     * Validates the layer key, authorizes via initiate(), ensures the layer row
     * exists and prepares the Classification model for the layer.
     *
     * @throws GC2Exception
     */
    protected function initiateLayer(string $layer): void
    {
        $parts = explode('.', $layer);
        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new GC2Exception("Layer key must be schema.table.geometry_column", 400, null, "INVALID_LAYER_KEY");
        }
        $this->initiate(schema: $parts[0], relation: $parts[1]);
        $layerModel = new LayerModel(connection: $this->connection);
        $layerModel->insertDefaultMeta();
        if (!$layerModel->doesLayerExist($layer)) {
            throw new GC2Exception("Layer not found", 404, null, "LAYER_NOT_FOUND");
        }
        $this->layerKey = $layer;
        $this->classification = new Classification(table: $layer, connection: $this->connection);
    }

    /**
     * Regenerates the WMS and WFS mapfiles — the API equivalent of the GUI's writeFiles().
     *
     * Mapfile generation is scoped to $connection->schema (defaults to 'public' otherwise), so
     * we clone the connection with the target schema before instantiating the model —
     * otherwise mutations would regenerate the 'public' mapfiles instead of the layer's.
     *
     * @param string|null $schema Schema to regenerate mapfiles for. Defaults to the current
     *                            layer's schema (derived from $this->layerKey) when omitted.
     */
    protected function writeMapFiles(?string $schema = null): void
    {
        $connection = clone $this->connection;
        $connection->schema = $schema ?? explode('.', $this->layerKey)[0];
        $mapfile = new MapfileModel(connection: $connection);
        $mapfile->writeMapfile($mapfile->generateWms(), 'wms');
        $mapfile->writeMapfile($mapfile->generateWfs(), 'wfs');
    }

    /**
     * The subset of layer `def` keys whose value ends up in the generated MapCache config
     * (see Mapcachefile::layerSettings). Classes, styles and labels — i.e. the styling that goes
     * into the WMS/WFS mapfiles — do NOT appear in the MapCache config, so they never require it to
     * be rewritten (a styling change only makes already-cached tiles stale, which is a cache-delete
     * concern, not a config concern).
     */
    public const array MAPCACHE_RELEVANT_KEYS = [
        'cache', 'format', 'ttl', 'auto_expire', 'meta_size', 'meta_buffer', 'layers', 's3_tile_set',
    ];

    /**
     * True when a layer-properties update touches a key that changes the MapCache config, i.e. when
     * rewriting it is warranted. Unlike the mapfiles, the MapCache config covers every layer in the
     * database and is expensive to generate, so this gate keeps it from being rewritten on
     * styling-only or otherwise MapCache-irrelevant changes.
     *
     * @param array<string,mixed>|null $properties the layer "properties" (def) in the request
     */
    public static function affectsMapCache(?array $properties): bool
    {
        return is_array($properties)
            && !empty(array_intersect(array_keys($properties), self::MAPCACHE_RELEVANT_KEYS));
    }

    /**
     * Regenerates the per-database MapCache config (all layers in the database). Best-effort: a
     * failure is logged, not surfaced, so a tilecache-config hiccup never fails an otherwise
     * successful layer update. Mapcachefile::write() is a no-op when the content is unchanged.
     */
    protected function writeMapCacheFile(): void
    {
        try {
            $model = new MapcachefileModel(connection: $this->connection);
            $model->write($model->generate());
        } catch (Throwable $e) {
            error_log('MapCache config regeneration failed: ' . $e);
        }
    }

    /**
     * Rejects a POST body that is an empty JSON array.
     *
     * Symfony's Collection validator treats an empty array as trivially valid when every field
     * is Optional, so `POST []` would otherwise pass validate() as a no-op. For styles/labels
     * that additionally made Classification::insertEntries() report every existing entry's id
     * (array_slice(..., -count($entries)) with count 0 slices from index 0, i.e. the whole
     * array), so the 201 Location would wrongly claim pre-existing entries were just created.
     *
     * @throws GC2Exception
     */
    protected function rejectEmptyArrayPost(?string $body): void
    {
        if (Input::getMethod() !== 'post') {
            return;
        }
        $data = $body === null ? null : json_decode($body, true);
        if (is_array($data) && $data === []) {
            throw new GC2Exception("POST body must not be an empty array", 400, null, "EMPTY_ARRAY_BODY");
        }
    }
}
