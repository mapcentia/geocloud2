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
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN privileges TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ALTER sort_id SET DEFAULT 0";
        $sqls[] = "UPDATE settings.geometry_columns_join SET sort_id = 0 WHERE sort_id IS NULL";
        $sqls[] = "CREATE EXTENSION \"uuid-ossp\"";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join DROP f_table_schema";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join DROP f_table_name";
        $sqls[] = "CREATE EXTENSION \"hstore\"";
        $sqls[] = "CREATE EXTENSION \"dblink\"";
        $sqls[] = "CREATE EXTENSION \"pgcrypto\"";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD PRIMARY KEY (_key_)";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN classwizard TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN enablesqlfilter BOOL";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ALTER enablesqlfilter SET DEFAULT FALSE";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN triggertable VARCHAR(255)";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN classwizard TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN extra TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD skipconflict BOOL DEFAULT FALSE";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ALTER wmssource TYPE TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN roles TEXT";
        $sqls[] = "CREATE TABLE settings.workflow
                    (
                      id SERIAL NOT NULL,
                      f_schema_name CHARACTER VARYING(255),
                      f_table_name CHARACTER VARYING(255),
                      gid INTEGER,
                      status INTEGER,
                      gc2_user CHARACTER VARYING(255),
                      roles hstore,
                      workflow hstore,
                      version_gid INTEGER,
                      operation CHARACTER VARYING(255),
                      created TIMESTAMP WITH TIME ZONE DEFAULT ('now'::TEXT)::TIMESTAMP(0) WITH TIME ZONE
                    )";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN elasticsearch TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN uuid UUID NOT NULL DEFAULT uuid_generate_v4()";

        $sqls[] = "CREATE OR REPLACE VIEW non_postgis_tables AS
                     SELECT t.table_name::character varying(256) AS f_table_name,
                        t.table_schema::character varying(256) AS f_table_schema,
                        'gc2_non_postgis'::character varying(256) AS f_geometry_column,
                        NULL::integer AS coord_dimension,
                        NULL::integer AS srid,
                        NULL::character varying(30) AS type
                       FROM information_schema.tables t
                          WHERE
                                NOT (EXISTS ( SELECT 1
                                  FROM geometry_columns
                                      WHERE
                                            t.table_name::text = geometry_columns.f_table_name::text
                                      )) AND

                                NOT (EXISTS ( SELECT 1
                                  FROM raster_columns
                                      WHERE
                                            t.table_name::text = raster_columns.r_table_name::text
                                      ))

                              AND
                                  NOT t.table_schema::text = 'settings'::text AND
                                  NOT (t.table_schema::text = 'public'::text AND
                                  t.table_name::text = 'spatial_ref_sys'::text) AND
                                  NOT (t.table_schema::text = 'public'::text AND t.table_name::text = 'geometry_columns'::text) AND
                                  NOT (t.table_schema::text = 'public'::text AND t.table_name::text = 'geography_columns'::text) AND
                                  NOT (t.table_schema::text = 'public'::text AND t.table_name::text = 'raster_columns'::text) AND
                                  NOT (t.table_schema::text = 'public'::text AND t.table_name::text = 'raster_overviews'::text) AND
                                  NOT (t.table_schema::text = 'public'::text AND t.table_name::text = 'non_postgis_tables'::text) AND
                                  NOT t.table_schema::text = 'pg_catalog'::text AND NOT t.table_schema::text = 'information_schema'::text;
                    ";
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
                        geometry_columns_join.uuid
                      FROM geometry_columns
                        LEFT JOIN
                        settings.geometry_columns_join ON
                                                         geometry_columns.f_table_schema || '.' || geometry_columns.f_table_name || '.' || geometry_columns.f_geometry_column =
                                                         geometry_columns_join._key_
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
                        geometry_columns_join.uuid

                      FROM raster_columns
                        LEFT JOIN
                        settings.geometry_columns_join ON
                                                         raster_columns.r_table_schema || '.' || raster_columns.r_table_name || '.' || raster_columns.r_raster_column =
                                                         geometry_columns_join._key_

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
                        geometry_columns_join.uuid

                      FROM non_postgis_tables
                        LEFT JOIN
                        settings.geometry_columns_join ON
                                                         non_postgis_tables.f_table_schema || '.' || non_postgis_tables.f_table_name || '.' || non_postgis_tables.f_geometry_column =
                                                         geometry_columns_join._key_

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
                                geometry_columns_join.uuid

                              FROM geometry_columns
                                LEFT JOIN
                                settings.geometry_columns_join ON
                                                                 geometry_columns.f_table_schema || ''.'' || geometry_columns.f_table_name || ''.'' || geometry_columns.f_geometry_column =
                                                                 geometry_columns_join._key_
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
                                geometry_columns_join.uuid

                              FROM raster_columns
                                LEFT JOIN
                                settings.geometry_columns_join ON
                                                                 raster_columns.r_table_schema || ''.'' || raster_columns.r_table_name || ''.'' || raster_columns.r_raster_column =
                                                                 geometry_columns_join._key_
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
                                geometry_columns_join.uuid

                              FROM non_postgis_tables

                              LEFT JOIN
                                settings.geometry_columns_join ON
                                                                 non_postgis_tables.f_table_schema || ''.'' || non_postgis_tables.f_table_name || ''.'' || non_postgis_tables.f_geometry_column =
                                                                 geometry_columns_join._key_
                              WHERE ' || $1 || '

                        ';
                      END;
                      $$ LANGUAGE PLPGSQL;
        ";
        return $sqls;
    }

    public static function mapcentia()
    {
        $sqls[] = "ALTER TABLE users ADD COLUMN parentdb VARCHAR(255)";
        $sqls[] = "ALTER TABLE users ADD COLUMN usergroup VARCHAR(255)";
        return $sqls;
    }

    public static function gc2scheduler()
    {
        $sqls[] = "ALTER TABLE jobs ALTER url TYPE TEXT";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN delete_append BOOL DEFAULT FALSE ";
        return $sqls;
    }
}
