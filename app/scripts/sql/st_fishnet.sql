CREATE OR REPLACE FUNCTION public.st_fishnet(geom_table TEXT, geom_col TEXT, cellsize FLOAT8, refsys INTEGER)
  RETURNS SETOF geometry AS
$BODY$
DECLARE
  sql TEXT;

BEGIN

  sql := 'WITH
extent as (
SELECT ST_Extent(' || geom_col || ') as bbox
FROM ' || geom_table || '),

bnds as (
SELECT ST_XMin(bbox) as xmin, ST_YMin(bbox) as
ymin, ST_XMax(bbox) as xmax, ST_YMax(bbox) as ymax
FROM extent),

raster as (
SELECT ST_AddBand(ST_MakeEmptyRaster(
ceil((xmax-xmin)/' || cellsize || ')::integer,
ceil((ymax-ymin)/' || cellsize || ')::integer,

xmin, ymin, ' || cellsize || ' , ' || cellsize || ', 0 , 0, ' || refsys || '), ''8BUI''::text,200) AS rast
FROM bnds)


SELECT (ST_PixelAsPolygons(rast,1)).geom
FROM raster;';

  RETURN QUERY EXECUTE sql;

END
$BODY$
LANGUAGE plpgsql STABLE
COST 100