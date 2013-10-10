<?php
namespace app\controllers\upload;

use \app\inc\Response;
use \app\inc\Input;
use \app\conf\Connection;

class Mapinfo extends Baseupload
{
    public function post_index()
    {
        if (move_uploaded_file($_FILES['tab']['tmp_name'], $this->file . ".tab")) {
        } else {
            $this->response['uploaded'] = false;
        }
        if (move_uploaded_file($_FILES['map']['tmp_name'], $this->file . ".map")) {
        } else {
            $this->response['uploaded'] = false;
        }
        if (move_uploaded_file($_FILES['id']['tmp_name'], $this->file . ".id")) {
        } else {
            $this->response['uploaded'] = false;
        }
        if (move_uploaded_file($_FILES['dat']['tmp_name'], $this->file . ".dat")) {
        } else {
            $this->response['uploaded'] = false;
        }

        if ($this->response['uploaded']) {

            switch (Input::get('type')) {
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
            }
            //$skip = (Input::get('skipfailures')) ? "" : "-skipfailures";
            $cmd = "PGCLIENTENCODING=LATIN1 ogr2ogr {$skip} " .
                "-overwrite ".
                "-lco 'GEOMETRY_NAME=the_geom' " .
                "-lco 'FID=gid' " .
                "-nlt '{$type}' " .
                "-a_srs 'EPSG:{$_REQUEST['srid']}' " .
                "-f 'PostgreSQL' PG:'user=postgres dbname=" . Connection::$param["postgisdb"] . " active_schema=".Connection::$param["postgisschema"]."' " .
                $this->file . ".tab " .
                "-nln " . $this->safeFileWithOutSchema;
            //echo $cmd;
            exec($cmd . ' 2>&1', $out, $err);
            $result = $out[0];
            if ($result == "") {
                $this->response['success'] = true;
                $this->response['message'] = "MapInfo TAB file uploaded and processed";
            } else {
                $this->response['success'] = false;
                $this->response['message'] = $result;
            }
        }
        return Response::json($this->response);
    }
}