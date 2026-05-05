<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\controllers;

use app\inc\Connection;
use app\inc\Controller;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

class Mapfile extends Controller
{
    /**
     * @return array<array<bool|string>>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_index(): array
    {
        $model = new \app\models\Mapfile(connection: new Connection);
        return [
            $model->writeMapfile($model->generateWms(), 'wms'),
            $model->writeMapfile($model->generateWfs(), 'wfs'),
        ];
    }
}
