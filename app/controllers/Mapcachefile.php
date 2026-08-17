<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\controllers;

use app\inc\Connection;
use app\inc\Controller;

class Mapcachefile extends Controller
{
    /**
     * @return array<bool|string>
     */
    public function get_index(): array
    {
        $model = new \app\models\Mapcachefile(connection: new Connection);
        return $model->write($model->generate());
    }
}
