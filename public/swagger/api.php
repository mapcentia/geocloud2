<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

ini_set("display_errors", "no");

require("../../app/vendor/autoload.php");
require("../../app/conf/App.php");

use app\conf\App;

new App();

$openapi = \OpenApi\Generator::scan(['../../app/api/v' . $_REQUEST['v']]);

header('Content-Type: application/json');
echo $openapi->toJson();