<?php
// Join on table, schema and geometry column
$sqls[] = "CREATE VIEW settings.geometry_columns_view AS
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
                        geometry_columns_join.filter,
                        geometry_columns_join.bitmapsource,
                        geometry_columns_join.privileges,
                        geometry_columns_join.enablesqlfilter,
                        geometry_columns_join.triggertable,
                        geometry_columns_join.classwizard,
                        geometry_columns_join.extra,
                        geometry_columns_join.skipconflict,
                        geometry_columns_join.roles,
                        geometry_columns_join.elasticsearch,
                        geometry_columns_join.uuid,
                        geometry_columns_join.tags,
                        geometry_columns_join.meta,
                        geometry_columns_join.wmsclientepsgs,
                        geometry_columns_join.featureid,
                        geometry_columns_join.note,
                        geometry_columns_join.legend_url,
                        geometry_columns_join.enableows
                      FROM geometry_columns
                        LEFT JOIN
                        settings.geometry_columns_join ON geometry_columns_join._key_ =
                                                         geometry_columns.f_table_schema || '.' || geometry_columns.f_table_name || '.' || geometry_columns.f_geometry_column
                                                         
                      UNION ALL
                      SELECT
                        raster_columns.r_table_schema as f_table_schema,
                        raster_columns.r_table_name as f_table_name,
                        raster_columns.r_raster_column as f_geometry_column,
                        2 as coord_dimension,
                        raster_columns.srid,
                        'RASTER' as type,

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
                        geometry_columns_join.filter,
                        geometry_columns_join.bitmapsource,
                        geometry_columns_join.privileges,
                        geometry_columns_join.enablesqlfilter,
                        geometry_columns_join.triggertable,
                        geometry_columns_join.classwizard,
                        geometry_columns_join.extra,
                        geometry_columns_join.skipconflict,
                        geometry_columns_join.roles,
                        geometry_columns_join.elasticsearch,
                        geometry_columns_join.uuid,
                        geometry_columns_join.tags,
                        geometry_columns_join.meta,
                        geometry_columns_join.wmsclientepsgs,
                        geometry_columns_join.featureid,
                        geometry_columns_join.note,
                        geometry_columns_join.legend_url,
                        geometry_columns_join.enableows

                      FROM raster_columns
                        LEFT JOIN
                        settings.geometry_columns_join ON geometry_columns_join._key_ =
                                                         raster_columns.r_table_schema || '.' || raster_columns.r_table_name || '.' || raster_columns.r_raster_column
                                                         

                      UNION ALL
                      select
                        non_postgis_tables.f_table_schema,
                        non_postgis_tables.f_table_name,
                        non_postgis_tables.f_geometry_column,
                        non_postgis_tables.coord_dimension,
                        non_postgis_tables.srid,
                        non_postgis_tables.type,

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
                        geometry_columns_join.filter,
                        geometry_columns_join.bitmapsource,
                        geometry_columns_join.privileges,
                        geometry_columns_join.enablesqlfilter,
                        geometry_columns_join.triggertable,
                        geometry_columns_join.classwizard,
                        geometry_columns_join.extra,
                        geometry_columns_join.skipconflict,
                        geometry_columns_join.roles,
                        geometry_columns_join.elasticsearch,
                        geometry_columns_join.uuid,
                        geometry_columns_join.tags,
                        geometry_columns_join.meta,
                        geometry_columns_join.wmsclientepsgs,
                        geometry_columns_join.featureid,
                        geometry_columns_join.note,
                        geometry_columns_join.legend_url,
                        geometry_columns_join.enableows

                      FROM non_postgis_tables
                        LEFT JOIN
                        settings.geometry_columns_join ON geometry_columns_join._key_ =
                                                         non_postgis_tables.f_table_schema || '.' || non_postgis_tables.f_table_name || '.' || non_postgis_tables.f_geometry_column
                                                         
                      UNION ALL
                      select
                        non_postgis_matviews.f_table_schema,
                        non_postgis_matviews.f_table_name,
                        non_postgis_matviews.f_geometry_column,
                        non_postgis_matviews.coord_dimension,
                        non_postgis_matviews.srid,
                        non_postgis_matviews.type,

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
                        geometry_columns_join.filter,
                        geometry_columns_join.bitmapsource,
                        geometry_columns_join.privileges,
                        geometry_columns_join.enablesqlfilter,
                        geometry_columns_join.triggertable,
                        geometry_columns_join.classwizard,
                        geometry_columns_join.extra,
                        geometry_columns_join.skipconflict,
                        geometry_columns_join.roles,
                        geometry_columns_join.elasticsearch,
                        geometry_columns_join.uuid,
                        geometry_columns_join.tags,
                        geometry_columns_join.meta,
                        geometry_columns_join.wmsclientepsgs,
                        geometry_columns_join.featureid,
                        geometry_columns_join.note,
                        geometry_columns_join.legend_url,
                        geometry_columns_join.enableows

                      FROM non_postgis_matviews
                        LEFT JOIN
                        settings.geometry_columns_join ON geometry_columns_join._key_ =
                                                         non_postgis_matviews.f_table_schema || '.' || non_postgis_matviews.f_table_name || '.' || non_postgis_matviews.f_geometry_column
                                                         

                    ";
$sqls[] = "
                      CREATE OR REPLACE FUNCTION settings.getColumns(g text, r text) RETURNS SETOF settings.geometry_columns_view AS $$
                      BEGIN
                        RETURN QUERY EXECUTE '
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
                                geometry_columns_join.filter,
                                geometry_columns_join.bitmapsource,
                                geometry_columns_join.privileges,
                                geometry_columns_join.enablesqlfilter,
                                geometry_columns_join.triggertable,
                                geometry_columns_join.classwizard,
                                geometry_columns_join.extra,
                                geometry_columns_join.skipconflict,
                                geometry_columns_join.roles,
                                geometry_columns_join.elasticsearch,
                                geometry_columns_join.uuid,
                                geometry_columns_join.tags,
                                geometry_columns_join.meta,
                                geometry_columns_join.wmsclientepsgs,
                                geometry_columns_join.featureid,
                                geometry_columns_join.note,
                                geometry_columns_join.legend_url,
                                geometry_columns_join.enableows

                              FROM geometry_columns
                                LEFT JOIN
                                settings.geometry_columns_join ON geometry_columns_join._key_ =
                                                                 geometry_columns.f_table_schema || ''.'' || geometry_columns.f_table_name || ''.'' || geometry_columns.f_geometry_column
                                                                 
                              WHERE ' || $1 || '

                              UNION ALL
                              SELECT
                                raster_columns.r_table_schema as f_table_schema,
                                raster_columns.r_table_name as f_table_name,
                                raster_columns.r_raster_column as f_geometry_column,
                                2 as coord_dimension,
                                raster_columns.srid,
                                ''RASTER'' as type,

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
                                geometry_columns_join.filter,
                                geometry_columns_join.bitmapsource,
                                geometry_columns_join.privileges,
                                geometry_columns_join.enablesqlfilter,
                                geometry_columns_join.triggertable,
                                geometry_columns_join.classwizard,
                                geometry_columns_join.extra,
                                geometry_columns_join.skipconflict,
                                geometry_columns_join.roles,
                                geometry_columns_join.elasticsearch,
                                geometry_columns_join.uuid,
                                geometry_columns_join.tags,
                                geometry_columns_join.meta,
                                geometry_columns_join.wmsclientepsgs,
                                geometry_columns_join.featureid,
                                geometry_columns_join.note,
                                geometry_columns_join.legend_url,
                                geometry_columns_join.enableows

                              FROM raster_columns
                                LEFT JOIN
                                settings.geometry_columns_join ON geometry_columns_join._key_ =
                                                                 raster_columns.r_table_schema || ''.'' || raster_columns.r_table_name || ''.'' || raster_columns.r_raster_column
                                                                 
                              WHERE ' || $2 || '

                              UNION ALL

                              select
                                non_postgis_tables.f_table_schema,
                                non_postgis_tables.f_table_name,
                                non_postgis_tables.f_geometry_column,
                                non_postgis_tables.coord_dimension,
                                non_postgis_tables.srid,
                                non_postgis_tables.type,

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
                                geometry_columns_join.filter,
                                geometry_columns_join.bitmapsource,
                                geometry_columns_join.privileges,
                                geometry_columns_join.enablesqlfilter,
                                geometry_columns_join.triggertable,
                                geometry_columns_join.classwizard,
                                geometry_columns_join.extra,
                                geometry_columns_join.skipconflict,
                                geometry_columns_join.roles,
                                geometry_columns_join.elasticsearch,
                                geometry_columns_join.uuid,
                                geometry_columns_join.tags,
                                geometry_columns_join.meta,
                                geometry_columns_join.wmsclientepsgs,
                                geometry_columns_join.featureid,
                                geometry_columns_join.note,
                                geometry_columns_join.legend_url,
                                geometry_columns_join.enableows

                              FROM non_postgis_tables

                              LEFT JOIN
                                settings.geometry_columns_join ON geometry_columns_join._key_ =
                                                                 non_postgis_tables.f_table_schema || ''.'' || non_postgis_tables.f_table_name || ''.'' || non_postgis_tables.f_geometry_column
                                                                 
                              WHERE ' || $1 || '

                              UNION ALL

                              select
                                non_postgis_matviews.f_table_schema,
                                non_postgis_matviews.f_table_name,
                                non_postgis_matviews.f_geometry_column,
                                non_postgis_matviews.coord_dimension,
                                non_postgis_matviews.srid,
                                non_postgis_matviews.type,

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
                                geometry_columns_join.filter,
                                geometry_columns_join.bitmapsource,
                                geometry_columns_join.privileges,
                                geometry_columns_join.enablesqlfilter,
                                geometry_columns_join.triggertable,
                                geometry_columns_join.classwizard,
                                geometry_columns_join.extra,
                                geometry_columns_join.skipconflict,
                                geometry_columns_join.roles,
                                geometry_columns_join.elasticsearch,
                                geometry_columns_join.uuid,
                                geometry_columns_join.tags,
                                geometry_columns_join.meta,
                                geometry_columns_join.wmsclientepsgs,
                                geometry_columns_join.featureid,
                                geometry_columns_join.note,
                                geometry_columns_join.legend_url,
                                geometry_columns_join.enableows
                                
                              FROM non_postgis_matviews

                              LEFT JOIN
                                settings.geometry_columns_join ON geometry_columns_join._key_ = 
                                                                 non_postgis_matviews.f_table_schema || ''.'' || non_postgis_matviews.f_table_name || ''.'' || non_postgis_matviews.f_geometry_column
                                                                 
                              WHERE ' || $1 || '

                        ';
                      END;
                      $$ LANGUAGE PLPGSQL;
        ";
