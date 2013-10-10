<?php
namespace app\controllers\upload;

use \app\inc\Response;
use \app\inc\Input;

class Shape extends Baseupload
{
    public function post_index()
    {
        if (move_uploaded_file($_FILES['shp']['tmp_name'], $this->file . ".shp")) {
        } else {
            $this->response['uploaded'] = false;
        }
        if (move_uploaded_file($_FILES['dbf']['tmp_name'], $this->file . ".dbf")) {
        } else {
            $this->response['uploaded'] = false;
        }
        if (move_uploaded_file($_FILES['shx']['tmp_name'], $this->file . ".shx")) {
        } else {
            $this->response['uploaded'] = false;
        }

        if ($this->response['uploaded']) {
            $shapeFile = new \app\models\Shape($this->safeFile, Input::get('srid'), $this->file, true);
            $this->response = $shapeFile->loadInDb();
        }
        $this->response['success'] = true;
        return Response::json($this->response);
    }
}