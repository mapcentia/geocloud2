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

use \app\conf\App;
use \app\inc\Response;
use \app\conf\Connection;
use \app\inc\Session;

class Processraster extends \app\inc\Controller
{
    /**
     * Processraster constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    public function get_index()
    {
        $dir = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/__raster";
        $safeName = \app\inc\Model::toAscii($_REQUEST['name'], array(), "_");

        if (is_numeric($safeName[0])) {
            $safeName = "_" . $safeName;
        }

        $srid = ($_REQUEST['srid']) ?: "4326";

        $cmd = "raster2pgsql " .
            "-s " .
            $srid .
            " -I -C -M -d " .
            $dir . "/" . $_REQUEST['file'] .
            " -F" .
            " -t 100x100 " .
            Connection::$param["postgisschema"] . "." . $safeName .
            " | PGPASSWORD=" . Connection::$param["postgispw"] .
            " psql " . Connection::$param["postgisdb"] .
            " -U " .
            Connection::$param["postgisuser"] .
            " -h " .
            Connection::$param["postgishost"] .
            " -p " .
            Connection::$param["postgisport"];

        exec($cmd . ' 2>&1', $out);
        $err = false;

        // This is a HACK. raster2pgsql doesn't return the error to stdout or stderr.
        if (!isset($out[0])) {
            $out[0] = "ERROR: Unable to read raster file";
        }

        foreach ($out as $line) {
            if (strpos($line, 'ERROR') !== false) {
                $err = true;
                break;
            }
        }
        if (!$err) {
            $response['success'] = true;
            $response['cmd'] = $cmd;
            $response['message'] = "Raster layer <b>{$safeName}</b> is created";

            $key = Connection::$param["postgisschema"] . "." . $safeName . ".rast";
            $class = new \app\models\Classification($key);
            $arr = $class->getAll();

            if (empty($arr['data'])) {
                $class->insert();
                $class->update("0", \app\models\Classification::createClass("POLYGON"));
            }
           /* if ($_REQUEST['displayfile']) {
                $join = new Table("settings.geometry_columns_join");
                $json = '{"data":{"bitmapsource":"' . $_REQUEST['file'] . '","_key_":"' . $key . '"}}';
                $data = (array)json_decode(urldecode($json));
                $join->updateRecord($data, "_key_");
            }*/

        } else {
            $response['success'] = false;
            $response['message'] = "Some thing went wrong. Check the log.";
            Session::createLog($out, $_REQUEST['file']);
        }
        $response['cmd'] = $cmd;
        return Response::json($response);
    }
}