<?php
namespace app\conf;

class Connection
{
    static $param = array(
        "postgishost" => "POSTGISHOST_CONFIGURATION",
        "postgisdb" => "POSTGISDB_CONFIGURATION",
        "postgisuser" => "POSTGISUSER_CONFIGURATION",
        "postgisport" => "POSTGISPORT_CONFIGURATION",
        "postgispw" => "POSTGISPW_CONFIGURATION",
        "pgbouncer" => PGBOUNCER_CONFIGURATION,
    );
}