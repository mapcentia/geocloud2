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
            " -I -C -M -d" .
            $dir . "/" . $_REQUEST['file'] .
            " -F " .
            " -t 100x100 " .
            Connection::$param["postgisschema"] . "." . $safeName .
            " | psql " .
            Connection::$param["postgisdb"] .
            " -U " .
            Connection::$param["postgisuser"] .
            " -h " .
            Connection::$param["postgishost"];

        exec($cmd . ' 2>&1', $out);
        $err = false;
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
        } else {
            $response['success'] = false;
            $response['message'] = "Some thing went wrong. Check the log.";
            Session::createLog($out, $_REQUEST['file']);
        }
        $response['cmd'] = $cmd;
        return Response::json($response);
    }
}