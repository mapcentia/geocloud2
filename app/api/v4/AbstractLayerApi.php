<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
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
     */
    protected function writeMapFiles(): void
    {
        $mapfile = new MapfileModel(connection: $this->connection);
        $mapfile->writeMapfile($mapfile->generateWms(), 'wms');
        $mapfile->writeMapfile($mapfile->generateWfs(), 'wfs');
    }
}
