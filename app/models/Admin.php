<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use \app\inc\Model;

class Admin extends Model
{
    public $dbc;

    function __construct()
    {
        parent::__construct();
        $this->dbc = new Dbcheck();
    }


    private function check(): bool
    {
        $checkPostGIS = $this->dbc->isPostGISInstalled();

        if ($checkPostGIS['success']) {
            return true;
        } else {
            return false;

        }
    }

    public function install(): array
    {
        $response = [];

        if (!$this->check()) {
            $response['success'] = false;
            $response['message'] = "PostGIS extension is not created. Run 'CREATE EXTENSION postgis' in " . Database::getDb();
            $response['code'] = 401;
            return $response;
        }

        $checkMy = $this->dbc->isSchemaInstalled();
        if ($checkMy['success']) {
            $response['success'] = true;
            $response['message'] = "Schema is installed";
            $response['code'] = 401;
            return $response;
        }

        $this->connect();

        $this->begin();

        $sql = "CREATE SCHEMA settings";

        try {
            $res = $this->prepare($sql);
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["data"][] = $sql;

        $sql = "CREATE TABLE settings.geometry_columns_join (
                    _key_ VARCHAR(255) NOT NULL,
                    f_table_abstract CHARACTER VARYING(256),
                    f_table_title CHARACTER VARYING(256),
                    tweet TEXT,
                    editable BOOL DEFAULT 'true',
                    created TIMESTAMP WITH TIME ZONE DEFAULT ('now'::TEXT)::TIMESTAMP(0) WITH TIME ZONE,
                    lastmodified TIMESTAMP WITH TIME ZONE DEFAULT ('now'::TEXT)::TIMESTAMP(0) WITH TIME ZONE,
                    authentication TEXT DEFAULT 'Write'::TEXT,
                    fieldconf TEXT,
                    meta_url TEXT,
                    class TEXT,
                    def TEXT,
                    layergroup CHARACTER VARYING(255),
                    wmssource CHARACTER VARYING(255),
                    baselayer BOOL,
                    sort_id INT,
                    tilecache BOOL,
                    data TEXT,
                    not_querable BOOL,
                    single_tile BOOL,
                    cartomobile TEXT,
                    filter TEXT,
                    bitmapsource VARCHAR(255)
                )";

        try {
            $res = $this->prepare($sql);
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["data"][] = "CREATE TABLE settings.geometry_columns_join";


        $sql = "ALTER TABLE settings.geometry_columns_join ADD CONSTRAINT geometry_columns_join_key UNIQUE(_key_)";
        try {
            $res = $this->prepare($sql);
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["data"][] = $sql;


        $sql = "CREATE TABLE settings.viewer(viewer TEXT)";
        try {
            $res = $this->prepare($sql);
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        $sql = "INSERT INTO settings.viewer VALUES('{\"pw\":\"81dc9bdb52d04dc20036dbd8313ed055\"}')";
        try {
            $res = $this->prepare($sql);
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["data"][] = "INSERT INTO viewer";

        $this->commit();
        $response['success'] = true;
        $response['message'] = "GC2 schema installed";

        return $response;
    }
}