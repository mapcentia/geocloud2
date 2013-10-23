<?php
namespace app\controllers\upload;

use app\conf\App;
use \app\inc\Response;
use \app\inc\Input;
use \app\conf\Connection;

class Process extends \app\inc\Controller
{
    public function get_index()
    {
        $dir = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"];
        $safeName = \app\inc\Model::toAscii($_REQUEST['name'], array(), "_");
        $srid = ($_REQUEST['srid']) ? : "4326";

        switch ($_REQUEST['type']) {
            case "Point":
                $type = "point";
                break;
            case "Polygon":
                $type = "multipolygon";
                break;
            case "Line":
                $type = "multilinestring";
                break;
            case "Geometry":
                $type = "geometry";
                break;
            default:
                $type = "PROMOTE_TO_MULTI";
                break;
        }
        $cmd = "PGCLIENTENCODING=LATIN1 ogr2ogr " .
            "-overwrite " .
            "-lco 'GEOMETRY_NAME=the_geom' " .
            "-lco 'FID=gid' " .
            "-a_srs 'EPSG:{$srid}' " .
            "-f 'PostgreSQL' PG:'user=postgres dbname=" . Connection::$param["postgisdb"] . " active_schema=" . Connection::$param["postgisschema"] . "' " .
            "'" . $dir . "/" . $_REQUEST['file'] . "' " .
            "-nln {$safeName} " .
            "-nlt {$type}";

        //echo $cmd;
        exec($cmd . ' 2>&1', $out, $err);
        if ($out[0] == "") {
            $response['success'] = true;
            $response['message'] = "Layer <b>{$safeName}</b> is created";
        } else {
            $response['success'] = false;
            $response['message'] = $out[0];
        }
        return Response::json($response);
    }
}