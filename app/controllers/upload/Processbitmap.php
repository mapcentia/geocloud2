<?php
namespace app\controllers\upload;

use \app\conf\App;
use \app\inc\Response;
use \app\conf\Connection;
use \app\inc\Session;
use \app\models\Table;

class Processbitmap extends \app\inc\Controller
{
    public function get_index()
    {
        $safeName = \app\inc\Model::toAscii($_REQUEST['name'], array(), "_");
        if (is_numeric($safeName[0])) {
            $safeName = "_" . $safeName;
        }

        $srid = ($_REQUEST['srid']) ? : "4326";
        $file = $_REQUEST['file'];
        $key = Connection::$param["postgisschema"] . "." . $safeName . ".rast";


        // Create new table
        $table = new Table($safeName);
        $res = $table->createAsRasterTable($srid);
        // Set bitmapsource
        $join = new Table("settings.geometry_columns_join");
        $json = '{"data":{"bitmapsource":"'.$file.'","_key_":"' . $key . '"}}';
        $data = (array)json_decode(urldecode($json));
        $join->updateRecord($data, "_key_");

        if ($res["success"]) {
            $response['success'] = true;
            $response['message'] = "Layer <b>{$safeName}</b> is created";
        } else {

            $response['success'] = false;
            $response['message'] = "Some thing went wrong. Check the log.";
            Session::createLog(array($res['message']), $_REQUEST['file']);
        }
        return Response::json($response);
    }
}