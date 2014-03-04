<link href="/js/bootstrap/css/bootstrap.css" rel="stylesheet">
<div class="container">
	<div class="row">
		<div class="span9 offset2">
		<?php

		include ("../../app/conf/Connection.php");
		include ("../../app/inc/Model.php");
		include("../../app/models/Database.php");
		include("../../app/models/Dbchecks.php");

		echo "<div>PHP version " . phpversion() . " ";
		if (function_exists(apache_get_modules)) {
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
        $ourFileName = "../../app/wms/cfgfiles/testFile.txt";
        $ourFileHandle = @fopen($ourFileName, 'w');

        if ($ourFileHandle) {
            echo "<div class='alert alert-success'>app/wms/cfgfiles dir is writeable</div>";
            @fclose($ourFileHandle);
            @unlink($ourFileName);
        } else {
            echo "<div class='alert alert-error'>app/wms/cfgfiles dir is not writeable. You must set permissions so the webserver can write in the wms/cfgfiles dir.</div>";
        }

		$mod_rewrite = FALSE;
		if (function_exists("apache_get_modules")) {
			$modules = apache_get_modules();
			$mod_rewrite = in_array("mod_rewrite", $modules);
		}
		if (!isset($mod_rewrite) && isset($_SERVER["HTTP_MOD_REWRITE"])) {
			$mod_rewrite = ($_SERVER["HTTP_MOD_REWRITE"] == "on" ? TRUE : FALSE);
		}
		if (!$mod_rewrite) {
			// last solution; call a specific page as "mod-rewrite" have been enabled; based on result, we decide.
			$result = @file_get_contents("{$hostName}/mod_rewrite");
			$mod_rewrite = ($result == "ok" ? TRUE : FALSE);
		}

		if ($mod_rewrite) {
			echo "<div class='alert alert-success'>Apache mod_rewrite is installed</div>";
		} else {
			echo "<div class='alert alert-error'>Apache mod_rewrite is not installed</div>";
		}

		if (function_exists(ms_newMapobj)) {
			echo "<div class='alert alert-success'>MapScript is installed</div>";
			$mod_apache = true;
		} else {
			echo "<div class='alert alert-error'>MapScript is not installed</div>";
			$mod_apache = false;
		}
		$headers = get_headers("{$hostName}/cgi/tilecache.fcgi", 1);

		if ($headers['Content-Type'] == "text/html") {
			echo "<div class='alert alert-success'>It seems that Python is installed</div>";
		} else {
			echo "<div class='alert alert-error	'>It seems that Python is not installed</div>";
		}

		$dbList = new app\models\Database();
		try {
			$arr = $dbList -> listAllDbs();
		

		$i = 1;
		echo "<table class='table table-striped'>";
		echo "<thead><tr><th>Databases</th><th>PostGIS</th><th>MyGeoCloud</th><th></th></tr></thead>";
		foreach ($arr['data'] as $db) {

			if ($db != "template1" AND $db != "template0" AND $db != "postgres" AND $db != "postgis_template") {
				echo "<tr><td>{$db}</td>";
                \app\models\Database::setDb($db);
				$dbc = new app\models\Dbcheck();

				// Check if postgis is installed
				$checkPostGIS = $dbc->isPostGISInstalled();
				if ($checkPostGIS['success']) {
					echo "<td style='color:green'>V</td>";
				} else {
					echo "<td style='color:red'>X</td>";
				}

				// Check if schema "settings" is loaded
				$checkMy = $dbc->isSchemaInstalled();
				if ($checkMy['success']) {
					echo "<td style='color:green'>V";
					$checkView = $dbc -> isViewInstalled();
					if (!$checkView['success']) {
						echo "<span style='margin-left:20px'>But view is missing</span>";
					}
					echo "</td>";
					echo "<td></td>";
				} else {
					echo "<td style='color:red'>X</td>";
					if ($checkPostGIS['success']) {
						echo "<td><a class='btn btn-primary small' href='installmy.php?db={$db}'>Install MyGeoCloud</a></td>";
					}
					else
					echo "<td></td>";
				}

				echo "</tr>";

			}
			$i++;
		}
		echo "<table>";
		} catch (Exception $e) {
			echo "<div class='alert alert-error'>Could not connect to PostGreSQL</div>";
			//echo "<div class='alert alert-error'>".$e -> getMessage() . "</div>";
		}
	?>

		</div>
		</div>
	</div>

