SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

CREATE SCHEMA settings;

SET search_path = settings, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

CREATE TABLE geometry_columns_join (
	_key_ varchar(255) not null,
    f_table_abstract character varying(256),
    f_table_title character varying(256),
    tweet text,
    editable bool DEFAULT 'true',
    created timestamp with time zone DEFAULT ('now'::text)::timestamp(0) with time zone,
    lastmodified timestamp with time zone DEFAULT ('now'::text)::timestamp(0) with time zone,
    authentication text DEFAULT 'Write'::text,
    fieldconf text,
    meta_url text,
    class text,
	  def text,
    layergroup character varying(255),
    wmssource character varying(255),
    baselayer bool,
    sort_id int,
    tilecache bool,
    data text,
    not_querable bool,
    single_tile bool,
    cartomobile text,
    filter text,
    bitmapsource VARCHAR(255)
);

ALTER TABLE geometry_columns_join ADD CONSTRAINT geometry_columns_join_key UNIQUE (_key_);

CREATE TABLE viewer (
    viewer text
);

CREATE SCHEMA sqlapi;

INSERT INTO viewer VALUES ('{"pw":"81dc9bdb52d04dc20036dbd8313ed055"}');

SET search_path = public, pg_catalog;


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
    geometry_columns_join.filter,
    geometry_columns_join.bitmapsource
  FROM geometry_columns
    LEFT JOIN
    settings.geometry_columns_join ON
                                     geometry_columns.f_table_schema || '.' || geometry_columns.f_table_name || '.' || geometry_columns.f_geometry_column::text =
                                     geometry_columns_join._key_::text
  UNION
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
    geometry_columns_join.bitmapsource
  FROM raster_columns
    LEFT JOIN
    settings.geometry_columns_join ON
                                     raster_columns.r_table_schema || '.' || raster_columns.r_table_name || '.' || raster_columns.r_raster_column::text =
                                     geometry_columns_join._key_::text;