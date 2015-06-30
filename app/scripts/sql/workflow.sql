-- DROP TABLE settings.workflow;

CREATE TABLE settings.workflow
(
  id serial NOT NULL,
  f_schema_name character varying(255),
  f_table_name character varying(255),
  gid integer,
  status integer,
  gc2_user character varying(255),
  roles hstore,
  workflow hstore,
  version_gid integer,
  operation character varying(255),
  created timestamp with time zone DEFAULT ('now'::text)::timestamp(0) with time zone
)
WITH (
OIDS=FALSE
);
ALTER TABLE settings.workflow
OWNER TO postgres;