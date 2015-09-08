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
            $db = $dbSplit[1];
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

        //error_log(Util::clientIp());
        $trusted = false;
        foreach (App::$param["trustedAddresses"] as $address) {
            if (Util::ipInRange(Util::clientIp(), $address)) {
                $trusted = true;
                break;
            }
        }
        if (!$trusted) {
            if ($_SESSION['http_auth'] != $db) {
                \app\models\Database::setDb($db);
                $postgisObject = new \app\inc\Model();
                foreach (explode(",", $layers) as $layer) {
                    $auth = $postgisObject->getGeometryColumns($layer, "authentication");
                    $layerSplit = explode(".", $layer);
                    $HTTP_FORM_VARS["TYPENAME"] = $layerSplit[1];
                    if ($auth == "Read/write") {
                        include('inc/http_basic_authen.php');
                    } else {
                        include('inc/http_basic_authen_subuser.php');
                    }
                }
            }
        }

        ms_ioinstallstdouttobuffer();
        $oMap->owsdispatch($request);
        $contenttype = ms_iostripstdoutbuffercontenttype();
        if ($contenttype == 'image/png') {
            header('Content-type: image/png');
        } else {
            header('Content-type: text/xml');
        }
        ms_iogetStdoutBufferBytes();
        ms_ioresethandlers();
    }
}