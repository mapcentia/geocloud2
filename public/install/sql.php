<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

$sql = " 
CREATE SCHEMA settings;
SET search_path = settings, pg_catalog;
CREATE TABLE geometry_columns_join (
	_key_ varchar(255) not null,
    f_table_abstract character varying(256),
    f_table_title character varying(256),
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
    tweet text
);


CREATE TABLE viewer (
    viewer text
);

INSERT INTO viewer VALUES ('{\"pw\":\"81dc9bdb52d04dc20036dbd8313ed055\"}');
";