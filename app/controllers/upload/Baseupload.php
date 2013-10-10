<?php
namespace app\controllers\upload;

use \app\inc\Input;
use \app\conf\Connection;
use \app\conf\App;

abstract class Baseupload extends \app\inc\Controller
{
    protected $file;
    protected $safeFileWithOutSchema;
    public $response;

    function __construct()
    {
        $exts = array("shp", "tab", "gml", "geojson");
        $this->file = App::$param['path'] . "/app/tmp/" . Connection::$param["postgisschema"] . "_" . time();
        $this->response['uploaded'] = true;
        if (Input::get('name')) {
            $this->safeFile = Input::get('name');
        } else {
            $this->safeFile = $_FILES['shp']['name'];
            foreach ($exts as $ext) {
                $this->safeFile = str_replace(".{$ext}", "", $this->safeFile);
            }
        }
        $this->safeFile = $this->safeFileWithOutSchema = \app\inc\Model::toAscii($this->safeFile, array(), "_");
        $this->safeFile = Connection::$param["postgisschema"] . "." . $this->safeFile;
    }

    abstract function post_index();
}