<?php
require("../../app/vendor/autoload.php");
require("../../app/conf/App.php");

use app\conf\App;

new App();

$openapi = \OpenApi\Generator::scan(['../../app/api/v' . $_REQUEST['v']]);

header('Content-Type: application/json');
echo $openapi->toJson();