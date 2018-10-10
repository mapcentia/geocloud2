<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

$parts = explode("/", str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']));
//$parts = $parts = explode("/", $_SERVER['REDIRECT_URL']);

$userFromUri = $parts[2];
$schemaFromUri = $parts[3];
$srsFromUri = $parts[4];

if (!$schemaFromUri) {
	$schemaFromUri = "public";
}

//echo "<!-- Username from uri: ".$userFromUri."-->\n";
//echo "<!-- SRS from uri: ".$srsFromUri."-->\n";


