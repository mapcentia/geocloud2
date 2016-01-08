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

        ms_ioinstallstdouttobuffer();
        $oMap->owsdispatch($request);
        $contenttype = ms_iostripstdoutbuffercontenttype();
        if ($contenttype == 'image/png') {
            header('Content-type: image/png');
        } else {
            header('Content-type: text/xml');
        }
        ms_iogetStdoutBufferBytes();
        print ("<!--\n");
        include("README");
        print ("\n-->\n");
        ms_ioresethandlers();
    }
}