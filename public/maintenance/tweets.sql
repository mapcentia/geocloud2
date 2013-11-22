--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;


SET default_tablespace = '';

SET default_with_oids = true;

--
-- Name: tweets; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE IF NOT EXISTS tweets (
    gid integer NOT NULL,
    id bigint,
    the_geom geometry(Point,4326),
    text character varying(255),
    created_at character varying(255),
    source character varying(255),
    user_name character varying(255),
    user_screen_name character varying(255),
    user_id bigint,
    place_id character varying(255),
    place_type character varying(255),
    place_full_name character varying(255),
    place_country_code character varying(255),
    place_country character varying(255),
    retweet_count integer,
    favorite_count integer,
    entities text
);


ALTER TABLE tweets OWNER TO postgres;

--
-- Name: tweets_gid_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE tweets_gid_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE tweets_gid_seq OWNER TO postgres;

--
-- Name: tweets_gid_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE tweets_gid_seq OWNED BY tweets.gid;


--
-- Name: gid; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY tweets ALTER COLUMN gid SET DEFAULT nextval('tweets_gid_seq'::regclass);


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

CREATE INDEX tweets_the_geom_gist ON tweets USING gist (the_geom);


--
-- PostgreSQL database dump complete
--

