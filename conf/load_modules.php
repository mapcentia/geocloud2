<?php
//
if (!extension_loaded("gd")) dl("php_gd2.dll");
if (!extension_loaded("pgsql")) dl("php_pgsql.dll");
if (!extension_loaded("MapScript")) dl("php_mapscript.dll");
if(!$doNotLoadClasses)
{
define("__COUNTER_PHP", 1);
/*
include_once($basePath."core/constants.php");
include_once($basePath."core/classes.php");
include_once($basePath."core/functions.php");
require_once($basePath."core/dbuser.php");
include_once($basePath."core/dbcore.php");
$dbObj->connect2db();
include_once($basePath."core/config.php");
include_once($basePath."core/appfiles/lokalplaner/lokalplanerfunctions.php");
$iLangID = DANISH;// Odeum CMS specific;
*/
}
