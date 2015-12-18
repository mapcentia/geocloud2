<?php
namespace app\api\v1;

use \app\inc\Input;
use \app\inc\Response;

class Staticmap extends \app\inc\Controller
{

    function __construct()
    {
    }

    public function get_png()
    {
        $this->get_file("png");
    }

    public function post_png()
    {
        $this->get_file("png");
    }

    public function get_jpg()
    {
        $this->get_file("jpg");
    }

    private function get_file($type)
    {
        include_once 'Cache_Lite/Lite.php';
        $db = Input::getPath()->part(5);
        $baseLayer = Input::get("baselayer");
        $layers = Input::get("layers");
        $center = Input::get("center");
        $zoom = Input::get("zoom");
        $size = Input::get("size");
        $sizeArr = explode("x", Input::get("size"));
        $bbox = Input::get("bbox");
        $sql = Input::get("sql");

        $id = $db . "_" . $baseLayer . "_" . $layers . "_" . $center . "_" . $zoom . "_" . $size . "_" . $bbox . "_" . $sql;
        $lifetime = (Input::get('lifetime')) ? : 0;
        $options = array('cacheDir' => \app\conf\App::$param['path'] . "app/tmp/", 'lifeTime' => $lifetime);
        $Cache_Lite = new \Cache_Lite($options);
        if ($data = $Cache_Lite->get($id)) {
            //echo "Cached";
        } else {
            ob_start();
            $fileName = md5(time() . rand(10000, 99999) . microtime());
            $file = \app\conf\App::$param["path"] . "/app/tmp/_" . $fileName . "." . $type;
            $cmd = "wkhtmltoimage " .
                "--height {$sizeArr[1]} --disable-smart-width --width {$sizeArr[0]} --quality 90 --javascript-delay 1000 " .
                "\"" . "http://127.0.0.1" . "/api/v1/staticmap/html/{$db}?baselayer={$baseLayer}&layers={$layers}&center={$center}&zoom={$zoom}&size={$size}&bbox={$bbox}&sql={$sql}\" " .
                $file;
            //die($cmd);
            exec($cmd);
            switch ($type) {
                case "png":
                    $res = imagecreatefrompng($file);
                    break;
                case "jpg":
                    $res = imagecreatefromjpeg($file);
                    break;
            }

            if (!$res) {
                $response['success'] = false;
                $response['message'] = "Could not create image";
                $response['code'] = 406;
                header("HTTP/1.0 {$response['code']} " . \app\inc\Util::httpCodeText($response['code']));
                echo \app\inc\Response::toJson($response);
                exit();
            }
            header('Content-type: image/png');
            imageAlphaBlending($res, true);
            imageSaveAlpha($res, true);
            imagepng($res);

            // Cache script
            $data = ob_get_contents();
            $Cache_Lite->save($data, $id);
            ob_get_clean();
        }
        header("Content-type: image/png");
        echo $data;
        exit();

    }

    public function get_html()
    {
        $db = Input::getPath()->part(5);
        $baseLayer = Input::get("baselayer");
        $bits = explode(".", $baseLayer);
        if (sizeof($bits) == 1) {
            $baseLayer = "geocloud.{$baseLayer}";
        } else {
            $baseLayer = "'{$baseLayer}'";
        }
        if (Input::get("layers")) {
            $layers = json_encode(explode(",", Input::get("layers")));
        } else {
            $layers = null;
        }
        $center = str_replace('"', '', json_encode(explode(",", Input::get("center"))));
        $zoom = Input::get("zoom");
        $size = explode("x", Input::get("size"));
        $bbox = Input::get("bbox");
        $sql = Input::get("sql");

        echo "
        <script src='/js/leaflet/leaflet.js'></script>
        <script src='/js/openlayers/proj4js-combined.js'></script>
        <script src='" . \app\conf\App::$param['host'] . "/api/v3/js/geocloud.js'></script>
        <div id='map' style='width: {$size[0]}px; height: {$size[1]}px'></div>
        <style>
        body {margin: 0px; padding: 0px;}
        .leaflet-control-zoom{display: none}
        .leaflet-control-layers{display: none}
        .leaflet-control-attribution{white-space: nowrap;}
        </style>
        <script>
            (function () {
                var map = new geocloud.map({
                    el: 'map'
                });
                map.bingApiKey = '" . \app\conf\App::$param['bingApiKey'] . "'
                map.addBaseLayer({$baseLayer});
                map.setBaseLayer({$baseLayer});";
        if (!$sql) {
            if ($bbox) {
                $bboxArr = explode(",", Input::get("bbox"));
                $bbox = "[{$bboxArr[0]},{$bboxArr[1]},{$bboxArr[2]},{$bboxArr[3]}]";
                echo "map.zoomToExtent({$bbox});";
            } else {
                echo
                "map.setView({$center},{$zoom});";
            }
        } else {
            echo "
                var store = new geocloud.sqlStore({
                    db: '{$db}',
                    sql: '" . rawurlencode($sql) . "',
                    async: false
                });
                map.addGeoJsonStore(store);
                store.load();
                map.zoomToExtentOfgeoJsonStore(store);";
        }
        if ($layers) {
            echo "
                map.addTileLayers({
                    db: '{$db}',
                    layers: {$layers}
                });";
        }
        echo "}())";
        echo "</script>";
        exit();
    }
}