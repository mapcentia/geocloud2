<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

class Osm extends \app\inc\Model
{
    public function create($conf, $createTable = false)
    {
        switch ($conf->data->table) {
            case "POINT";
                $table = "planet_osm_point";
                $type = "Point";
                break;
            case "LINE";
                $table = "planet_osm_line";
                $type = "LineString";
                break;
            case "AREA";
                $table = "planet_osm_polygon";
                $type = "MultiPolygon";
                break;
            case "ROADS";
                $table = "planet_osm_roads";
                $type = "LineString";
                break;
        }
        if ($conf->data->tags) {
            switch ($conf->data->match) {
                case "ALL":
                    $lo = "AND";
                    $op = "=";
                    break;
                case "ANY":
                    $lo = "OR";
                    $op = "=";
                    break;
                case "NONE":
                    $lo = "AND";
                    $op = "!=";
                    break;
            }
            $tags = array();
            $lines = explode("\n", $conf->data->tags);
            foreach ($lines as $line) {
                $pair = explode("=", $line);
                $pair[0] = trim($pair[0]);
                $pair[1] = trim($pair[1]);
                if (($pair[0]) && ($pair[1])) {
                    $tags[] = "tags->''{$pair[0]}''{$op}''{$pair[1]}''";
                }
            }
            $tagsStr = implode(" {$lo} ", $tags);
        }
        $sql = "CREATE " . ($createTable ? "TABLE" : "VIEW") . " %s AS SELECT
            p.osm_id             ,
            p.access             ,
            p.\"addr:housename\"     ,
            p.\"addr:housenumber\"   ,
            p.\"addr:interpolation\" ,
            p.admin_level        ,
            p.aerialway          ,
            p.aeroway            ,
            p.amenity            ,
            p.area               ,
            p.barrier            ,
            p.bicycle            ,
            p.brand              ,
            p.bridge             ,
            p.boundary           ,
            p.building           ,
            p.construction       ,
            p.covered            ,
            p.culvert            ,
            p.cutting            ,
            p.denomination       ,
            p.disused            ,
            p.embankment         ,
            p.foot               ,
            p.\"generator:source\"   ,
            p.harbour            ,
            p.highway            ,
            p.historic           ,
            p.horse              ,
            p.intermittent       ,
            p.junction           ,
            p.landuse            ,
            p.layer              ,
            p.leisure            ,
            p.lock               ,
            p.man_made           ,
            p.military           ,
            p.motorcar           ,
            p.\"name\"            ,
            p.\"natural\"            ,
            p.office             ,
            p.oneway             ,
            p.operator           ,
            p.place              ,
            p.population         ,
            p.power              ,
            p.power_source       ,
            p.public_transport   ,
            p.railway            ,
            p.ref                ,
            p.religion           ,
            p.route              ,
            p.service            ,
            p.shop               ,
            p.sport              ,
            p.surface            ,
            p.toll               ,
            p.tourism            ,
            p.\"tower:type\"         ,
            p.tracktype          ,
            p.tunnel             ,
            p.water              ,
            p.waterway           ,
            p.wetland            ,
            p.width              ,
            p.wood               ,
            p.z_order            ,
            p.way_area           ,
            p.way                ,
            p.tags               ,
            p.gid
        FROM dblink('%s'::text, 'SELECT
            osm_id             ,
            access             ,
            \"addr:housename\"     ,
            \"addr:housenumber\"   ,
            \"addr:interpolation\" ,
            admin_level        ,
            aerialway          ,
            aeroway            ,
            amenity            ,
            area               ,
            barrier            ,
            bicycle            ,
            brand              ,
            bridge             ,
            boundary           ,
            building           ,
            construction       ,
            covered            ,
            culvert            ,
            cutting            ,
            denomination       ,
            disused            ,
            embankment         ,
            foot               ,
            \"generator:source\"   ,
            harbour            ,
            highway            ,
            historic           ,
            horse              ,
            intermittent       ,
            junction           ,
            landuse            ,
            layer              ,
            leisure            ,
            lock               ,
            man_made           ,
            military           ,
            motorcar           ,
            \"name\"               ,
            \"natural\"            ,
            office             ,
            oneway             ,
            operator           ,
            place              ,
            population         ,
            power              ,
            power_source       ,
            public_transport   ,
            railway            ,
            ref                ,
            religion           ,
            route              ,
            service            ,
            shop               ,
            sport              ,
            surface            ,
            toll               ,
            tourism            ,
            \"tower:type\"         ,
            tracktype          ,
            tunnel             ,
            water              ,
            waterway           ,
            wetland            ,
            width              ,
            wood               ,
            z_order            ,
            way_area           ,
            way                ,
            tags               ,
            gid
        FROM {$conf->data->region}.{$table} where %s'::text)
        p(
            osm_id             bigint,
            access             text  ,
            \"addr:housename\"     text  ,
            \"addr:housenumber\"   text  ,
            \"addr:interpolation\" text  ,
            admin_level        text  ,
            aerialway          text  ,
            aeroway            text  ,
            amenity            text  ,
            area               text  ,
            barrier            text  ,
            bicycle            text  ,
            brand              text  ,
            bridge             text  ,
            boundary           text  ,
            building           text  ,
            construction       text  ,
            covered            text  ,
            culvert            text  ,
            cutting            text  ,
            denomination       text  ,
            disused            text  ,
            embankment         text  ,
            foot               text  ,
            \"generator:source\"   text  ,
            harbour            text  ,
            highway            text  ,
            historic           text  ,
            horse              text  ,
            intermittent       text  ,
            junction           text  ,
            landuse            text  ,
            layer              text  ,
            leisure            text  ,
            lock               text  ,
            man_made           text  ,
            military           text  ,
            motorcar           text  ,
            \"name\"               text  ,
            \"natural\"            text  ,
            office             text  ,
            oneway             text  ,
            operator           text  ,
            place              text  ,
            population         text  ,
            power              text  ,
            power_source       text  ,
            public_transport   text  ,
            railway            text  ,
            ref                text  ,
            religion           text  ,
            route              text  ,
            service            text  ,
            shop               text  ,
            sport              text  ,
            surface            text  ,
            toll               text  ,
            tourism            text  ,
            \"tower:type\"         text  ,
            tracktype          text  ,
            tunnel             text  ,
            water              text  ,
            waterway           text  ,
            wetland            text  ,
            width              text  ,
            wood               text  ,
            z_order            integer	,
            way_area           real  ,
            way                geometry({$type},900913),
            tags               hstore,
            gid                integer
        )";
        $safeName = self::toAscii($conf->data->name, array(), "_");
        if ($safeName == "state") {
            $safeName = "_state";
        }
        $box = $conf->data->extent;

        if (is_numeric(mb_substr($safeName, 0, 1, 'utf-8'))) {
            $safeName = "_" . $safeName;
        }
        $fullName = \app\conf\Connection::$param['postgisschema'] . "." . $safeName;
        $wheres = "way && ST_MakeEnvelope({$box->left},{$box->bottom},{$box->right},{$box->top},900913)";
        $output = sprintf($sql,
            $fullName,
            \app\conf\App::$param["osmConfig"]["server"],
            $wheres . (($tagsStr) ? " AND {$tagsStr}" : "")
        );
        $this->connect();
        $this->begin();
        $res = $this->prepare($output);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $this->rollback();
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        if ($createTable){
            $sql = "ALTER TABLE {$fullName} ADD PRIMARY KEY (gid)";
            $res = $this->prepare($sql);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                $this->rollback();
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 400;
                return $response;
            }
            $sql = "CREATE INDEX {$safeName}_idx ON {$fullName} USING gist(way)";
            $res = $this->prepare($sql);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                $this->rollback();
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = 400;
                return $response;
            }
        }
        $this->commit();
        $response['success'] = true;
        $response['message'] = "View created";
        return $response;
    }
}