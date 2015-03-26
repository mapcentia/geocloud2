CREATE OR REPLACE FUNCTION pgr_createtopology3D(edge_table TEXT,
                                                f_zlev     TEXT,
                                                t_zlev     TEXT,
                                                tolerance  DOUBLE PRECISION,
                                                the_geom   TEXT DEFAULT 'the_geom',
                                                id         TEXT DEFAULT 'id',
                                                source     TEXT DEFAULT 'source',
                                                target     TEXT DEFAULT 'target',
                                                rows_where TEXT DEFAULT 'true')
  RETURNS VARCHAR AS
  $BODY$

  DECLARE
    points      RECORD;
    sridinfo    RECORD;
    source_id   BIGINT;
    target_id   BIGINT;
    totcount    BIGINT;
    rowcount    BIGINT;
    srid        INTEGER;
    sql         TEXT;
    sname       TEXT;
    tname       TEXT;
    tabname     TEXT;
    vname       TEXT;
    vertname    TEXT;
    gname       TEXT;
    idname      TEXT;
    sourcename  TEXT;
    targetname  TEXT;
    notincluded INTEGER;
    i           INTEGER;
    naming      RECORD;
    flag        BOOLEAN;
    query       TEXT;
    sourcetype  TEXT;
    targettype  TEXT;
    debuglevel  TEXT;

  BEGIN
    RAISE NOTICE 'PROCESSING:';
    RAISE NOTICE 'pgr_createTopology(''%'',%,''%'',''%'',''%'',''%'',''%'',''%'',''%'')', edge_table, f_zlev, t_zlev, tolerance, the_geom, id, source, target, rows_where;
    RAISE NOTICE 'Performing checks, pelase wait .....';
    EXECUTE 'show client_min_messages'
    INTO debuglevel;


    BEGIN
      RAISE DEBUG 'Checking % exists', edge_table;
      EXECUTE 'select * from pgr_getTableName(' || quote_literal(edge_table) || ')'
      INTO naming;
      sname=naming.sname;
      tname=naming.tname;
      IF sname IS NULL OR tname IS NULL
      THEN
        RAISE NOTICE '-------> % not found', edge_table;
        RETURN 'FAIL';
      ELSE
        RAISE DEBUG '  -----> OK';
      END IF;

      tabname=sname || '.' || tname;
      vname=tname || '_vertices_pgr';
      vertname= sname || '.' || vname;
      rows_where = ' AND (' || rows_where || ')';
    END;

    BEGIN
      RAISE DEBUG 'Checking id column "%" columns in  % ', id, tabname;
      EXECUTE 'select pgr_getColumnName(' || quote_literal(tabname) || ',' || quote_literal(the_geom) || ')'
      INTO gname;
      EXECUTE 'select pgr_getColumnName(' || quote_literal(tabname) || ',' || quote_literal(id) || ')'
      INTO idname;
      IF idname IS NULL
      THEN
        RAISE NOTICE 'ERROR: id column "%"  not found in %', id, tabname;
        RETURN 'FAIL';
      END IF;
      RAISE DEBUG 'Checking geometry column "%" column  in  % ', the_geom, tabname;
      IF gname IS NOT NULL
      THEN
        BEGIN
          RAISE DEBUG 'Checking the SRID of the geometry "%"', gname;
          query= 'SELECT ST_SRID(' || quote_ident(gname) || ') as srid '
                 || ' FROM ' || pgr_quote_ident(tabname)
                 || ' WHERE ' || quote_ident(gname)
                 || ' IS NOT NULL LIMIT 1';
          EXECUTE QUERY
          INTO sridinfo;

          IF sridinfo IS NULL OR sridinfo.srid IS NULL
          THEN
            RAISE NOTICE 'ERROR: Can not determine the srid of the geometry "%" in table %', the_geom, tabname;
            RETURN 'FAIL';
          END IF;
          srid := sridinfo.srid;
          RAISE DEBUG '  -----> SRID found %', srid;
          EXCEPTION WHEN OTHERS THEN
          RAISE NOTICE 'ERROR: Can not determine the srid of the geometry "%" in table %', the_geom, tabname;
          RETURN 'FAIL';
        END;
      ELSE
        RAISE NOTICE 'ERROR: Geometry column "%"  not found in %', the_geom, tabname;
        RETURN 'FAIL';
      END IF;
    END;

    BEGIN
      RAISE DEBUG 'Checking source column "%" and target column "%"  in  % ', source, target, tabname;
      EXECUTE 'select  pgr_getColumnName(' || quote_literal(tabname) || ',' || quote_literal(source) || ')'
      INTO sourcename;
      EXECUTE 'select  pgr_getColumnName(' || quote_literal(tabname) || ',' || quote_literal(target) || ')'
      INTO targetname;
      IF sourcename IS NOT NULL AND targetname IS NOT NULL
      THEN
--check that the are integer
        EXECUTE 'select data_type  from information_schema.columns where table_name = ' || quote_literal(tname) ||
                ' and table_schema=' || quote_literal(sname) || ' and column_name=' || quote_literal(sourcename)
        INTO sourcetype;
        EXECUTE 'select data_type  from information_schema.columns where table_name = ' || quote_literal(tname) ||
                ' and table_schema=' || quote_literal(sname) || ' and column_name=' || quote_literal(targetname)
        INTO targettype;
        IF sourcetype NOT IN ('integer', 'smallint', 'bigint')
        THEN
          RAISE NOTICE 'ERROR: source column "%" is not of integer type', sourcename;
          RETURN 'FAIL';
        END IF;
        IF targettype NOT IN ('integer', 'smallint', 'bigint')
        THEN
          RAISE NOTICE 'ERROR: target column "%" is not of integer type', targetname;
          RETURN 'FAIL';
        END IF;
        RAISE DEBUG '  ------>OK ';
      END IF;
      IF sourcename IS NULL
      THEN
        RAISE NOTICE 'ERROR: source column "%"  not found in %', source, tabname;
        RETURN 'FAIL';
      END IF;
      IF targetname IS NULL
      THEN
        RAISE NOTICE 'ERROR: target column "%"  not found in %', target, tabname;
        RETURN 'FAIL';
      END IF;
    END;


    IF sourcename = targetname
    THEN
      RAISE NOTICE 'ERROR: source and target columns have the same name "%" in %', target, tabname;
      RETURN 'FAIL';
    END IF;
    IF sourcename = idname
    THEN
      RAISE NOTICE 'ERROR: source and id columns have the same name "%" in %', target, tabname;
      RETURN 'FAIL';
    END IF;
    IF targetname = idname
    THEN
      RAISE NOTICE 'ERROR: target and id columns have the same name "%" in %', target, tabname;
      RETURN 'FAIL';
    END IF;


    BEGIN
      RAISE DEBUG 'Checking "%" column in % is indexed', idname, tabname;
      IF (pgr_isColumnIndexed(tabname, idname))
      THEN
        RAISE DEBUG '  ------>OK';
      ELSE
        RAISE DEBUG ' ------> Adding  index "%_%_idx".', tabname, idname;
        SET client_min_messages TO WARNING;
        EXECUTE 'create  index ' || pgr_quote_ident(tname || '_' || idname || '_idx') || '
                         on ' || pgr_quote_ident(tabname) || ' using btree(' || quote_ident(idname) || ')';
        EXECUTE 'set client_min_messages  to ' || debuglevel;
      END IF;
    END;

    BEGIN
      RAISE DEBUG 'Checking "%" column in % is indexed', sourcename, tabname;
      IF (pgr_isColumnIndexed(tabname, sourcename))
      THEN
        RAISE DEBUG '  ------>OK';
      ELSE
        RAISE DEBUG ' ------> Adding  index "%_%_idx".', tabname, sourcename;
        SET client_min_messages TO WARNING;
        EXECUTE 'create  index ' || pgr_quote_ident(tname || '_' || sourcename || '_idx') || '
                         on ' || pgr_quote_ident(tabname) || ' using btree(' || quote_ident(sourcename) || ')';
        EXECUTE 'set client_min_messages  to ' || debuglevel;
      END IF;
    END;

    BEGIN
      RAISE DEBUG 'Checking "%" column in % is indexed', targetname, tabname;
      IF (pgr_isColumnIndexed(tabname, targetname))
      THEN
        RAISE DEBUG '  ------>OK';
      ELSE
        RAISE DEBUG ' ------> Adding  index "%_%_idx".', tabname, targetname;
        SET client_min_messages TO WARNING;
        EXECUTE 'create  index ' || pgr_quote_ident(tname || '_' || targetname || '_idx') || '
                         on ' || pgr_quote_ident(tabname) || ' using btree(' || quote_ident(targetname) || ')';
        EXECUTE 'set client_min_messages  to ' || debuglevel;
      END IF;
    END;

    BEGIN
      RAISE DEBUG 'Checking "%" column in % is indexed', gname, tabname;
      IF (pgr_iscolumnindexed(tabname, gname))
      THEN
        RAISE DEBUG '  ------>OK';
      ELSE
        RAISE DEBUG ' ------> Adding unique index "%_%_gidx".', tabname, gname;
        SET client_min_messages TO WARNING;
        EXECUTE 'CREATE INDEX '
                || quote_ident(tname || '_' || gname || '_gidx')
                || ' ON ' || pgr_quote_ident(tabname)
                || ' USING gist (' || quote_ident(gname) || ')';
        EXECUTE 'set client_min_messages  to ' || debuglevel;
      END IF;
    END;
    gname=quote_ident(gname);
    idname=quote_ident(idname);
    sourcename=quote_ident(sourcename);
    targetname=quote_ident(targetname);


    BEGIN
      RAISE DEBUG 'initializing %', vertname;
      EXECUTE 'select * from pgr_getTableName(' || quote_literal(vertname) || ')'
      INTO naming;
      IF sname = naming.sname AND vname = naming.tname
      THEN
        EXECUTE 'TRUNCATE TABLE ' || pgr_quote_ident(vertname) || ' RESTART IDENTITY';
        EXECUTE 'SELECT DROPGEOMETRYCOLUMN(' || quote_literal(sname) || ',' || quote_literal(vname) || ',' ||
                quote_literal('the_geom') || ')';
      ELSE
        SET client_min_messages TO WARNING;
        EXECUTE 'CREATE TABLE ' || pgr_quote_ident(vertname) ||
                ' (id bigserial PRIMARY KEY,cnt integer,chk integer,ein integer,eout integer)';
      END IF;
      EXECUTE 'select addGeometryColumn(' || quote_literal(sname) || ',' || quote_literal(vname) || ',' ||
              quote_literal('the_geom') || ',' || srid || ', ' || quote_literal('POINT') || ', 3)';
      EXECUTE 'CREATE INDEX ' || quote_ident(vname || '_the_geom_idx') || ' ON ' || pgr_quote_ident(vertname) ||
              '  USING GIST (the_geom)';
      EXECUTE 'set client_min_messages  to ' || debuglevel;
      RAISE DEBUG '  ------>OK';
    END;


    BEGIN
      sql = 'select count(*) from ( select * from ' || pgr_quote_ident(tabname) || ' WHERE true' || rows_where ||
            ' limit 1 ) foo';
      EXECUTE sql
      INTO i;
      sql = 'select count(*) from ' || pgr_quote_ident(tabname) || ' WHERE (' || gname || ' IS NOT NULL AND ' ||
            idname || ' IS NOT NULL)=false ' || rows_where;
      EXECUTE SQL
      INTO notincluded;
      EXCEPTION WHEN OTHERS THEN BEGIN
      RAISE NOTICE 'Got %', SQLERRM;
      RAISE NOTICE 'ERROR: Condition is not correct, please execute the following query to test your condition';
      RAISE NOTICE '%', sql;
      RETURN 'FAIL';
    END;
    END;


    BEGIN
      RAISE NOTICE 'Creating Topology, Please wait...';
      EXECUTE 'UPDATE ' || pgr_quote_ident(tabname) ||
              ' SET ' || sourcename || ' = NULL,' || targetname || ' = NULL';
      rowcount := 0;
      FOR points IN EXECUTE 'SELECT ' || idname || '::bigint AS id,
                            coalesce(' || f_zlev || '::float,0.0) AS f_zlev,
                            coalesce(' || t_zlev || '::float,0.0) AS t_zlev,'
                            || ' PGR_StartPoint(' || gname || ') AS source,'
                            || ' PGR_EndPoint(' || gname || ') AS target'
                            || ' FROM ' || pgr_quote_ident(tabname)
                            || ' WHERE ' || gname || ' IS NOT NULL AND '
                            || idname || ' IS NOT NULL ' || rows_where
                            || ' ORDER BY ' || idname
      LOOP

        rowcount := rowcount + 1;
        IF rowcount % 1000 = 0
        THEN
          RAISE NOTICE '% edges processed', rowcount;
        END IF;


        source_id := pgr_pointToIdZ(points.source, tolerance, vertname, srid, points.f_zlev);
        target_id := pgr_pointToIdZ(points.target, tolerance, vertname, srid, points.t_zlev);
        BEGIN
          sql := 'UPDATE ' || pgr_quote_ident(tabname) ||
                 ' SET ' || sourcename || ' = ' || source_id :: TEXT || ',' || targetname || ' = ' || target_id :: TEXT
                 ||
                 ' WHERE ' || idname || ' =  ' || points.id :: TEXT;

          IF sql IS NULL
          THEN
            RAISE NOTICE 'WARNING: UPDATE % SET source = %, target = % WHERE % = % ', tabname, source_id :: TEXT, target_id :: TEXT, idname, points.id :: TEXT;
          ELSE
            EXECUTE sql;
          END IF;
          EXCEPTION WHEN OTHERS THEN
          RAISE NOTICE '%', SQLERRM;
          RAISE NOTICE '%', sql;
          RETURN 'FAIL';
        END;
      END LOOP;
      RAISE NOTICE '-------------> TOPOLOGY CREATED FOR  % edges', rowcount;
      RAISE NOTICE 'Rows with NULL geometry or NULL id: %', notincluded;
      RAISE NOTICE 'Vertices table for table % is: %', pgr_quote_ident(tabname), pgr_quote_ident(vertname);
      RAISE NOTICE '----------------------------------------------';
    END;
    RETURN 'OK';

  END;


  $BODY$
LANGUAGE plpgsql VOLATILE STRICT;
COMMENT ON FUNCTION pgr_createTopology(TEXT, double precision, TEXT, TEXT, TEXT, TEXT, TEXT)
IS 'args: edge_table,tolerance, the_geom:=''the_geom'',source:=''source'', target:=''target'',rows_where:=''true'' - fills columns source and target in the geometry table and creates a vertices table for selected rows';


CREATE OR REPLACE FUNCTION pgr_pointToIdZ(point     geometry,
                                          tolerance DOUBLE PRECISION,
                                          vertname  TEXT,
                                          srid      INTEGER,
                                          zlev      FLOAT
)
  RETURNS BIGINT AS
  $BODY$
  DECLARE
    rec RECORD;
    pid BIGINT;
    pnt geometry;
  BEGIN
    pnt := st_translate(
        st_force_3d(point), 0.0, 0.0, coalesce(zlev :: FLOAT, 0.0));
    EXECUTE
    'SELECT
      id,
      the_geom
    FROM
      ' || vertname || '
    WHERE
      ST_expand(ST_GeomFromText(st_astext(' || quote_literal(pnt :: TEXT) || '),' || srid || '), ' || text(tolerance) || ') && the_geom AND
      ST_3DLength(ST_makeline(the_geom, ST_GeomFromText(st_astext(' || quote_literal(pnt :: TEXT) || '),' || srid ||
    '))) < ' || text(tolerance) || ' ORDER BY ST_3DLength(ST_makeline(the_geom, ST_GeomFromText(st_astext(' ||
    quote_literal(pnt :: TEXT) || '),' || srid ||
    '))) LIMIT 1'
    INTO rec;
    IF rec.id IS NOT NULL
    THEN
      pid := rec.id;
    ELSE
      EXECUTE 'INSERT INTO ' || pgr_quote_ident(vertname) || ' (the_geom) VALUES (' || quote_literal(pnt :: TEXT) ||
              ')';
      pid := lastval();
    END IF;
    RETURN pid;
  END;
  $BODY$
LANGUAGE plpgsql VOLATILE STRICT;
COMMENT ON FUNCTION pgr_pointToId(geometry, double precision, TEXT,
                                  INTEGER) IS 'args: point geometry,tolerance,verticesTable,srid - inserts the point into the vertices table using tolerance to determine if its an existing point and returns the id assigned to it';




