<?php
/**
 * Long description for file
 *
 * Long description for file (if any)...
 *
 * @category   API
 * @package    app\controllers
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 * @since      File available since Release 2013.1
 *
 */

namespace app\controllers\upload;

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Input;
use \app\inc\Model;
use \app\controllers\Tilecache;

/**
 * Class Processqgis
 * @package app\controllers\upload
 */
class Processqgis extends \app\inc\Controller
{
    private $table;
    private $layer;
    private $qgis;
    private $sridStr;

    function __construct()
    {
        parent::__construct();
        $this->table = new \app\models\Table("settings.geometry_columns_join");
        $this->layer = new \app\models\Layer();
        $this->qgis = new \app\models\Qgis();
        $this->sridStr = "EPSG:4326 EPSG:3857 EPSG:900913 EPSG:25832";
    }

    public function get_index()
    {
        $file = Input::get("file");
        $qgs = @simplexml_load_file(App::$param['path'] . "/app/tmp/" . Connection::$param["postgisdb"] . "/__qgis/" . $file);
        $arrT = [];
        $arrG = [];
        $arrN = [];
        $wmsNames = [];
        $wmsSrids = [];
        $treeOrder = [];
        $createWms = Input::get("createWms") == "true" ? true : false;
        $createComp = Input::get("createComp") == "true" ? true : false;

        if (!$qgs) {
            return array("success" => false, "code" => 400, "message" => "Could not read qgs file");
        }

        $ver = explode(".", $qgs->attributes()["version"]);

        $majorVer = $ver[0];
        $minorVer = $ver[1];

        foreach ($qgs->projectlayers[0]->maplayer as $maplayer) {

            $provider = (string)$maplayer->provider;

            switch ($provider) {

                case "postgres":
                    $dataSource = (string)$maplayer->datasource;
                    $layerName = (string)$maplayer->layername;
                    $newDataSource = preg_replace("/host=\S*/", "host=" . Connection::$param["postgishost"], $dataSource, 1);
                    preg_match("/table=\S*/", $dataSource, $matches);
                    $maplayer->datasource = $newDataSource;
                    preg_match_all("/\"(.*?)\"/", $matches[0], $t);
                    $arrT[] = $t;
                    preg_match_all("/\((.*?)\)/", $dataSource, $g);
                    $arrG[] = $g;
                    $maplayer->layername = $t[1][0] . "." . $t[1][1];
                    $arrN[] = $maplayer->layername;
                    $maplayer->title = (string)$maplayer->title ?: $layerName;
                    break;

                case "WFS":
                    $dataSource = (string)$maplayer->datasource;

                    // If version 14 or 18 style WFS source
                    if (!isset(parse_url($dataSource)["scheme"])) {
                        preg_match("/(?<=url\=)\S*/", $dataSource, $matches);
                        $parsed = parse_url(str_replace("'", "", $matches[0]));
                        preg_match("/(?<=typename\=)\S*/", $dataSource, $matches);
                        $split = explode(":", str_replace("'", "", $matches[0]));
                        $schema = explode("/", $parsed["path"])[3];
                        $table = $split[1];
                    } else {
                        $parsed = parse_url($dataSource);
                        $schema = explode("/", $parsed["path"])[3];
                        parse_str($parsed["query"], $result);
                        $table = explode(":", $result["TYPENAME"])[1];
                    }

                    $db = explode("/", $parsed["path"])[2];

                    $split = explode("@", $db);
                    if (sizeof($split) > 1) {
                        $db = $split[1];
                    }

                    $fullTable = $schema . "." . $table;

                    $rec = $this->layer->getAll($fullTable, false, true);
                    $pkey = $rec["data"][0]["pkey"];
                    $srid = $rec["data"][0]["srid"];
                    $type = $rec["data"][0]["type"];
                    $f_geometry_column = $rec["data"][0]["f_geometry_column"];

                    $spatialRefSys = new \app\models\Spatial_ref_sys();
                    $spatialRefSysRow = $spatialRefSys->getRowBySrid($srid);

                    $proj4text = $spatialRefSysRow["data"]["proj4text"];

                    $arrT[] = array(1 => array($schema, $table));
                    $arrG[] = array(1 => array($f_geometry_column));
                    $arrN[] = $fullTable;

                    $PGDataSource = "dbname={$db} host=" . Connection::$param["postgishost"] . " port=" . Connection::$param["postgisport"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " sslmode=disable key='{$pkey}' srid={$srid} type={$type} table=\"{$schema}\".\"{$table}\" ({$f_geometry_column}) sql=";

                    $maplayer->srs->spatialrefsys = "";
                    $maplayer->srs->spatialrefsys->proj4 = $proj4text;
                    $maplayer->srs->spatialrefsys->srid = $srid;
                    $maplayer->srs->spatialrefsys->authid = "EPSG:{$srid}";
                    $maplayer->provider = "postgres";
                    $maplayer->datasource = $PGDataSource;
                    $maplayer->layername = $fullTable;
                    $maplayer->title = (string)$maplayer->title ?: $fullTable;

                    break;

                case "wms":
                    if ($createWms) {
                        $layerName = Connection::$param["postgisschema"] . "." . Model::toAscii(is_numeric(mb_substr((string)$maplayer->layername, 0, 1, 'utf-8')) ? "_" . (string)$maplayer->layername : (string)$maplayer->layername, array(), "_");
                        $srid = (string)$maplayer->srs->spatialrefsys->srid;
                        $wmsSrids[] = $srid;
                        $wmsNames[] = $layerName;
                        $maplayer->layername = $layerName;
                    }
                    break;
            }
        }

        // Get the layers in the right order according to QGIS layertree
        // =============================================================
        foreach ($qgs->{"layer-tree-group"}[0] as $group) {
            if ($group && $group[0]->attributes()) {
                $attrs = $group[0]->attributes();
                $id = strval($attrs['id']);
                if (strval($attrs['checked']) == "Qt::Checked") {
                    foreach ($qgs->projectlayers[0]->maplayer as $maplayer) {
                        if ((string)$maplayer->id == $id) {
                            $treeOrder[] = (string)$maplayer->layername;
                        }
                    }
                }
            }
        }

        $path = App::$param['path'] . "/app/wms/qgsfiles/";
        $firstName = explode(".", $file)[0];
        $name = "parsed_" . Model::toAscii($firstName) . "_" . md5(microtime() . rand()) . ".qgs";
        $nameWithoutHash = "parsed_" . Model::toAscii($firstName) . ".qgs";

        // Set QGIS wms source for PG layers
        // =================================
        for ($i = 0; $i < sizeof($arrT); $i++) {
            $tableName = $arrT[$i][1][0] . "." . $arrT[$i][1][1];
            $layerKey = $tableName . "." . $arrG[$i][1][0];
            $wmsLayerName = $arrN[$i];
            $layers[] = $layerKey;
            $url = "http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=" . $path . $name . "&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&STYLES=&FORMAT=image/png&LAYER=" . $wmsLayerName . "&transparent=true&";
            $urls[] = $url;
            $data = new \stdClass;
            $data->_key_ = $layerKey;
            $data->wmssource = $url;
            $data->wmsclientepsgs = $this->sridStr;
            $data = array("data" => $data);
            $res = $this->table->updateRecord($data, "_key_");
            Tilecache::bust($tableName);
        }

        // Create new layers from QGIS WMS layer
        // =====================================
        for ($i = 0; $i < sizeof($wmsNames); $i++) {
            $tableName = $wmsNames[$i];
            $layerKey = $tableName . ".rast";
            $table = new \app\models\Table($tableName);
            $table->createAsRasterTable($wmsSrids[$i]);
            $url = "http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=" . $path . $name . "&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&STYLES=&FORMAT=image/png&LAYER=" . $tableName . "&transparent=true&";
            $data = new \stdClass();
            $data->_key_ = $layerKey;
            $data->wmssource = $url;
            $data->wmsclientepsgs = $this->sridStr;
            $data = array("data" => $data);
            $res = $this->table->updateRecord($data, "_key_");
            Tilecache::bust($tableName);
        }

        // Create the composite map from all layers in qgs-file
        // ====================================================
        if ($createComp) {
            $tableName = Connection::$param["postgisschema"] . "." . Model::toAscii($firstName);
            $layerKey = $tableName . ".rast";
            $table = new \app\models\Table($tableName);
            $table->createAsRasterTable("4326");
            $url = "http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=" . $path . $name . "&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&STYLES=&FORMAT=image/png&LAYER=" . implode(",", array_reverse($treeOrder)) . "&transparent=true&";
            $data = new \stdClass();
            $data->_key_ = $layerKey;
            $data->wmssource = $url;
            $data->wmsclientepsgs = "EPSG:4326 EPSG:3857 EPSG:900913 EPSG:25832";

            $data = array("data" => $data);
            $res = $this->table->updateRecord($data, "_key_");
            Tilecache::bust(Connection::$param["postgisschema"] . "." . $wmsNames[$i]);

        }

        // Write the new qgs-file
        // ======================
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        if (!$fh) {
            return ["success" => false, "message" => "Couldn't open file for writing: ". $name, "code" => 401];
        }
        $w = fwrite($fh, $qgs->asXML());
        if (!$w) {
            return ["success" => false, "message" => "Couldn't write file: ". $name, "code" => 401];
        }
        fclose($fh);

        // Write the a copy of the qgs-file with out hash
        // This will be overwritten if exists.
        // ==============================================
        @unlink($path . $nameWithoutHash);
        $fh = fopen($path . $nameWithoutHash, 'w');
        if (!$fh) {
            return ["success" => false, "message" => "Couldn't open file for writing: ". $nameWithoutHash, "code" => 401];
        }
        $w = fwrite($fh, $qgs->asXML());
        if (!$w) {
            return ["success" => false, "message" => "Couldn't write file: ". $nameWithoutHash, "code" => 401];
        }
        fclose($fh);

        $resDb = $this->qgis->insert([
            "id" => $name,
            "xml" => $qgs->asXML(),
            "db" => Connection::$param["postgisdb"],
        ]);

        if (!$resDb["success"]) {
            return ["success" => false, "message" => "Qgs file couldn't be stored in database"];
        }

        $res = json_decode($this->reload());
        $reloaded = $res->success ?: false;
        return ["success" => true, "version" => $minorVer, "message" => "Qgs file parsed", "reloaded" => $reloaded, "ch" => $path . $name, "layers" => $layers, "urls" => $urls];
    }

    public static function reload()
    {
        $res = \app\inc\Util::wget(App::$param["qgisServer"]["api"] . "/reload");
        return $res;
    }
}