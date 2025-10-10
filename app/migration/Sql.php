<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\migration;

use app\conf\App;

/**
 * Class Sql
 * @package app\conf\migration
 */
class Sql
{
    /**
     * @return array<string>
     */
    public static function get(): array
    {
        $sqls[] = "DROP VIEW settings.geometry_columns_view CASCADE";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN filter TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN cartomobile TEXT";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN bitmapsource VARCHAR(255)";
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
        $sqls[] = "CREATE TABLE settings.seed_jobs
                    (
                      uuid      UUID                      NOT NULL  DEFAULT uuid_generate_v4()  PRIMARY KEY,
                      name      CHARACTER VARYING(255)    NOT NULL,
                      pid       INTEGER                   NOT NULL,
                      host      CHARACTER VARYING(255)    NOT NULL,
                      created   TIMESTAMP WITH TIME ZONE  NOT NULL  DEFAULT ('now'::TEXT)::TIMESTAMP(0) WITH TIME ZONE
                    )";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN legend_url VARCHAR(255)";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ALTER roles TYPE JSONB USING roles::jsonb";
        $sqls[] = "CREATE TABLE settings.geofence
                    (
                    id                   serial UNIQUE NOT NULL,
                    priority             integer NOT NULL default 1::int,
                    username             varchar default '*'::character varying,
                    service              varchar default '*'::character varying,
                    request              varchar default '*'::character varying,
                    layer                varchar default '*'::character varying,
                    iprange              varchar default '*'::character varying,
                    schema               varchar default '*'::character varying,
                    access               varchar default 'deny'::character varying,
                    filter         text,
                    CHECK (service in ('*', 'sql', 'ows', 'wfst')),
                    CHECK (request in ('*', 'select', 'insert', 'update', 'delete')),
                    CHECK (access in ('*', 'allow', 'deny', 'limit'))
                )";
        $sqls[] = "ALTER TABLE settings.key_value ADD COLUMN created TIMESTAMP WITH TIME ZONE DEFAULT ('now'::TEXT)::TIMESTAMP(0) WITH TIME ZONE";
        $sqls[] = "ALTER TABLE settings.key_value ADD COLUMN updated TIMESTAMP WITH TIME ZONE DEFAULT ('now'::TEXT)::TIMESTAMP(0) WITH TIME ZONE";
        $sqls[] = "CREATE TABLE settings.symbols
                    (
                        id                   varchar not null PRIMARY KEY,
                        rotation             float,
                        scale                float,
                        zoom                 int,
                        svg                  text,
                        browserid            varchar,
                        userid               varchar,  
                        anonymous            bool,
                        file                 varchar,  
                        tag                  varchar,
                        the_geom             geometry(POINT, 4326)
                    )";
        $sqls[] = "ALTER TABLE settings.symbols ADD COLUMN timestamp TIMESTAMP WITH TIME ZONE DEFAULT ('now'::TEXT)::TIMESTAMP(0) WITH TIME ZONE";
        $sqls[] = "ALTER TABLE settings.symbols ADD COLUMN properties jsonb";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN enableows BOOL DEFAULT TRUE NOT NULL";
        $sqls[] = "create table settings.views
                    (
                        name                  varchar                                not null,
                        schemaname            varchar                                not null,
                        owner                 varchar                                not null,
                        definition            text                                   not null,
                        timestamp             timestamp with time zone default now() not null,
                        ismat                 boolean                                not null,
                        constraint views_pk
                            primary key (name, schemaname)
                    )";
        $sqls[] = "create table settings.clients
                    (
                        id                    varchar                                not null,
                        name                  varchar                                not null,
                        homepage              varchar                                        ,
                        description           text                                           ,
                        redirect_uri          varchar not null                               ,
                        secret                varchar                                        ,
                        constraint clients_pk
                            primary key (id)
                    )";
        $sqls[] = "alter table settings.clients add \"public\" boolean default false not null";
        $sqls[] = "alter table settings.clients add confirm boolean default true not null";
        $sqls[] = "alter table settings.clients add twofactor boolean default true not null";
        $sqls[] = "INSERT INTO settings.clients (id, name, description, redirect_uri, public, confirm) values ('gc2-cli', 'gc2-cli', 'Client for use in CLI','[\"http://127.0.0.1:5657/auth/callback\"]', true, false)";
        $sqls[] = "create table settings.cost
                    (
                        id        serial,
                        timestamp timestamp default now() not null,
                        username  varchar(255)            not null,
                        statement text                    not null,
                        cost      float                   not null
                    )";
        $sqls[] = "alter table settings.prepared_statements add type_hints jsonb";
        $sqls[] = "alter table settings.prepared_statements add type_formats jsonb";
        $sqls[] = "alter table settings.prepared_statements add output_format varchar(255)";
        $sqls[] = "alter table settings.prepared_statements add srs int4";
        $sqls[] = "ALTER TABLE settings.geometry_columns_join ADD COLUMN class_cache jsonb";
        $sqls[] = "alter table settings.geometry_columns_join alter class type jsonb using class::jsonb";
        $sqls[] = "ALTER TABLE settings.prepared_statements ADD COLUMN username varchar(255)";
        $sqls[] = "alter table settings.clients rename column twofactor to two_factor";
        $sqls[] = "alter table settings.prepared_statements add output_schema jsonb";
        $sqls[] = "alter table settings.prepared_statements add input_schema jsonb";
        $sqls[] = "alter table settings.prepared_statements add request varchar check (request in ('*', 'select', 'insert', 'update', 'delete', 'merge'))";
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
        if (isset(App::$param['dontUseGeometryColumnInJoin']) && App::$param['dontUseGeometryColumnInJoin'] === true) {
            include 'Views2.php';
        } else {
            include 'Views1.php';
        }
        return $sqls;
    }

    /**
     * @return array<string>
     */
    public static function mapcentia(): array
    {
        $sqls[] = "ALTER TABLE users ADD COLUMN parentdb VARCHAR(255)";
        $sqls[] = "ALTER TABLE users ADD COLUMN usergroup VARCHAR(255)";
        $sqls[] = "ALTER TABLE users ALTER COLUMN screenname SET NOT NULL";
        $sqls[] = "ALTER TABLE users ALTER COLUMN pw SET NOT NULL";
        $sqls[] = "ALTER TABLE users DROP CONSTRAINT user_unique";
        $sqls[] = "ALTER TABLE users ADD CONSTRAINT user_unique UNIQUE (screenname, parentdb)";
        $sqls[] = "ALTER TABLE users ADD COLUMN properties JSONB";
        $sqls[] = "CREATE TABLE logins
                    (
                      id SERIAL NOT NULL,
                      db VARCHAR(255) NOT NULL,
                      \"user\" VARCHAR(255) NOT NULL,
                      timestamp TIMESTAMP DEFAULT now() NOT NULL
                    )";
        $sqls[] = "ALTER TABLE users ADD COLUMN default_user boolean default false not null";
        $sqls[] = "CREATE UNIQUE INDEX only_one_true_default_useridx
                        ON users (default_user, parentdb)
                        WHERE default_user IS TRUE";
        $sqls[] = "ALTER TABLE users ADD CONSTRAINT email_unique_for_parent UNIQUE  (parentdb, email)";
        $sqls[] = "alter table public.users alter column email set not null";
        return $sqls;
    }

    /**
     * @return array<string>
     */
    public static function gc2scheduler(): array
    {
        $sqls[] = "ALTER TABLE jobs ALTER url TYPE TEXT";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN delete_append BOOL DEFAULT FALSE";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN lastrun timestamp with time zone";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN presql text";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN postsql text";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN download_schema BOOL DEFAULT TRUE";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN report jsonb";
        $sqls[] = "ALTER TABLE jobs ADD COLUMN active BOOL DEFAULT TRUE";
        $sqls[] = "CREATE EXTENSION \"uuid-ossp\"";
        $sqls[] = "create table public.started_jobs
                    (
                        uuid    uuid                     default uuid_generate_v4() not null primary key,
                        pid     integer                                             not null,
                        created timestamp with time zone default ('now'::text)::timestamp(0) with time zone,
                        id      integer                                             not null,
                        db      varchar(255)                                        not null,
                        name    varchar(255)
                    );
                  ";
        return $sqls;
    }
}
