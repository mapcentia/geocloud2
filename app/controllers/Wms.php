<?php
namespace app\controllers;


use \app\conf\App;
use \app\inc\Util;
use \app\inc\Input;

/**
 * Class Wms
 * @package app\controllers
 */
class Wms extends \app\inc\Controller
{
    /**
     * Wms constructor.
     */
    function __construct()
    {
        $layers = [];
        $postgisschema = \app\inc\Input::getPath()->part(3);
        $db = \app\inc\Input::getPath()->part(2);
        $dbSplit = explode("@", $db);
        if (sizeof($dbSplit) == 2) {
            $subUser = $dbSplit[0];
            $db = $dbSplit[1];
        } else {
            $subUser = null;
        }

        $trusted = false;
        foreach (App::$param["trustedAddresses"] as $address) {
            if (Util::ipInRange(Util::clientIp(), $address)) {
                $trusted = true;
                break;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            foreach ($_GET as $k => $v) {
                if (strtolower($k) == "layers" || strtolower($k) == "layer" || strtolower($k) == "typename" || strtolower($k) == "typenames") {
                    $layers[] = $v;
                }
            }
            if (!$trusted) {
                foreach ($layers as $layer) {
                    $this->basicHttpAuthLayer($layer, $db, $subUser);
                }
            }
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $this->get($db, $postgisschema);
            }
        }

        // TODO check layers!
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->post($db, $postgisschema, Input::get(null, true));
        }
    }

    /**
     * @param $db string
     * @param $postgisschema string
     */
    private function get($db, $postgisschema)
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
        echo $content;
        exit();
    }

    private function post($db, $postgisschema, $data) {
        $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/{$db}_{$postgisschema}.map&";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: text/xml',
                'Content-Length: ' . strlen($data))
        );
        $content = curl_exec($ch);
        header("Content-Type: text/xml");
        echo $content;
        exit();
    }
}