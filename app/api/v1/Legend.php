<?php
/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v1;

use app\conf\App;
use app\inc\Cache;
use app\inc\Controller;
use app\inc\Globals;
use app\inc\Input;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;


/**
 * Class Legend
 * @package app\api\v1
 */
class Legend extends Controller
{
    /**
     * @var mixed
     */
    private $legendArr;

    function __construct()
    {
        parent::__construct();
        $path = App::$param['path'] . "/app/wms/mapfiles/";
        if (Input::get("l")) {
            $cacheType = "legend";
            $cacheId = md5(Input::getPath()->part(5) . "_" . $cacheType . "_" . Input::get("l"));
            $CachedString = Cache::getItem($cacheId);
            if ($CachedString != null && $CachedString->isHit()) {
                $this->legendArr = $CachedString->get();
            } else {
                $layerNames = explode(";", Input::get("l"));
                $layerNames = array_reverse($layerNames);
                $temp = $layerNames;
                $newLayerNames = $arr = array();
                // Check if schema is given as param and get the layer names
                foreach ($temp as $layerName) {
                    $splitName = explode(".", $layerName);
                    if ($splitName[0] !== "gc2_group") {
                        if (sizeof($splitName) < 2) {
                            $mapFile = Input::getPath()->part(5) . "_" . $splitName[0] . "_wms.map";
                            if (file_exists($path . $mapFile)) {
                                $map = new \mapObj($path . $mapFile);
                                for ($i = 0; $i < $map->numlayers; $i++) {
                                    $arr[] = $map->getLayer($i)->name;
                                }
                            }
                        } else {
                            $newLayerNames[] = $layerName;
                        }
                        $newLayerNames = array_merge($newLayerNames, $arr);
                    }
                }
                foreach ($newLayerNames as $layerName) {
                    $layerNameWithOutPrefix = str_replace("v:", "", $layerName);
                    $splitName = explode(".", $layerNameWithOutPrefix);
                    $mapFile = Input::getPath()->part(5) . "_" . $splitName[0] . "_wms.map";
                    $map = new \mapObj($path . $mapFile);
                    $layer = $map->getLayerByName($layerNameWithOutPrefix);
                    if ($layer) {
                        $this->legendArr[$layerName]['title'] = $layer->metadata->get("ows_title");
                        if ($layer->metadata->get("wms_get_legend_url")) {
                            $icon = imagecreatefrompng($layer->metadata->get("wms_get_legend_url"));
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
                            for ($i = 0; $i < $layer->numclasses; $i++) {
                                $class = $layer->getClass($i);
                                $icon = $class->createLegendIcon($map, $layer, 17, 17);
                                $data = base64_encode($icon->getBytes());
                                $this->legendArr[$layerName]['classes'][$i]['img'] = $data;
                                $this->legendArr[$layerName]['classes'][$i]['name'] = $class->name;
                                $this->legendArr[$layerName]['classes'][$i]['expression'] = $class->getExpressionString();

                            }
                        }
                    }
                }
                $CachedString->set($this->legendArr)->expiresAfter(Globals::$cacheTtl);
                $CachedString->addTags([$cacheType, Input::getPath()->part(5)]);
                Cache::save($CachedString);
            }
        }
    }

    public function get_png()
    {
        $path = App::$param['path'] . "/app/wms/mapfiles/";
        if (!Input::getPath()->part(6)) {
            $response['success'] = false;
            $response['message'] = "Need to specify schema when using PNG";
            $response['code'] = 400;
            return $response;
        }
        $mapFile = Input::getPath()->part(5) . "_" . Input::getPath()->part(6) . "_wms.map";
        $map = new \mapObj($path . $mapFile);
        if (is_array($this->legendArr)) {
            foreach ($this->legendArr as $key => $layer) {
                $layer = $map->getLayerByName($key);
                if ($layer) {
                    $layer->status = MS_ON;
                }
            }
        }
        header('Content-type: image/png');
        exit($map->drawLegend()->getBytes());
    }

    /**
     * @return array<string>
     */
    public function get_html(): array
    {
        $html = "";
        if (is_array($this->legendArr)) {
            foreach ($this->legendArr as $layer) {
                //$html .= "<div class=\"legend legend-container\"><div class=\"legend legend-header\"><b>" . $layer['title'] . "<b></div>";
                $html .= "<table class=\"legend legend-body\">";
                if (is_array($layer['classes'])) {
                    foreach ($layer['classes'] as $class) {
                        if ($class['name']) {
                            $html .= "<tr><td style=\"padding: 3px\" class=\"legend img\"><img alt=\"\" src=\"data:image/png;base64, {$class['img']}\"></td>";
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
     * @return array<mixed>
     */
    public function get_json(): array
    {
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