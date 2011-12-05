<?php
$table = new table("settings.geometry_columns_join");
$responseWmsLayers = $table -> getRecords("f_table_schema='{$postgisschema}'");