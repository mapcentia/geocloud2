<?php
namespace app\api\v1;

use \app\inc\Input;
use \app\inc\Response;

class Staticmap extends \app\inc\Controller
{

    function __construct()
    {
    }

    public function get_index()
    {
        include_once 'Cache_Lite/Lite.php';
        $id =$_SERVER["QUERY_STRING"].Input::getPath()->part(4);
        $lifetime = (Input::get('lifetime')) ? : 0;
        $options = array('cacheDir' => \app\conf\App::$param['path'] . "app/tmp/", 'lifeTime' => $lifetime);
        $Cache_Lite = new \Cache_Lite($options);
        if ($data = $Cache_Lite->get($id)) {
            //echo "Cached";
        } else {
            ob_start();
            $db = Input::getPath()->part(4);
            $baseLayer = Input::get("baselayer");
            $layers = Input::get("layers");
            $center = Input::get("center");
            $zoom = Input::get("zoom");
            $size = Input::get("size");
            $sizeArr = explode("x", Input::get("size"));
            $file = \app\conf\App::$param["path"] . "/app/tmp/_" . time() . ".png";
            $cmd = "xvfb-run --server-args='-screen 0, 1024x768x24' wkhtmltoimage " .
                "--height {$sizeArr[1]} --disable-smart-width --width {$sizeArr[0]} --quality 100 " .
                "\"".\app\conf\App::$param['host']."/api/v1/staticmap/html/{$db}?baselayer={$baseLayer}&layers={$layers}&center={$center}&zoom={$zoom}&size={$size}\" " .
                $file;
            //die($cmd);
            exec($cmd);
            $res = imagecreatefrompng($file);
            if (!$res) {
                $response['success'] = false;
                $response['message'] = "Could create image";
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
        $layers = json_encode(explode(",", Input::get("layers")));
        //$center = str_replace('"', '', json_encode(explode(",", Input::get("center"))));
        $center = explode(",", Input::get("center"));
        $zoom = Input::get("zoom");
        $size = explode("x", Input::get("size"));

        echo "
        <script src='http://maps.google.com/maps/api/js?v=3&sensor=false&libraries=places' type='text/javascript'></script>
        <script src='http://eu1.mapcentia.com/js/openlayers/OpenLayers.js' type='text/javascript'></script>
        <script src='http://eu1.mapcentia.com/js/openlayers/proj4js-combined.js' type='text/javascript'></script>
        <script src='".\app\conf\App::$param['host']."/api/v3/js/geocloud.js'></script>
        <div id='map' style='width: {$size[0]}px; height: {$size[1]}px'></div>
        <style>
        body {margin: 0px; padding: 0px;}
        .olControlZoom {display: none}
        </style>
        <script>
            (function () {
                var transformPoint = function (lat, lon, s, d) {
                    var p = [];
                    if (typeof Proj4js === 'object') {
                        var source = new Proj4js.Proj(s);    //source coordinates will be in Longitude/Latitude
                        var dest = new Proj4js.Proj(d);
                        var p = new Proj4js.Point(lat, lon);
                        Proj4js.transform(source, dest, p);
                    }
                    else{
                        p.x = null;
                        p.y = null;
                    }
                    return p;
                };
                var p = transformPoint({$center[1]}, {$center[0]}, 'EPSG:4326', 'EPSG:900913');
                var map = new geocloud.map({
                    el: 'map'
                });
                map.addBaseLayer(geocloud.{$baseLayer});
                map.setBaseLayer(geocloud.{$baseLayer});
                map.zoomToPoint(p.x,p.y,{$zoom});
                map.addTileLayers({
                    db: '{$db}',
                    layers: {$layers}
                });
            }())
        </script>
        ";
        exit();
    }
}