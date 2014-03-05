<?php
namespace app\api\v1;

class Baselayerjs extends \app\inc\Controller
{
    function __construct()
    {
        if (\app\conf\App::$param['baseLayers']) {
            echo "window.bingApiKey = '".\app\conf\App::$param['bingApiKey']."';\n";
            echo "window.setBaseLayers = ".json_encode(\app\conf\App::$param['baseLayers']).";\n";
        }
        exit();
    }
    public function get_index()
    {

    }
}
