<?php
namespace app\api\v1;

use \app\conf\App;

class Legend extends \app\inc\Controller
{
    private $legendArr;

    function __construct()
    {
        $path = App::$param['path'] . "/app/wms/mapfiles/";
        $name = \app\inc\Input::getPath()->part(5) . "_" . \app\inc\Input::getPath()->part(6) . ".map";
        $map = ms_newMapobj($path . $name);
        $layerNames = explode(";", \app\inc\Input::get("l"));
        foreach ($layerNames as $layerName) {
            @$layer = $map->getLayerByName($layerName);
            if ($layer) {
                $this->legendArr[$layerName]['title'] = $layer->getMetaData("wms_title");
                for ($i = 0; $i < $layer->numclasses; $i++) {
                    $class = $layer->getClass($i);
                    $icon = $class->createLegendIcon(17, 17);
                    ob_start();
                    $icon->saveImage("", $map);
                    $data = base64_encode(ob_get_clean());
                    $this->legendArr[$layerName]['classes'][$i]['img'] = $data;
                    $this->legendArr[$layerName]['classes'][$i]['name'] = $class->name;
                }
            }
        }
    }

    function get_html()
    {
        $html = "";
        if (is_array($this->legendArr)) {
            foreach ($this->legendArr as $layer) {
                $html .= "<div class=\"legend legend-container\"><div class=\"legend legend-header\"><b>" . $layer['title'] . "<b></div>";
                $html .= "<table class=\"legend legend-body: 10px\">";
                if (is_array($layer['classes'])) {
                    foreach ($layer['classes'] as $class) {
                        $html .= "<tr><td class=\"legend img\"><img src=\"data:image/png;base64, {$class['img']}\"></td>";
                        $html .= "<td class=\"legend legend-text\">" . $class['name'] . "</td></tr>";
                    }
                }
                $html .= "</table></div>";
            }
        }
        $response['html'] = $html;
        echo \app\inc\Response::json($response);
    }
}