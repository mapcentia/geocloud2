<?php
$table = new table("settings.geometry_columns_view");
$responseWmsLayers = $table -> getRecords(NULL,"*","1=1 ORDER BY sort_id,f_table_title,f_table_name");