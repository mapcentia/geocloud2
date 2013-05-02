<?php
include ("../../inc/controller.php");
include ("../../model/users.php");
include ("../../wms/mapfile.php.map");
include ("../../wms/tilecache.cfg.php");
include ("../../model/classes.php");// For casttoclass

/**
 *
 */

class Mapfile_c extends Controller
{
    public $user;
    function __construct()
    {
        global $postgisschema;
        parent::__construct();
        $parts = $this->getUrlParts();
       // $this->auth($parts[3]);
        $postgisschema = $parts[4];
        switch ($parts[5]) {
            case 'create' :
                makeMapFile($parts[3]);
                makeTileCacheFile($parts[3]);
                echo $this->toJSON(array("message"=>"Files created"));
                break;
        }
    }
}
new Mapfile_c();