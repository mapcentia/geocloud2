<?php
namespace app\controllers;

use \app\conf\App;
use \app\inc\Util;

class Wms extends \app\inc\Controller
{
    function __construct()
    {
        if (\app\inc\Input::getPath()->part(3) == "tilecache") {
            $postgisschema = \app\inc\Input::getPath()->part(4);

        } else {
            $postgisschema = \app\inc\Input::getPath()->part(3);
        }
        $db = \app\inc\Input::getPath()->part(2);
        $dbSplit = explode("@", $db);
        if (sizeof($dbSplit) == 2) {
            $subUser = $dbSplit[0];
            $db = $dbSplit[1];
        } else {
            $subUser = null;
        }
        $path = App::$param['path'] . "/app/wms/mapfiles/";
        $name = $db . "_" . $postgisschema . ".map";

        $oMap = new \mapObj($path . $name);
        $request = new \OWSRequestObj();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            foreach ($_GET as $k => $v) {
                if (strtolower($k) == "layers" || strtolower($k) == "layer" || strtolower($k) == "typename" || strtolower($k) == "typenames") {
                    $layers = $v;
                }
                $request->setParameter($k, $v);
            }
        } else {
            $request->loadParams();
        }

        $trusted = false;
        foreach (App::$param["trustedAddresses"] as $address) {
            if (Util::ipInRange(Util::clientIp(), $address)) {
                $trusted = true;
                break;
            }
        }
        if (!$trusted) {
            foreach (explode(",", $layers) as $layer) {
                $this->basicHttpAuthLayer($layer, $db, $subUser);
            }
        }

        $this->fetch($db, $postgisschema);


        /*ms_ioinstallstdouttobuffer();
        $oMap->owsdispatch($request);
        $contenttype = ms_iostripstdoutbuffercontenttype();
        if ($contenttype == 'image/png') {
            header('Content-type: image/png');
        } else {
            header('Content-type: text/xml');
        }
        imagepng(ms_iogetStdoutBufferBytes());

        ms_ioresethandlers();*/
    }

    public function fetch($db, $postgisschema)
    {

        $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/{$db}_{$postgisschema}.map&" . $_SERVER["QUERY_STRING"];


        header("X-Powered-By: GC2 WMS");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header_line) {
            $bits = explode(":", $header_line);
            if ($bits[0] == "Content-Type") {
                $this->type = trim($bits[1]);
            }
            // Send text/xml instead of application/vnd.ogc.se_xml
            if ($bits[0] == "Content-Type" && trim($bits[1]) == "application/vnd.ogc.se_xml") {
                header("Content-Type: text/xml");
            } elseif ($bits[0] != "Content-Encoding" && trim($bits[1]) != "chunked") {
                header($header_line);
            }
            return strlen($header_line);
        });
        $content = curl_exec($ch);
        curl_close($ch);

        // Return content
        echo $content;
        exit();
    }
}