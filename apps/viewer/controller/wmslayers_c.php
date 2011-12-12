<?php
$table = new table("settings.geometry_columns_view");
$responseWmsLayers = $table -> getRecords("f_table_schema='{$postgisschema}'");