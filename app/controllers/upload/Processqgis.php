<?php
namespace app\controllers\upload;

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Input;

class Processqgis extends \app\inc\Controller
{

    private $table;
    private $layer;

    function __construct()
    {
        $this->table = new \app\models\Table("settings.geometry_columns_join");
        $this->layer = new \app\models\Layer();
    }

    public function get_index()
    {
        $file = Input::get("file");
        $qgs = @simplexml_load_file(App::$param['path'] . "/app/tmp/" . Connection::$param["postgisdb"] . "/__qgis/" . $file);

        if (!$qgs) {
            return array("success" => false, "code" => 400, "message" => "Could not read qgs file");
        }

        foreach ($qgs->projectlayers[0]->maplayer as $maplayer) {

            $provider = (string)$maplayer->provider;


            switch ($provider) {
                case "postgres":
                    $dataSource = (string)$maplayer->datasource;
                    $layerName = (string)$maplayer->layername;

                    $newDataSource = preg_replace("/host=\S*/", "host=postgis", $dataSource, 1);
                    preg_match("/table=\S*/", $dataSource, $matches);
                    $maplayer->datasource = $newDataSource;
                    preg_match_all("/\"(.*?)\"/", $matches[0], $t);
                    $arrT[] = $t;
                    preg_match_all("/\((.*?)\)/", $dataSource, $g);
                    $arrG[] = $g;

                    $maplayer->layername = $t[1][0] . "." . $t[1][1];
                    $maplayer->title = (string)$maplayer->title ?: $layerName;

                    break;
                case "WFS":
                    $TYPENAME = "";
                    $dataSource = (string)$maplayer->datasource;
                    $layerName = (string)$maplayer->layername;

                    $parsed = parse_url($dataSource);


                    //print_r($parsed);

                    $db = explode("/", $parsed["path"])[2];
                    $schema = explode("/", $parsed["path"])[3];

                    parse_str($parsed["query"]);
                    $table = explode(":", $TYPENAME)[1];

                    $fullTable = $schema . "." . $table;


                    $rec = $this->layer->getAll(null, $fullTable, true);
                    $pkey = $rec["data"][0]["pkey"];
                    $srid = $rec["data"][0]["srid"];
                    $type = $rec["data"][0]["type"];
                    $f_geometry_column = $rec["data"][0]["f_geometry_column"];

                    $spatialRefSys = new \app\models\Spatial_ref_sys();
                    $spatialRefSysRow = $spatialRefSys->getRowBySrid($srid);

                    $proj4text = $spatialRefSysRow["data"]["proj4text"];

                    $arrT[] = array(1 => array($schema, $table));
                    $arrG[] = array(1 => array($f_geometry_column));

                    $PGDataSource = "dbname='{$db}' host=postgis port=5432 user='gc2' password='1234' sslmode=disable key='{$pkey}' srid={$srid} type={$type} table=\"{$schema}\".\"{$table}\" ({$f_geometry_column}) sql=";

                    $maplayer->srs->spatialrefsys = "";
                    $maplayer->srs->spatialrefsys->proj4 = $proj4text;
                    $maplayer->srs->spatialrefsys->srid = $srid;
                    $maplayer->srs->spatialrefsys->authid = "EPSG:{$srid}";
                    $maplayer->provider = "postgres";
                    $maplayer->datasource = $PGDataSource;
                    $maplayer->layername = $fullTable;
                    $maplayer->title = (string)$maplayer->title ?: $layerName;

                    break;
            }

        }
        $path = App::$param['path'] . "/app/wms/qgsfiles/";
        $name = "parsed_" . $file;
        for ($i = 0; $i < sizeof($arrT); $i++) {
            $layer = $arrT[$i][1][0] . "." . $arrT[$i][1][1] . "." . $arrG[$i][1][0];
            $layers[] = $layer;
            $url = "http://qgis-server/cgi-bin/qgis_mapserv.fcgi?map=" . $path . $name . "&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&STYLES=&FORMAT=image/png&LAYER=" . $arrT[$i][1][0] . "." . $arrT[$i][1][1] . "&transparent=true&";
            $urls[] = $url;

            $data = new \StdClass;
            $data->_key_ = $layer;
            $data->wmssource = $url;

            $data = array("data" => $data);
            $res = $this->table->updateRecord($data, "_key_");
            \app\controllers\Tilecache::bust($arrT[$i][1][0] . "." . $arrT[$i][1][1]);
        }
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $qgs->asXML());
        fclose($fh);

        $res = json_decode($this->reload());
        $reloaded = $res->success ?: false;
        return array("success" => true, "message" => "Qgs file parsed", "reloaded" => $reloaded, "ch" => $path . $name, "layers" => $layers, "urls" => $urls);
    }

    public static function reload()
    {
        $res = \app\inc\Util::wget(App::$param["qgisServer"]["api"] . "/reload");
        return $res;
    }
}