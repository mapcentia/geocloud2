<?php
class Sql
{
    public static function get()
    {
        $sqls[] = "DROP VIEW settings.geometry_columns_view CASCADE";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN filter TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN cartomobile TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN bitmapsource VARCHAR(255)";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD CONSTRAINT geometry_columns_join_key UNIQUE (_key_)";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ALTER data TYPE TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ALTER sort_id set default 0";
        $sqls[] = "UPDATE settings.geometry_columns_join SET sort_id = 0 WHERE sort_id IS NULL";

        $sqls[] = "CREATE VIEW settings.geometry_columns_view AS
                      (SELECT
                          foo.f_table_schema,
                          foo.f_table_name,
                          foo.f_geometry_column,
                          foo.coord_dimension,
                          foo.srid,
                          foo.type,

                            _key_,
                            f_table_abstract,
                            f_table_title,
                            tweet,
                            editable,
                            created,
                            lastmodified,
                            authentication,
                            fieldconf,
                            meta_url,
                            layergroup,
                            def,
                            class,
                            wmssource,
                            baselayer,
                            sort_id,
                            tilecache,
                            data,
                            not_querable,
                            single_tile,
                            cartomobile,
                            filter,
                            bitmapsource

                          FROM (
                              SELECT
                                geometry_columns.f_table_schema,
                                geometry_columns.f_table_name,
                                geometry_columns.f_geometry_column,
                                geometry_columns.coord_dimension,
                                geometry_columns.srid,
                                geometry_columns.type

                              FROM geometry_columns

                              UNION ALL
                              SELECT
                                raster_columns.r_table_schema as f_table_schema,
                                raster_columns.r_table_name as f_table_name,
                                raster_columns.r_raster_column as f_geometry_column,
                                2 as coord_dimension,
                                raster_columns.srid,
                                'RASTER' as type

                              FROM raster_columns
                              ) AS foo

                            LEFT JOIN
                                settings.geometry_columns_join ON
                                                             foo.f_table_schema || '.' || foo.f_table_name || '.' || foo.f_geometry_column::text =
                                                             geometry_columns_join._key_::text)
                    ";
        return $sqls;
    }
}

