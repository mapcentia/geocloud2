<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

include_once(__DIR__ . "/../conf/App.php");

use \app\conf\App;

new App();

$dir = App::$param['path'] . "/app/tmp";
$ttl = 60 * 60 * 4;

$directory = new RecursiveDirectoryIterator($dir);
$iterator = new RecursiveIteratorIterator($directory);

foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && time() - $fileInfo->getCTime() >= $ttl && $fileInfo->getFilename() != ".gitignore") {
        echo $fileInfo->getRealPath() . "\n";
        unlink($fileInfo->getRealPath());
    }
}
