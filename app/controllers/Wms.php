<?php
namespace app\controllers;

use \app\conf\App;

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
        if ($_SESSION['http_auth'] != $db) {
            \app\models\Database::setDb($db);
            $postgisObject = new \app\inc\Model();
            foreach(explode(",",$layers) as $layer) {
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
        if ($_GET['sql_layer']) {
            include '../libs/functions.php';
            include '../conf/main.php';
            $postgisdb = "mydb";

            $request->setParameter("SLD_BODY",
                "<StyledLayerDescriptor version='1.1.0'><NamedLayer><Name>sql</Name><UserStyle><Title>xxx</Title><FeatureTypeStyle><Rule><LineSymbolizer><Stroke><CssParameter name='stroke'>#FFFF00</CssParameter><CssParameter name='stroke-width'>15</CssParameter></Stroke></LineSymbolizer></Rule></FeatureTypeStyle></UserStyle></NamedLayer></StyledLayerDescriptor>
                ");

            $postgisObj = new postgis();
            $postgisObj2 = new postgis();
            $view = "public.hello";
            $sqlView = "CREATE VIEW {$view} as " . urldecode($_GET['sql_layer']);
            $postgisObj->connect();
            $postgisObj->execQuery($sqlView);
            $postgisObj->execQuery("CREATE SEQUENCE _serial START 1");

            $arrayWithFields = $postgisObj2->getMetaData($view);

            foreach ($arrayWithFields as $key => $arr) {
                if ($arr['type'] == "geometry") {
                    $fieldsArr[] = "transform(" . $key . ",900913) as the_geom";
                } else {
                    $fieldsArr[] = $key;
                }
            }
            $fieldsArr[] = "nextval('_serial') as _serial";
            $sql = implode(",", $fieldsArr);
            $sql = "SELECT {$sql} FROM {$view}";

            $request->setParameter("LAYERS", $_GET['LAYERS'] . ",sql");
            $layer = ms_newLayerObj($oMap);

            $layer->updateFromString("
	LAYER
		NAME 'sql'
		STATUS off
		PROCESSING 'CLOSE_CONNECTION=DEFER'
		DATA \"the_geom from ({$sql}) as foo using unique _serial using srid=900913\"
		TYPE POLYGON
		CONNECTIONTYPE POSTGIS
		CONNECTION 'user=postgres dbname=mydb host=127.0.0.1'
		METADATA
		  'wms_title'    'sql'
		  'wms_srs'    'EPSG:4326'
		  'wms_name'    'sql'
		END
		PROJECTION
		  'init=epsg:900913'
		END
		CLASS
		  NAME 'New style'
		  STYLE
			OUTLINECOLOR 255 0 0
		  END
  		END
  	END
	");

        }
        ms_ioinstallstdouttobuffer();
        $oMap->owsdispatch($request);

        if ($_GET['sql_layer']) {
            $sql = "DROP VIEW {$view}";
            $result = $postgisObj->execQuery($sql);
        }
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