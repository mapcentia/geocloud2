<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

include_once(__DIR__ . "/../conf/App.php");

use app\conf\App;

new App();

$lockDir = App::$param['path'] . "/app/tmp/scheduler_locks";

$files = glob($lockDir."/*");
$now   = time();

foreach ($files as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= 60 * 60 * 1 * 1) { // one hour
            print "unlink " . $file. "\n";
            unlink($file);
        }
    }
}