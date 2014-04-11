<?php
namespace app\controllers\upload;

use \app\conf\App;
use \app\inc\Response;
use \app\conf\Connection;
use \app\inc\Session;
use \app\models\Table;

class Processraster extends \app\inc\Controller
{
    public function get_index()
    {
        $dir = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/__rasters";
        $safeName = \app\inc\Model::toAscii($_REQUEST['name'], array(), "_");

        if (is_numeric($safeName[0])) {
            $safeName = "_" . $safeName;
        }

        $srid = ($_REQUEST['srid']) ? : "4326";

        $cmd = "raster2pgsql " .
            "-s " .
            $srid .
            " -I -C -M " .
            $dir."/".$_REQUEST['file'] .
            " -F " .
            " -t 100x100 " .
            Connection::$param["postgisschema"] . "." . $safeName .
            " | psql " .
            Connection::$param["postgisdb"] .
            " -U " .
            Connection::$param["postgisuser"] .
            " -h " .
            Connection::$param["postgishost"];

        //echo $cmd;
        exec($cmd . ' 2>&1', $out, $err);

        if ($err[0] == "") {
            $response['success'] = true;
            $response['message'] = "Raster layer <b>{$safeName}</b> is created";
        } else {

            $response['success'] = false;
            $response['message'] = "Some thing went wrong. Check the log.";
            Session::createLog($err, $_REQUEST['file']);
        }
        $response['cmd'] = $cmd;
        return Response::json($response);
    }
}