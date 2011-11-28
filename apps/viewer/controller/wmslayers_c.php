<?php
$table = new table("settings.geometry_columns_join");
$response = $table -> getRecords("f_table_schema='{$postgisschema}'");