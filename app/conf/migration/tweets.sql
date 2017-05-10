--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = ON;
SET check_function_bodies = FALSE;
SET client_min_messages = WARNING;


SET default_tablespace = '';

SET default_with_oids = TRUE;

--
-- Name: tweets; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE IF NOT EXISTS tweets (
  gid                INTEGER NOT NULL,
  id                 BIGINT,
  the_geom           GEOMETRY(POINT, 4326),
  text               CHARACTER VARYING(255),
  created_at         CHARACTER VARYING(255),
  source             CHARACTER VARYING(255),
  user_name          CHARACTER VARYING(255),
  user_screen_name   CHARACTER VARYING(255),
  user_id            BIGINT,
  place_id           CHARACTER VARYING(255),
  place_type         CHARACTER VARYING(255),
  place_full_name    CHARACTER VARYING(255),
  place_country_code CHARACTER VARYING(255),
  place_country      CHARACTER VARYING(255),
  retweet_count      INTEGER,
  favorite_count     INTEGER,
  entities           TEXT,
  extended_entities  TEXT,
  full_json          JSON

);


ALTER TABLE tweets
  OWNER TO postgres;

--
-- Name: tweets_gid_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE tweets_gid_seq
START WITH 1
INCREMENT BY 1
NO MINVALUE
NO MAXVALUE
CACHE 1;


ALTER TABLE tweets_gid_seq
  OWNER TO postgres;

--
-- Name: tweets_gid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE tweets_gid_seq OWNED BY tweets.gid;

--
-- Name: gid; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY tweets
  ALTER COLUMN gid SET DEFAULT nextval('tweets_gid_seq' :: REGCLASS);

--
-- Name: constraintname; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY tweets
  ADD CONSTRAINT constraintname UNIQUE (id);

--
-- Name: tweets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY tweets
  ADD CONSTRAINT tweets_pkey PRIMARY KEY (gid);

--
-- Name: tweets_the_geom_gist; Type: INDEX; Schema: public; Owner: postgres; Tablespace: 
--

CREATE INDEX tweets_the_geom_gist
  ON tweets USING GIST (the_geom);

--
-- PostgreSQL database dump complete
--

