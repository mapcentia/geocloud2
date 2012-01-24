<?php
$table = new table("settings.geometry_columns_view");
$responseWmsLayers = $table -> getRecords("1=1 ORDER BY sort_id,f_table_title");