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
use app\models\Mapfile as MapfileModel;

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
