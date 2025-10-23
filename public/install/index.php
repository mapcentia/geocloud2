<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


ini_set("display_errors", "no");

use app\inc\Connection;
use \app\inc\Util;
use \app\conf\App;
use \app\models\Database;
use \app\models\Dbcheck;

include("../../app/conf/App.php");
include("../../app/conf/Connection.php");
new \app\conf\App();

App::$param['protocol'] = App::$param['protocol'] ?: Util::protocol();
App::$param['host'] = App::$param['host'] ?: App::$param['protocol'] . "://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'];

?>

<html>
<head>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://unpkg.com/htmx.org@1.9.12"></script>
    <script src="https://unpkg.com/htmx.org@1.9.12/dist/ext/json-enc.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/auth.css">
    <script>
        document.documentElement.setAttribute('data-bs-theme', (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'))
    </script>
    <title></title>

</head>
<body class="d-flex flex-column min-vh-100">
<main class='container py-4 flex-grow-1'>
    <h1 class="mb-3">GC2 Installation Overview</h1>
    <p class="text-muted">This page checks your environment and lists PostgreSQL databases. For each database, you can install required PostGIS extensions and GC2 schemas if missing.</p>
    <?php
    echo "<div class='mb-3'>PHP version " . phpversion() . " ";
    if (function_exists('apache_get_modules')) {
        echo " running as mod_apache</div>";
        $mod_apache = true;
    } else {
        echo " running as CGI/FastCGI</div>";
        $mod_apache = false;
    }
    // We check if \"wms/mapfiles\" is writeable
    $ourFileName = "../../app/wms/mapfiles/testFile.txt";
    $ourFileHandle = @fopen($ourFileName, 'w');
    if ($ourFileHandle) {
        echo "<div class='alert alert-success'>app/wms/mapfiles dir is writeable</div>";
        @fclose($ourFileHandle);
        @unlink($ourFileName);
    } else {
        echo "<div class='alert alert-danger'>app/wms/mapfiles dir is not writeable. You must set permissions so the webserver can write in the wms/mapfiles dir.</div>";
    }
    $ourFileName = "../../app/tmp/testFile.txt";
    $ourFileHandle = @fopen($ourFileName, 'w');
    if ($ourFileHandle) {
        echo "<div class='alert alert-success'>app/tmp dir is writeable</div>";
        @fclose($ourFileHandle);
        @unlink($ourFileName);
    } else {
        echo "<div class='alert alert-danger'>app/tmp dir is not writeable. You must set permissions so the webserver can write in the wms/cfgfiles dir.</div>";
    }
    if (class_exists('mapObj')) {
        echo "<div class='alert alert-success'>MapScript is installed</div>";
        $mod_apache = true;
    } else {
        echo "<div class='alert alert-danger'>MapScript is not installed</div>";
        $mod_apache = false;
    }
    $dbList = new Database();
    try {
        $arr = $dbList->listAllDbs();
        $i = 1;
        $systemDbs = ['mapcentia', 'gc2scheduler'];
        // Check presence of required 'mapcentia' database
        $hasMapcentia = in_array('mapcentia', $arr['data'] ?? [], true);
        if (!$hasMapcentia) {
            echo "<div class='alert alert-warning d-flex justify-content-between align-items-center' role='alert'>";
            echo "<div><strong>mapcentia</strong> database is missing. This database stores GC2 users and related metadata.</div>";
            echo "<a class='btn btn-sm btn-primary' href='userdatabase.php'>Create database</a>";
            echo "</div>";
        }
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped align-middle'>";
        echo "<thead><tr><th>Database</th><th>PostGIS</th><th>GC2 settings schema</th><th>Action</th></tr></thead><tbody>";
        foreach ($arr['data'] as $db) {
            if (in_array($db, $systemDbs)) {
                continue;
            }
            $postgisInstalled = false;
            if ($db != "template1" and $db != "template0" and $db != "postgres" and $db != "postgis_template") {
                echo "<tr><td>" . htmlspecialchars($db) . "</td>";
                $dbc = new Dbcheck(connection: new Connection(database: $db));
                $dbc->connect();
                // Check if postgis is installed
                try {
                    $checkPostGIS = $dbc->isPostGISInstalled();
                    echo "<td><span class='badge text-bg-success'>OK</span></td>";
                    $postgisInstalled = true;
                } catch (Exception $e) {
                    echo "<td><span class='badge text-bg-danger'>Missing</span></td>";
                }
                // Check if schema \"settings\" is loaded
                $actionCell = "<td></td>";
                try {
                    $checkMy = $dbc->isSchemaInstalled();
                    echo "<td><span class='badge text-bg-success'>OK</span></td>";
                } catch (Exception) {
                    echo "<td><span class='badge text-bg-danger'>Missing</span></td>";
                    if (!$postgisInstalled) {
                        $actionCell = "<td><a class='btn btn-primary btn-sm' href='prepare.php?db=" . urlencode($db) . "'>Install</a></td>";
                    } else {
                        $actionCell = "<td></td>";
                    }
                }
                echo $actionCell;
                echo "</tr>";
                $dbc->close();
            }
            $i++;
            if ($i > 1000) break;
        }
        echo "</tbody></table></div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Could not connect to PostgreSQL " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
</main>
</body>
</html>

