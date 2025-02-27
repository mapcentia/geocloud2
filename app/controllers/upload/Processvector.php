<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\controllers\upload;

use app\conf\App;
use app\controllers\Tilecache;
use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Response;
use app\conf\Connection;
use app\inc\Session;
use app\inc\Input;
use app\inc\Model;
use app\models\Classification;
use app\models\Table;
use app\models\Tile;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use stdClass;
use ZipArchive;

/**
 * Class Processvector
 * @package app\controllers\upload
 */
class Processvector extends Controller
{
    /**
     * Processvector constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException|GC2Exception
     * @throws InvalidArgumentException
     */
    public function get_index(): array
    {
        $response = [];
        $fileName = Input::get("file");
        $dir = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/__vectors";
        $safeName = Model::toAscii(Input::get("name"), array(), "_");
        $skipFailures = Input::get("ignoreerrors") == "true";
        $delete = Input::get("delete") == "true";
        $append = Input::get("append") == "true";
        $overwrite = Input::get("overwrite") == "true";
        $srid = Input::get("srid") ?: "4326";
        $encoding = Input::get("encoding") ?: "LATIN1";
        $type = Input::get("type");

        if (is_numeric($safeName[0])) {
            $safeName = "_" . $safeName;
        }

        // Check if file is .zip
        // =====================
        $zipCheck1 = explode(".", $fileName);
        $zipCheck2 = array_reverse($zipCheck1);
        $format = strtolower($zipCheck2[0]);
        if (strtolower($zipCheck2[0]) == "zip") {
            $ext = array("shp", "tab", "geojson", "gml", "kml", "mif", "gdb", "csv");
            $folderArr = array();
            $safeNameArr = array();
            for ($i = 0; $i < sizeof($zipCheck1) - 1; $i++) {
                $folderArr[] = $zipCheck1[$i];
            }
            $folder = implode(".", $folderArr);

            // ZIP start
            // =========
            $zip = new ZipArchive;
            $res = $zip->open($dir . "/" . $fileName);
            if ($res !== true) {
                $response['success'] = false;
                $response['message'] = "Could not unzip file";
                return Response::json($response);
            }
            $zip->extractTo($dir . "/" . $folder);
            $zip->close();

            if ($handle = opendir($dir . "/" . $folder)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry !== "." && $entry !== "..") {
                        $zipCheck1 = explode(".", $entry);
                        $zipCheck2 = array_reverse($zipCheck1);
                        if (in_array(strtolower($zipCheck2[0]), $ext)) {
                            $fileName = $folder . "/" . $entry;
                            for ($i = 0; $i < sizeof($zipCheck1) - 1; $i++) {
                                $safeNameArr[] = $zipCheck1[$i];
                            }
                            $safeName = Model::toAscii(implode(".", $safeNameArr), array(), "_");
                            break;
                        }
                        $fileName = $folder;
                    }
                }
            }
        }

        $fileType = strtolower($zipCheck2[0]);

        $type = match ($type) {
            "point" => "point",
            "linestring" => "linestring",
            "polygon" => "polygon",
            "multipoint" => "multipoint",
            "multilinestring" => "multilinestring",
            "multipolygon" => "multipolygon",
            "geometrycollection" => "geometrycollection",
            "geometry" => "geometry",
            default => "PROMOTE_TO_MULTI",
        };

        $model = new Model();
        $tableExist = $model->doesRelationExists(Connection::$param["postgisschema"] . "." . $safeName);
        $tableExist = $tableExist["success"];

        if ($tableExist && !$overwrite && !$delete && !$append) {
            throw new GC2Exception("'$safeName' exists already, use 'Overwrite'", 406);
        }

        if ($delete) {
            $sql = "TRUNCATE ONLY " . Connection::$param["postgisschema"] . "." . $safeName;
            $res = $model->prepare($sql);
            $res->execute();
        }
        $cmd = "PGCLIENTENCODING=$encoding ogr2ogr " .
            "-nomd " .
            ($skipFailures ? "-skipfailures " : " ") .
            (($delete || $append) ? "-append " : " ") .
            (($overwrite && !$delete) ? "-overwrite " : " ") .
            // TODO Set dim i GUI
            "-dim XY " .
            /*"--config DXF_ENCODING WIN1252 " .*/
            (($delete || $append) ? '' : "-lco 'GEOMETRY_NAME=the_geom' ") .
            (($delete || $append) ? '' : "-lco 'FID=gid' ") .
            (($delete || $append) ? '' : "-lco 'PRECISION=NO' ") .
            "-a_srs 'EPSG:$srid' " .

            // If csv, then set Open Options
            // =============================
            ($format == "csv" ? "-oo X_POSSIBLE_NAMES=lon*,Lon*,x,X -oo Y_POSSIBLE_NAMES=lat*,Lat*,y,Y -oo AUTODETECT_TYPE=YES -oo GEOM_POSSIBLE_NAMES=geometri " : '') .

            "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " port=" . Connection::$param["postgisport"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . Connection::$param["postgisdb"] . "' " .
            "'" . $dir . "/" . $fileName . "' " .
            "-nln " . Connection::$param["postgisschema"] . ".$safeName -nlt $type";

        exec($cmd . ' 2>&1', $out);

        // Add single class with random color
        // ==================================
        $geoType = $model->getGeometryColumns(Connection::$param["postgisschema"] . "." . $safeName, "type");
        $key = Connection::$param["postgisschema"] . "." . $safeName . ".the_geom";
        $class = new Classification($key);
        $arr = $class->getAll();
        if (empty($arr['data'])) {
            $class->insert();
            $class->update("0", Classification::createClass($geoType));
        }

        // Set layer editable
        // ==================
        $join = new Table("settings.geometry_columns_join");
        $data['_key_'] = $key;
        $data['editable'] = true;
        $join->updateRecord($data, "_key_");

        // Insert default layer def
        // ========================
        $def = new Tile($key);
        $arr = $def->get();
        if (empty($arr['data'][0])) {
            $data = new stdClass();
            $data->theme_column = '';
            $data->label_column = '';
            $data->query_buffer = '';
            $data->opacity = '';
            $data->label_max_scale = '';
            $data->label_min_scale = '';
            $data->meta_tiles = false;
            $data->meta_size = 3;
            $data->meta_buffer = 10;
            $data->ttl = '';
            $def->update($data);
        }

        // Check ogr2ogr output
        // ====================
        if ($out[0] == '') {
            $response['success'] = true;
            $response['message'] = "Layer <b>$safeName</b> is created";
            $response['type'] = $geoType;

            // Bust cache, in case of layer already exist
            // ==========================================
            Tilecache::bust(Connection::$param["postgisschema"] . "." . $safeName);
        } else {
            $response['success'] = false;
            $response['code'] = "400";
            $response['message'] = $safeName . ": Some thing went wrong. Check the log.";
            $response['out'] = $out[0];
            Session::createLog($out, $fileName);

            // Make sure the table is dropped if not
            // skipping failures and it didn't exists before
            // =================================================
            if (!$skipFailures && !$tableExist) {
                $sql = "DROP TABLE " . Connection::$param["postgisschema"] . "." . $safeName;
                $res = $model->prepare($sql);
                try {
                    $res->execute();
                } catch (PDOException) {

                }
            }
        }
        $response['cmd'] = $cmd;
        return $response;
    }
}