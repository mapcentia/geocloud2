<?php
namespace app\controllers;

class Baselayerjs extends \app\inc\Controller
{
    function __construct()
    {
        if (\app\conf\App::$param['baseLayers']) {
            echo "window.setBaseLayers = function () {";
            echo \app\conf\App::$param['baseLayers'];
            echo "};";
        }
        exit();
    }

    public function get_index()
    {

    }
}
