<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\conf\migration;

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
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN note VARCHAR(255)";
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
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN tags JSON";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN meta JSON";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN wmsclientepsgs TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN featureid VARCHAR(255)";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ALTER meta TYPE JSONB";
        $sqls[] = "CREATE INDEX geometry_columns_join_meta_idx ON settings.geometry_columns_join USING gin (meta)";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ALTER tags TYPE JSONB";
        $sqls[] = "CREATE INDEX geometry_columns_join_tags_idx ON settings.geometry_columns_join USING gin (tags)";
        $sqls[] = "CREATE TABLE settings.qgis_files
                    (
                      id VARCHAR(255) NOT NULL UNIQUE PRIMARY KEY,
                      xml TEXT NOT NULL,
                      db VARCHAR(255) NOT NULL,
                      timestamp TIMESTAMP DEFAULT now() NOT NULL
                    )";
        $sqls[] = "CREATE TABLE settings.key_value
                    (
                      id    SERIAL       NOT NULL       CONSTRAINT key_value_id_pk       PRIMARY KEY,
                      key   VARCHAR(256) NOT NULL,
                      value JSONB        NOT NULL
                    )";
        $sqls[] = "CREATE UNIQUE INDEX key_value_key_uindex ON settings.key_value (key)";
        $sqls[] = "UPDATE settings.geometry_columns_join SET wmssource = replace(wmssource, '127.0.0.1', 'gc2core')";
       // $sqls[] = "UPDATE settings.geometry_columns_join SET wmssource = replace(wmssource, 'gc2core', '127.0.0.1')";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ALTER COLUMN authentication TYPE VARCHAR(255) USING authentication::VARCHAR(255)";
        $sqls[] = "CREATE INDEX geometry_columns_join_authentication_idx ON settings.geometry_columns_join (authentication)";
        $sqls[] = "CREATE INDEX geometry_columns_join_baselayer_idx ON settings.geometry_columns_join (baselayer)";
        $sqls[] = "CREATE TABLE settings.prepared_statements
                    (
                      uuid      UUID                      NOT NULL  DEFAULT uuid_generate_v4()  PRIMARY KEY,
                      name      CHARACTER VARYING(255)    NOT NULL,
                      statement text                      NOT NULL,
                      created   TIMESTAMP WITH TIME ZONE  NOT NULL  DEFAULT ('now'::TEXT)::TIMESTAMP(0) WITH TIME ZONE,
                      CONSTRAINT name_unique UNIQUE (name)
                    )";
        $sqls[] = "ALTER TABLE settings.qgis_files ADD COLUMN old BOOLEAN DEFAULT FALSE";

        $sqls[] = "DROP VIEW non_postgis_matviews CASCADE";
        $sqls[] = "CREATE VIEW non_postgis_matviews AS
                    SELECT
                      t.matviewname :: CHARACTER VARYING(256)     AS f_table_name,
                      t.schemaname :: CHARACTER VARYING(256)      AS f_table_schema,
                      'gc2_non_postgis' :: CHARACTER VARYING(256) AS f_geometry_column,
                      NULL :: INTEGER                             AS coord_dimension,
                      NULL :: INTEGER                             AS srid,
                      NULL :: CHARACTER VARYING(30)               AS type
                    FROM pg_matviews t
                      LEFT JOIN geometry_columns
                        ON t.matviewname = geometry_columns.f_table_name AND t.schemaname = geometry_columns.f_table_schema
                      LEFT JOIN raster_columns
                        ON t.matviewname = raster_columns.r_table_name AND t.schemaname = raster_columns.r_table_schema
                    WHERE
                      geometry_columns.f_geometry_column ISNULL AND
                      raster_columns.r_raster_column ISNULL AND
                      NOT t.schemaname :: TEXT = 'settings' :: TEXT AND
                      NOT (t.schemaname :: TEXT = 'public' :: TEXT AND
                           t.schemaname :: TEXT = 'spatial_ref_sys' :: TEXT) AND
                      NOT (t.schemaname :: TEXT = 'public' :: TEXT AND t.matviewname :: TEXT = 'geometry_columns' :: TEXT) AND
                      NOT (t.schemaname :: TEXT = 'public' :: TEXT AND t.matviewname :: TEXT = 'geography_columns' :: TEXT) AND
                      NOT (t.schemaname :: TEXT = 'public' :: TEXT AND t.matviewname :: TEXT = 'raster_columns' :: TEXT) AND
                      NOT (t.schemaname :: TEXT = 'public' :: TEXT AND t.matviewname :: TEXT = 'raster_overviews' :: TEXT) AND
                      NOT (t.schemaname :: TEXT = 'public' :: TEXT AND t.matviewname :: TEXT = 'non_postgis_tables' :: TEXT) AND
                      NOT t.schemaname :: TEXT = 'pg_catalog' :: TEXT AND NOT t.schemaname :: TEXT = 'information_schema' :: TEXT;
                    ";

        $sqls[] = "DROP VIEW non_postgis_tables CASCADE";
        $sqls[] = "CREATE VIEW non_postgis_tables AS
                     SELECT
                      t.table_name :: CHARACTER VARYING(256)      AS f_table_name,
                      t.table_schema :: CHARACTER VARYING(256)    AS f_table_schema,
                      'gc2_non_postgis' :: CHARACTER VARYING(256) AS f_geometry_column,
                      NULL :: INTEGER                             AS coord_dimension,
                      NULL :: INTEGER                             AS srid,
                      NULL :: CHARACTER VARYING(30)               AS type
                    FROM information_schema.tables t
                      LEFT JOIN geometry_columns
                        ON t.table_name = geometry_columns.f_table_name AND t.table_schema = geometry_columns.f_table_schema
                      LEFT JOIN raster_columns
                        ON t.table_name = raster_columns.r_table_name AND t.table_schema = raster_columns.r_table_schema
                    WHERE
                      geometry_columns.f_geometry_column ISNULL AND
                      raster_columns.r_raster_column ISNULL AND
                      NOT t.table_schema :: TEXT = 'settings' :: TEXT AND
                      NOT (t.table_schema :: TEXT = 'public' :: TEXT AND
                           t.table_name :: TEXT = 'spatial_ref_sys' :: TEXT) AND
                      NOT (t.table_schema :: TEXT = 'public' :: TEXT AND t.table_name :: TEXT = 'geometry_columns' :: TEXT) AND
                      NOT (t.table_schema :: TEXT = 'public' :: TEXT AND t.table_name :: TEXT = 'geography_columns' :: TEXT) AND
                      NOT (t.table_schema :: TEXT = 'public' :: TEXT AND t.table_name :: TEXT = 'raster_columns' :: TEXT) AND
                      NOT (t.table_schema :: TEXT = 'public' :: TEXT AND t.table_name :: TEXT = 'raster_overviews' :: TEXT) AND
                      NOT (t.table_schema :: TEXT = 'public' :: TEXT AND t.table_name :: TEXT = 'non_postgis_tables' :: TEXT) AND
                      NOT (t.table_schema :: TEXT = 'public' :: TEXT AND t.table_name :: TEXT = 'non_postgis_matviews' :: TEXT) AND
                      NOT t.table_schema :: TEXT = 'pg_catalog' :: TEXT AND NOT t.table_schema :: TEXT = 'information_schema' :: TEXT;
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
                        geometry_columns_join.uuid,
                        geometry_columns_join.tags,
                        geometry_columns_join.meta,
                        geometry_columns_join.wmsclientepsgs,
                        geometry_columns_join.featureid,
                        geometry_columns_join.note
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
                        geometry_columns_join.uuid,
                        geometry_columns_join.tags,
                        geometry_columns_join.meta,
                        geometry_columns_join.wmsclientepsgs,
                        geometry_columns_join.featureid,
                        geometry_columns_join.note

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
                        geometry_columns_join.uuid,
                        geometry_columns_join.tags,
                        geometry_columns_join.meta,
                        geometry_columns_join.wmsclientepsgs,
                        geometry_columns_join.featureid,
                        geometry_columns_join.note

                      FROM non_postgis_tables
                        LEFT JOIN
                        settings.geometry_columns_join ON
                                                         non_postgis_tables.f_table_schema || '.' || non_postgis_tables.f_table_name || '.' || non_postgis_tables.f_geometry_column =
                                                         geometry_columns_join._key_
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
                        geometry_columns_join.note

                      FROM non_postgis_matviews
                        LEFT JOIN
                        settings.geometry_columns_join ON
                                                         non_postgis_matviews.f_table_schema || '.' || non_postgis_matviews.f_table_name || '.' || non_postgis_matviews.f_geometry_column =
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
                                geometry_columns_join.uuid,
                                geometry_columns_join.tags,
                                geometry_columns_join.meta,
                                geometry_columns_join.wmsclientepsgs,
                                geometry_columns_join.featureid,
                                geometry_columns_join.note

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
                                geometry_columns_join.uuid,
                                geometry_columns_join.tags,
                                geometry_columns_join.meta,
                                geometry_columns_join.wmsclientepsgs,
                                geometry_columns_join.featureid,
                                geometry_columns_join.note

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
                                geometry_columns_join.uuid,
                                geometry_columns_join.tags,
                                geometry_columns_join.meta,
                                geometry_columns_join.wmsclientepsgs,
                                geometry_columns_join.featureid,
                                geometry_columns_join.note

                              FROM non_postgis_tables

                              LEFT JOIN
                                settings.geometry_columns_join ON
                                                                 non_postgis_tables.f_table_schema || ''.'' || non_postgis_tables.f_table_name || ''.'' || non_postgis_tables.f_geometry_column =
                                                                 geometry_columns_join._key_
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
                                geometry_columns_join.note

                              FROM non_postgis_matviews

                              LEFT JOIN
                                settings.geometry_columns_join ON
                                                                 non_postgis_matviews.f_table_schema || ''.'' || non_postgis_matviews.f_table_name || ''.'' || non_postgis_matviews.f_geometry_column =
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
        $sqls[] = "ALTER TABLE users ALTER COLUMN screenname SET NOT NULL";
        $sqls[] = "ALTER TABLE users ALTER COLUMN pw SET NOT NULL";
        $sqls[] = "ALTER TABLE users DROP CONSTRAINT user_unique";
        $sqls[] = "ALTER TABLE users ADD CONSTRAINT user_unique UNIQUE (screenname, parentdb)";
        return $sqls;
    }

    public static function gc2scheduler()
    {
        $sqls[] = "ALTER TABLE jobs ALTER url TYPE TEXT";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN delete_append BOOL DEFAULT FALSE";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN lastrun timestamp with time zone";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN presql text";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN postsql text";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN download_schema BOOL DEFAULT TRUE";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN report jsonb";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN active BOOL DEFAULT TRUE";
        return $sqls;
    }
}
