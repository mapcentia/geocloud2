<?php
namespace app\api\v1;

class Baselayerjs extends \app\inc\Controller
{
    function __construct()
    {
        if (\app\conf\App::$param['bingApiKey']) {
            echo "window.bingApiKey = '".\app\conf\App::$param['bingApiKey']."';\n";
        }
        if (\app\conf\App::$param['baseLayers']) {
            echo "window.setBaseLayers = ".json_encode(\app\conf\App::$param['baseLayers']).";\n";
        }
        if (\app\conf\App::$param['mapAttribution']) {
            echo "window.mapAttribution = '".\app\conf\App::$param['mapAttribution']."';\n";
        }
        exit();
    }
    public function get_index()
    {

    }
}
