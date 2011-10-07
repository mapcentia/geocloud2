<?php

$sqls[] = " 
--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

--
-- Name: settings; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA settings;


ALTER SCHEMA settings OWNER TO postgres;

SET search_path = settings, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: geometry_columns_join; Type: TABLE; Schema: settings; Owner: postgres; Tablespace:
--

CREATE TABLE geometry_columns_join (
    f_table_name character varying(256),
    f_table_abstract character varying(256),
    f_table_title character varying(256),
    tweet text,
    editable text DEFAULT 1,
    created timestamp with time zone DEFAULT ('now'::text)::timestamp(0) with time zone,
    lastmodified timestamp with time zone DEFAULT ('now'::text)::timestamp(0) with time zone,
    authentication text DEFAULT 'Write'::text,
    fieldconf text,
    meta_url text,
    layergroup character varying(255)
);


ALTER TABLE settings.geometry_columns_join OWNER TO postgres;

--
-- Name: classes; Type: TABLE; Schema: settings; Owner: postgres; Tablespace:
--

CREATE TABLE classes (
    id integer NOT NULL,
    layer character varying,
    class text
);


ALTER TABLE settings.classes OWNER TO postgres;

--
-- Name: classes_id_seq; Type: SEQUENCE; Schema: settings; Owner: postgres
--

CREATE SEQUENCE classes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;


ALTER TABLE settings.classes_id_seq OWNER TO postgres;

--
-- Name: classes_id_seq; Type: SEQUENCE OWNED BY; Schema: settings; Owner: postgres
--

ALTER SEQUENCE classes_id_seq OWNED BY classes.id;


--
-- Name: viewer; Type: TABLE; Schema: settings; Owner: postgres; Tablespace:
--

CREATE TABLE viewer (
    viewer text
);


ALTER TABLE settings.viewer OWNER TO postgres;

--
-- Name: wmslayers; Type: TABLE; Schema: settings; Owner: postgres; Tablespace:
--

CREATE TABLE wmslayers (
    layer character varying,
    def text
);


ALTER TABLE settings.wmslayers OWNER TO postgres;

--
-- Name: id; Type: DEFAULT; Schema: settings; Owner: postgres
--

ALTER TABLE classes ALTER COLUMN id SET DEFAULT nextval('classes_id_seq'::regclass);


--
-- Name: settings; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA settings FROM PUBLIC;
REVOKE ALL ON SCHEMA settings FROM postgres;
GRANT ALL ON SCHEMA settings TO postgres;
GRANT ALL ON SCHEMA settings TO user_mhoegh;


--
-- Name: geometry_columns_join; Type: ACL; Schema: settings; Owner: postgres
--

REVOKE ALL ON TABLE geometry_columns_join FROM PUBLIC;
REVOKE ALL ON TABLE geometry_columns_join FROM postgres;
GRANT ALL ON TABLE geometry_columns_join TO postgres;
GRANT ALL ON TABLE geometry_columns_join TO user_mhoegh;


--
-- Name: classes; Type: ACL; Schema: settings; Owner: postgres
--

REVOKE ALL ON TABLE classes FROM PUBLIC;
REVOKE ALL ON TABLE classes FROM postgres;
GRANT ALL ON TABLE classes TO postgres;
GRANT ALL ON TABLE classes TO user_mhoegh;


--
-- Name: viewer; Type: ACL; Schema: settings; Owner: postgres
--

REVOKE ALL ON TABLE viewer FROM PUBLIC;
REVOKE ALL ON TABLE viewer FROM postgres;
GRANT ALL ON TABLE viewer TO postgres;
GRANT ALL ON TABLE viewer TO user_mhoegh;


--
-- Name: wmslayers; Type: ACL; Schema: settings; Owner: postgres
--

REVOKE ALL ON TABLE wmslayers FROM PUBLIC;
REVOKE ALL ON TABLE wmslayers FROM postgres;
GRANT ALL ON TABLE wmslayers TO postgres;
GRANT ALL ON TABLE wmslayers TO user_mhoegh;


--
-- PostgreSQL database dump complete
--
";
$sqls[] = "
SELECT geometry_columns.f_table_schema, geometry_columns.f_table_name, geometry_columns.f_geometry_column, geometry_columns.coord_dimension, geometry_columns.srid, geometry_columns.type, geometry_columns_join.f_table_abstract, geometry_columns_join.f_table_title, geometry_columns_join.tweet, geometry_columns_join.editable, geometry_columns_join.created, geometry_columns_join.lastmodified, geometry_columns_join.authentication, geometry_columns_join.fieldconf, geometry_columns_join.meta_url, geometry_columns_join.layergroup
   FROM geometry_columns
   LEFT JOIN settings.geometry_columns_join ON geometry_columns.f_table_name::text = geometry_columns_join.f_table_name::text;
";

//$sqls[] = "DROP schema settings cascade";