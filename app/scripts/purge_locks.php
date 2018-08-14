<?php
include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

use \app\conf\App;

new App();

$lockDir = App::$param['path'] . "/app/tmp/scheduler_locks";

$files = glob($lockDir."/*");
$now   = time();

foreach ($files as $file) {
    print $file . "\n\n";
    if (is_file($file)) {
        if ($now - filemtime($file) >= 60 * 60 * 1 * 1) { // one hour
            print "unlink " . $file. "\n";
            unlink($file);
        }
    }
}