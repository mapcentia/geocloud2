<?php
/**
 * Long description for file
 *
 * Long description for file (if any)...
 *
 * @category   API
 * @package    app\controllers
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 * @since      File available since Release 2013.1
 *
 */

namespace app\controllers\upload;

use app\inc\Controller;
use app\inc\Model;
use app\inc\Response;
use app\conf\Connection;
use app\inc\Session;
use app\models\Table;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

class Processbitmap extends Controller
{
    /**
     * @throws PhpfastcacheInvalidArgumentException|PDOException
     */
    public function get_index(): array
    {
        $safeName = Model::toAscii($_REQUEST['name'], array(), "_");
        if (is_numeric($safeName[0])) {
            $safeName = "_" . $safeName;
        }
        $srid = ($_REQUEST['srid']) ?: "4326";
        $file = $_REQUEST['file'];
        $key = Connection::$param["postgisschema"] . "." . $safeName . ".rast";
        // Create new table
        $table = new Table($safeName);
        $table->createAsRasterTable($srid);
        // Set bitmapsource
        $join = new Table("settings.geometry_columns_join");
        $data['_key_'] = $key;
        $data['bitmapsource'] = $file;
        $join->updateRecord($data, "_key_");
        $response['success'] = true;
        $response['message'] = "Layer <b>{$safeName}</b> is created";
        return Response::json($response);
    }
}