<?php
/**
 * Long description for file
 *
 * Long description for file (if any)...
 *  
 * @category   API
 * @package    app\api\v1
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 * @since      File available since Release 2013.1
 *  
 */

namespace app\api\v1;

use \app\conf\App;
use \app\inc\Session;
use \app\models\Layer;
use \GuzzleHttp\Client;

class Legend extends \app\inc\Controller
{
    private $legendArr;

    function __construct()
    {
        parent::__construct();

    }

    /**
     * @return array
     * @throws \PDOException
     */
    private function createLegendObj()
    {

        $response = [];

        $path = App::$param['path'] . "/app/wms/mapfiles/";

        $client = new Client([
            'timeout' => 10,
        ]);

        if (\app\inc\Input::get("l")) {

            $layerNames = explode(";", \app\inc\Input::get("l"));

            $layerNames = array_reverse($layerNames);

            $meta = new Layer();

            try {

                $layers = $meta->getAll(implode(",", $layerNames), Session::isAuth(), false, false,  false, \app\inc\Session::getUser())["data"];

            } catch (\PDOException $e) {

                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = $e->getCode();
                return $response;

            }

            foreach ($layers as $layer) {

                $layerName = $layer["f_table_schema"] . "." . $layer["f_table_name"];

                // Sort classes
                $classes = json_decode($layer['class'], true);

                foreach ($classes as $key => $row) {
                    $sortid[$key] = $row['sortid'];
                }
                array_multisort($sortid, SORT_ASC, $classes);

                $numClass = sizeof($classes);

                $mapFile = $path . \app\inc\Input::getPath()->part(5) . "_" . $layer["f_table_schema"] . "_wms.map";

                $this->legendArr[$layerName]['title'] = !empty($layer["wms_title"]) ? $layer["wms_title"] : "";

                if (isset($layer["wmssource"])) {

                    $wmsCon = str_replace(array("layers", "LAYERS"), "LAYER", $layer['wmssource']);

                    $icon = imagecreatefrompng($wmsCon . "&REQUEST=getlegendgraphic");
                    imagecolortransparent($icon, imagecolorallocatealpha($icon, 0, 0, 0, 127));
                    imagealphablending($icon, false);
                    imagesavealpha($icon, true);
                    ob_start();
                    imagepng($icon);
                    imagedestroy($icon);
                    $data = base64_encode(ob_get_clean());
                    $this->legendArr[$layerName]['classes'][0]['img'] = $data;
                    $this->legendArr[$layerName]['classes'][0]['name'] = "_gc2_wms_legend";
                    $this->legendArr[$layerName]['classes'][0]['expression'] = null;

                } else {

                    for ($i = 0; $i < $numClass; $i++) {

                        $iconUrl = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=" . $mapFile . "&MODE=legendicon&icon=" . $layerName . "," . $i;

                        try {

                            $content = $client->get($iconUrl);

                        } catch (\Exception $e) {

                            $response['success'] = false;
                            $response['message'] = $e->getMessage();
                            $response['code'] = $e->getCode();
                            return $response;

                        }

                        $data = base64_encode($content->getBody());

                        $this->legendArr[$layerName]['classes'][$i]['img'] = $data;
                        $this->legendArr[$layerName]['classes'][$i]['name'] = $classes[$i]["name"];
                        $this->legendArr[$layerName]['classes'][$i]['expression'] = $classes[$i]["expression"];

                    }
                }

            }

        }

        $response['success'] = true;
        $response['message'] = "";
        return $response;
    }

    /**
     * @return array
     */
    public function get_png()
    {
        $res = $this->createLegendObj();

        if (!$res["success"]) {
            return $res;
        }

        $path = App::$param['path'] . "/app/wms/mapfiles/";
        if (!\app\inc\Input::getPath()->part(6)) {
            $response['success'] = false;
            $response['message'] = "Need to specify schema when using PNG";
            $response['code'] = 400;
            return $response;
        }

        if (\app\inc\Input::get("l")) {
            $layerNames = explode(";", \app\inc\Input::get("l"));
            $mapFile = \app\inc\Input::getPath()->part(5) . "_" . \app\inc\Input::getPath()->part(6) . "_wms.map";
            $map = ms_newMapobj($path . $mapFile);
            foreach ($layerNames as $layerName) {
                $layer = $map->getLayerByName($layerName);
                if ($layer) {
                    $layer->status = MS_ON;
                }
            }
        }
        header('Content-type: image/png');
        $legend = $map->drawLegend();
        ob_start();
        $legend->saveImage("", $map);
        $data = ob_get_clean();
        exit($data);
    }

    /**
     * @return array
     */
    public function get_html()
    {
        $res = $this->createLegendObj();

        if (!$res["success"]) {
            return $res;
        }

        $html = "";

        if (is_array($this->legendArr)) {
            foreach ($this->legendArr as $layer) {
                //$html .= "<div class=\"legend legend-container\"><div class=\"legend legend-header\"><b>" . $layer['title'] . "<b></div>";
                $html .= "<table class=\"legend legend-body\">";
                if (is_array($layer['classes'])) {
                    foreach ($layer['classes'] as $class) {
                        if ($class['name']) {
                            $html .= "<tr><td style=\"padding: 3px\" class=\"legend img\"><img src=\"data:image/png;base64, {$class['img']}\"></td>";
                            $html .= "<td style=\"padding: 3px\" class=\"legend legend-text\">" . (($class['name'] == "_gc2_wms_legend") ? "" : htmlentities($class['name'])) . "</td></tr>";
                        }
                    }
                }
                $html .= "</table>";
            }
        }

        $response['html'] = $html;

        return $response;

    }

    /**
     * @return array
     */
    public function get_json()
    {
        $res = $this->createLegendObj();

        if (!$res["success"]) {
            return $res;
        }

        $json = array();
        $classes = array();
        if (is_array($this->legendArr)) {
            foreach ($this->legendArr as $key => $layer) {
                {
                    if (is_array($layer['classes'])) {
                        foreach ($layer['classes'] as $class) {
                            $classes[] = array(
                                "name" => $class['name'],
                                "expression" => $class['expression'],
                                "img" => $class['img']
                            );
                        }
                    }
                    $json[] = array("id" => $key, "classes" => $classes);
                    $classes = array();
                }
            }
        }
        return $json;
    }
}
