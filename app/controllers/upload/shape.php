<?php
namespace app\controllers\upload;

use \app\inc\Response;
use \app\inc\Input;

class Shape extends \app\inc\Controller
{
    private $file;

    function __construct()
    {
        global $basePath;
        $this->file = $basePath . "/app/tmp/" .\Connection::$param["postgisschema"] . "_" . time();
    }

    public function post_index()
    {
        $response['uploaded'] = true;

        if ($_REQUEST['name']) {
            $SafeFile = Input::get()->values('name');
        } else {
            $SafeFile = $_FILES['shp']['name'];
        }
        $SafeFile = str_replace("#", "No.", $SafeFile);
        $SafeFile = str_replace("-", "_", $SafeFile);
        $SafeFile = str_replace("$", "Dollar", $SafeFile);
        $SafeFile = str_replace("%", "Percent", $SafeFile);
        $SafeFile = str_replace("^", "", $SafeFile);
        $SafeFile = str_replace("&", "and", $SafeFile);
        $SafeFile = str_replace("*", "", $SafeFile);
        $SafeFile = str_replace("?", "", $SafeFile);
        $SafeFile = str_replace(".shp", "", $SafeFile);
        $SafeFile = strtolower($SafeFile);

        $SafeFile = \app\inc\postgis::toAscii($SafeFile, array(), "_");
        $SafeFile = \Connection::$param["postgisschema"] . "." . $SafeFile;

        if (move_uploaded_file($_FILES['shp']['tmp_name'], $this->file . ".shp")) {
        } else {
            $response['uploaded'] = false;
        }
        if (move_uploaded_file($_FILES['dbf']['tmp_name'], $this->file . ".dbf")) {
        } else {
            $response['uploaded'] = false;
        }
        if (move_uploaded_file($_FILES['shx']['tmp_name'], $this->file . ".shx")) {
        } else {
            $response['uploaded'] = false;
        }

        if ($response['uploaded']) {
            $shapeFile = new \app\models\Shape($SafeFile, Input::get('srid'), $this->file, Input::get('pdo'));
            $response = $shapeFile->loadInDb();
            //makeMapFile($_SESSION['screen_name']);
            //makeTileCacheFile($_SESSION['screen_name']);
        }
        $response['success'] = true;
        return Response::json($response);
    }
}