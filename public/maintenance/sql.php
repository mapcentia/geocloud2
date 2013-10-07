<?php
/*
$sqls[] = "DROP VIEW settings.geometry_columns_view CASCADE";

$sqls[] = "ALTER TABLE settings.geometry_columns_join DROP COLUMN editable";
$sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN editable bool";

$sqls[] = "
CREATE VIEW settings.geometry_columns_view AS 
	SELECT 
		geometry_columns.f_table_schema,
		geometry_columns.f_table_name,
		geometry_columns.f_geometry_column,
		geometry_columns.coord_dimension,
		geometry_columns.srid,
		geometry_columns.type,
		
		geometry_columns_join._key_,
		geometry_columns_join.f_table_abstract,
		geometry_columns_join.f_table_title,
		geometry_columns_join.tweet,
		geometry_columns_join.editable,
		geometry_columns_join.created,
		geometry_columns_join.lastmodified,
		geometry_columns_join.authentication,
		geometry_columns_join.fieldconf,
		geometry_columns_join.meta_url,
		geometry_columns_join.layergroup,
		geometry_columns_join.def,
		geometry_columns_join.class,
		geometry_columns_join.wmssource,
		geometry_columns_join.baselayer,
		geometry_columns_join.sort_id,
		geometry_columns_join.tilecache,
		geometry_columns_join.data,
		geometry_columns_join.not_querable,
		geometry_columns_join.single_tile,
		geometry_columns_join.cartomobile,
		geometry_columns_join.filter
   FROM geometry_columns
   LEFT JOIN 
   		settings.geometry_columns_join ON
   			geometry_columns.f_table_schema || '.' || geometry_columns.f_table_name || '.' || geometry_columns.f_geometry_column::text = 
   			geometry_columns_join._key_::text;
";
*/
$sqls[] = "CREATE SCHEMA sqlapi";