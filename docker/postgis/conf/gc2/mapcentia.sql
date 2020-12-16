CREATE USER gc2 WITH SUPERUSER
  CREATEROLE
  CREATEDB
  PASSWORD 'xx';
CREATE DATABASE template_geocloud ENCODING 'UTF-8' LC_COLLATE 'da_DK.UFT-8' LC_CTYPE 'da_DK.UFT-8' TEMPLATE template0;
create extension postgis;
create extension pgcrypto;
create extension pgrouting;


CREATE DATABASE mapcentia ENCODING 'UTF-8' TEMPLATE template0;
CREATE TABLE users
(
  screenname CHARACTER VARYING(255),
  pw         CHARACTER VARYING(255),
  email      CHARACTER VARYING(255),
  zone       CHARACTER VARYING,
  parentdb   VARCHAR(255),
  created    TIMESTAMP WITH TIME ZONE DEFAULT ('now' :: TEXT) :: TIMESTAMP(0) WITH TIME ZONE
);
