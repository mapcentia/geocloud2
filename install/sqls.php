<?php
$sqls['schema'] = " 
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
    f_table_name character varying(256),
    f_table_schema character varying(256),
    f_table_abstract character varying(256),
    f_table_title character varying(256),
    tweet text,
    editable text DEFAULT 1,
    created timestamp with time zone DEFAULT ('now'::text)::timestamp(0) with time zone,
    lastmodified timestamp with time zone DEFAULT ('now'::text)::timestamp(0) with time zone,
    authentication text DEFAULT 'Write'::text,
    fieldconf text,
    meta_url text,
    class text,
    layergroup character varying(255)
);

CREATE TABLE classes (
    id integer NOT NULL,
    layer character varying,
    class text
);

CREATE SEQUENCE classes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

ALTER SEQUENCE classes_id_seq OWNED BY classes.id;

SELECT pg_catalog.setval('classes_id_seq', 2, true);

CREATE TABLE viewer (
    viewer text
);

CREATE TABLE wmslayers (
    layer character varying,
    def text
);

ALTER TABLE classes ALTER COLUMN id SET DEFAULT nextval('classes_id_seq'::regclass);

INSERT INTO viewer VALUES ('{\"pw\":\"81dc9bdb52d04dc20036dbd8313ed055\"}');

SET search_path = public, pg_catalog;
";

$sqls['view'] = "
CREATE VIEW settings.geometry_columns_view AS SELECT geometry_columns.f_table_schema, geometry_columns.f_table_name, geometry_columns.f_geometry_column, geometry_columns.coord_dimension, geometry_columns.srid, geometry_columns.type, geometry_columns_join.f_table_abstract, geometry_columns_join.f_table_title, geometry_columns_join.tweet, geometry_columns_join.editable, geometry_columns_join.created, geometry_columns_join.lastmodified, geometry_columns_join.authentication, geometry_columns_join.fieldconf, geometry_columns_join.meta_url, geometry_columns_join.layergroup
   FROM geometry_columns
   LEFT JOIN settings.geometry_columns_join ON geometry_columns.f_table_name::text = geometry_columns_join.f_table_name::text AND geometry_columns.f_table_schema::text = geometry_columns_join.f_table_schema::text;
";