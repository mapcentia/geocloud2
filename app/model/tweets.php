<?php
class Tweet extends postgis
{
    /**
     *
     */
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
    public function search($search)
    {
        /** Note: Set the GET field BEFORE calling buildOauth(); **/
        $url = 'https://api.twitter.com/1.1/search/tweets.json';
        $getfield = '?' . $search;
        $requestMethod = 'GET';
        $twitter = new TwitterAPIExchange($this->settings);
        $res = $twitter
            ->setGetfield($getfield)
            ->buildOauth($url, $requestMethod)
            ->performRequest();
        $arr = json_decode($res);
        //print_r($arr->statuses);
        foreach ($arr->statuses as $value) {
            if (is_object($value->coordinates)) {
                $features[] = array("geometry" => $value->coordinates, "type" => "Feature", "properties" => array(
                    "text" => $value->text,
                    "created_at" => date("D jS F Y",strtotime($value->created_at)),
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
                    "favorite_count" => $value->favorite_count
                ));
            }
        }
        $response['success'] = true;
        $response['type'] = "FeatureCollection";
        $response['features'] = $features;
        //print_r(json_encode($response));
        return ($response);
    }
}