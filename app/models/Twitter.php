<?php
namespace app\models;

use app\inc\Model;

class Twitter extends Model
{
    private $settings;

    function __construct()
    {
        parent::__construct();
        /** URL for REST request, see: https://dev.twitter.com/docs/api/1.1/ **/
        $this->settings = array(
            'oauth_access_token' => "1252476258-DVO9x6mwnz05f5VL8WIsTx4XmpeUbOjDSs9hLXc",
            'oauth_access_token_secret' => "regt6Vo082Bq3uaSDKyrGnHYPNSccncU6woXN1Mlw",
            'consumer_key' => "AtDjL4UxITwrwBIMBicCQ",
            'consumer_secret' => "GsFWDIR8bCuuigshxVhj9ZFK0lU00ecMm0ZeEd2Go"
        );
    }

    public function search($search, $store = false, $schema = null)
    {
        $schema = ($schema) ? : "public";
        $sql = file_get_contents(\app\conf\App::$param['path'] . "public/migration/tweets.sql");

        if ($store) {
            // Using native PG driver for multi commands
            $this->execQuery("SET search_path TO " . $schema . ",public", "PG");
            $this->execQuery($sql, "PG");
            // Closing connection, so it reopens with PDO driver
            $this->close();

            $sql = "SELECT max(id) FROM {$schema}.tweets";
            $row = $this->fetchRow($this->execQuery($sql), "assoc");
        }

        /** Note: Set the GET field BEFORE calling buildOauth(); **/
        $url = 'https://api.twitter.com/1.1/search/tweets.json';
        $getfield = '?' . $search;
        $requestMethod = 'GET';
        $twitter = new \app\inc\TwitterAPIExchange($this->settings);
        $res = $twitter
            ->setGetfield($getfield)
            ->buildOauth($url, $requestMethod)
            ->performRequest();
        //$res = file_get_contents("kv13_parsed.json");
        //echo $res;
        $arr = json_decode($res);
        //print_r($arr);
        foreach ($arr->statuses as $value) {
            /*if (!is_object($value->coordinates)) {
                $value->coordinates = new \stdClass();
                $value->coordinates->type = "point";
                $value->coordinates->coordinates = array(10.22518158,56.17566115);
            }*/
            if (is_object($value->coordinates)) {
                $bindings = array(
                    "text" => $value->text,
                    "created_at" => date("D jS F Y", strtotime($value->created_at)),
                    "id" => $value->id,
                    "source" => $value->source,
                    "user_name" => $value->user->name,
                    "user_screen_name" => $value->user->screen_name,
                    "user_id" => $value->user->id,
                    "place_id" => $value->place->id,
                    "place_type" => $value->place->place_type,
                    "place_full_name" => $value->place->full_name,
                    "place_country_code" => $value->place->country_code,
                    "place_country" => $value->place->country,
                    "retweet_count" => $value->retweet_count,
                    "favorite_count" => $value->favorite_count,
                    "entities" => json_encode($value->entities)
                );
                $features[] = array("geometry" => $value->coordinates, "type" => "Feature", "properties" => $bindings);
                if ($store) {
                    $bindings['the_geom'] = "POINT(" . $value->coordinates->coordinates[0] . " " . $value->coordinates->coordinates[1] . ")";
                    $sql = "INSERT INTO {$schema}.tweets (id,text,created_at,source,user_name,user_screen_name,user_id,place_id,place_type,place_full_name,place_country_code,place_country,retweet_count,favorite_count,entities,the_geom) VALUES(" .
                        ":id," .
                        ":text," .
                        ":created_at," .
                        ":source," .
                        ":user_name," .
                        ":user_screen_name," .
                        ":user_id," .
                        ":place_id," .
                        ":place_type," .
                        ":place_full_name," .
                        ":place_country_code," .
                        ":place_country," .
                        ":retweet_count," .
                        ":favorite_count," .
                        ":entities," .
                        "ST_GeomFromText(:the_geom,4326))";
                    $res = $this->prepare($sql);
                    try {
                        $res->execute($bindings);
                    } catch (PDOException $e) {
                        print_r($e);
                    }

                }
//            }
            }
        }
        $response['success'] = true;
        $response['type'] = "FeatureCollection";
        $response['features'] = $features;
        return ($response);
    }
}