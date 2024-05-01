
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */
?>

<link href="/js/bootstrap/css/bootstrap.css" rel="stylesheet">
<div class="container">
    <div class="row">
        <div class="span9 offset2">
            <?php
            use \app\inc\Util;
            use \app\conf\App;
            use \app\models\Database;
            use \app\models\Dbcheck;

            include("../../app/conf/App.php");
            include("../../app/conf/Connection.php");
            new \app\conf\App();

            App::$param['protocol'] = App::$param['protocol'] ?: Util::protocol();
            App::$param['host'] = App::$param['host'] ?: App::$param['protocol'] . "://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'];

            echo "<div>PHP version " . phpversion() . " ";
            if (function_exists('apache_get_modules')) {
                echo " running as mod_apache</div>";
                $mod_apache = true;
            } else {
                echo " running as CGI/FastCGI</div>";
                $mod_apache = false;
            }
            // We check if "wms/mapfiles" is writeable
            $ourFileName = "../../app/wms/mapfiles/testFile.txt";
            $ourFileHandle = @fopen($ourFileName, 'w');
            if ($ourFileHandle) {
                echo "<div class='alert alert-success'>app/wms/mapfiles dir is writeable</div>";
                @fclose($ourFileHandle);
                @unlink($ourFileName);
            } else {
                echo "<div class='alert alert-error'>app/wms/mapfiles dir is not writeable. You must set permissions so the webserver can write in the wms/mapfiles dir.</div>";
            }
            $ourFileName = "../../app/tmp/testFile.txt";
            $ourFileHandle = @fopen($ourFileName, 'w');
            if ($ourFileHandle) {
                echo "<div class='alert alert-success'>app/tmp dir is writeable</div>";
                @fclose($ourFileHandle);
                @unlink($ourFileName);
            } else {
                echo "<div class='alert alert-error'>app/tmp dir is not writeable. You must set permissions so the webserver can write in the wms/cfgfiles dir.</div>";
            }
            if (class_exists('mapObj')) {
                echo "<div class='alert alert-success'>MapScript is installed</div>";
                $mod_apache = true;
            } else {
                echo "<div class='alert alert-error'>MapScript is not installed</div>";
                $mod_apache = false;
            }
            $dbList = new Database();
            try {
                $arr = $dbList->listAllDbs();
                $i = 1;
                $systemDbs = ['mapcentia', 'gc2scheduler'];
                echo "<table class='table table-striped'>";
                echo "<thead><tr><th>Databases</th><th>PostGIS</th><th>GC2 settings schema</th><th></th></tr></thead>";
                foreach ($arr['data'] as $db) {
                    if (in_array($db, $systemDbs)) {
                        continue;
                    }
                    $postgisInstalled = false;
                    if ($db != "template1" and $db != "template0" and $db != "postgres" and $db != "postgis_template") {
                        echo "<tr><td>{$db}</td>";
                        Database::setDb($db);
                        $dbc = new Dbcheck();
                        // Check if postgis is installed
                        try {
                            $checkPostGIS = $dbc->isPostGISInstalled();
                            echo "<td style='color:green'>V</td>";
                            $postgisInstalled = true;
                        } catch (Exception) {
                            echo "<td style='color:red'>X</td>";
                        }
                        // Check if schema "settings" is loaded
                        try {
                            $checkMy = $dbc->isSchemaInstalled();
                            echo "<td style='color:green'>V</td><td></td>";
                        } catch (Exception) {
                            echo "<td style='color:red'>X</td>";
                            if ($postgisInstalled) {
                                echo "<td><a class='btn btn-primary small' href='install.php?db={$db}'>Install schema</a></td>";
                            } else {
                                echo "<td></td>";
                            }
                        }
                        echo "</tr>";
                    }
                    $i++;
                }
                echo "<table>";
            } catch (Exception $e) {
                echo "<div class='alert alert-error'>Could not connect to PostGreSQL {$e->getMessage()}</div>";
            }
            ?>
        </div>
    </div>
</div>

