<?php
/**
 * GC2 configuration of the throw-away test environment (docker/test).
 *
 * Copied to app/conf/App.php in the test container (entrypoint.sh). It is the config
 * template (docker/conf/gc2/App.php) plus the few settings the test suites
 * need, so a new setting in the template reaches the tests by itself.
 * No secrets belong here.
 */

namespace app\conf;

require '/src/docker/conf/gc2/App.php';

App::$param = array_replace_recursive(App::$param, [
    // Snapshots on the container's own disk
    "snapshot" => [
        "storage" => "local",
        "localPath" => "/var/www/geocloud2/app/tmp/snapshots",
    ],
    // Web server, MapServer and MapCache are the same container
    "mapCache" => [
        "host" => "http://127.0.0.1:80",
        "wmsHost" => "http://127.0.0.1:80",
        "type" => "sqlite",
    ],
    "mapServer" => [
        "host" => "http://127.0.0.1",
    ],
    // Milliseconds. The template's value is too low for the SQL API tests.
    "SqlApiSettings" => [
        "statement_timeout" => 60000,
    ],
    // The suites read their own writes back at once
    "appCache" => [
        "ttl" => 1,
    ],
    // The api suite sends far more requests per minute than a client would
    "apiV4" => [
        "rateLimitMode" => "sliding",
        "rateLimitWindowSeconds" => 10,
        "rateLimitPerMinute" => 12000,
    ],
    // The opensearch service of docker/test/docker-compose.yml
    "esHost" => "http://opensearch:9200",
]);
