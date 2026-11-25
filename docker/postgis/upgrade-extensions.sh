#!/bin/bash
#
# Upgrades PostGIS and pgRouting in every database of the cluster to the
# versions this image ships.
#
# Run it once after a cluster has been moved to a newer image:
#
#   docker exec <postgis container> upgrade-extensions.sh
#
# A newer image brings newer libraries, but each database keeps the SQL
# definitions of the version it was created with until it is upgraded:
#
# - PostGIS keeps working, with postgis_full_version() reporting "procs need
#   upgrade". SELECT postgis_extensions_upgrade() brings postgis,
#   postgis_raster and postgis_topology up to date -- but not in a GC2
#   database as it stands; see UPGRADE_POSTGIS below.
# - pgRouting does not: its library carries the version in its name, so every
#   pgr_* function fails with 'could not access file "$libdir/libpgrouting-3.4"'
#   until ALTER EXTENSION pgrouting UPDATE has run.
#
# Both are per database, templates included -- a database created from a
# template that was not upgraded starts out on the old versions. The script can
# be run again at any time; a database that is up to date is left as it is.
#
# Tested from mapcentia/postgis:15 (PostGIS 3.3.2, pgRouting 3.4.2) to
# pg15-postgis3-bookworm (PostGIS 3.7.0, pgRouting 4.0.2).

set -u
export PGUSER=${PGUSER:-postgres}

# postgis_extensions_upgrade(), with the views that stand in its way set aside.
#
# The raster upgrade drops and recreates the views raster_columns and
# raster_overviews, and PostgreSQL refuses while another view selects from
# them. Every GC2 database has one that does (settings.geometry_columns_view),
# so the plain call fails there with "cannot drop view raster_columns because
# other objects depend on it" and leaves the database on the old version.
#
# The dependent views -- and the views that depend on those -- are dropped,
# the upgrade runs, and they are created again from their own definitions with
# owner, privileges, options and comment. One DO block is one transaction: if
# anything fails, the database is exactly as it was.
#
# Not handled, and refused before anything is touched: a dependent
# materialized view (it would have to be repopulated) and a dependent view
# with triggers or rules of its own.
UPGRADE_POSTGIS=$(cat <<'SQL'
DO $upgrade$
DECLARE
    v record;
    acl record;
BEGIN
    CREATE TEMP TABLE _gc2_dependent_views ON COMMIT DROP AS
    WITH RECURSIVE dep AS (
        SELECT c.oid, 1 AS depth
        FROM pg_class base
                 JOIN pg_depend member ON member.classid = 'pg_class'::regclass AND member.objid = base.oid AND member.deptype = 'e'
                 JOIN pg_extension e ON e.oid = member.refobjid AND e.extname = 'postgis_raster'
                 JOIN pg_depend d ON d.refclassid = 'pg_class'::regclass AND d.refobjid = base.oid AND d.classid = 'pg_rewrite'::regclass
                 JOIN pg_rewrite rw ON rw.oid = d.objid
                 JOIN pg_class c ON c.oid = rw.ev_class AND c.oid <> base.oid
        WHERE base.relname IN ('raster_columns', 'raster_overviews')
        UNION
        SELECT c.oid, dep.depth + 1
        FROM dep
                 JOIN pg_depend d ON d.refclassid = 'pg_class'::regclass AND d.refobjid = dep.oid AND d.classid = 'pg_rewrite'::regclass
                 JOIN pg_rewrite rw ON rw.oid = d.objid
                 JOIN pg_class c ON c.oid = rw.ev_class AND c.oid <> dep.oid
    )
    SELECT c.oid, n.nspname, c.relname, c.relkind, max(dep.depth) AS depth,
           pg_get_viewdef(c.oid) AS def, pg_get_userbyid(c.relowner) AS owner, c.relacl, c.reloptions,
           obj_description(c.oid, 'pg_class') AS comment,
           (SELECT count(*) FROM pg_trigger t WHERE t.tgrelid = c.oid AND NOT t.tgisinternal) AS triggers,
           (SELECT count(*) FROM pg_rewrite r WHERE r.ev_class = c.oid AND r.rulename <> '_RETURN') AS rules
    FROM dep
             JOIN pg_class c ON c.oid = dep.oid
             JOIN pg_namespace n ON n.oid = c.relnamespace
    -- the extension's own views are the upgrade's business
    WHERE NOT EXISTS (SELECT 1 FROM pg_depend m WHERE m.classid = 'pg_class'::regclass AND m.objid = c.oid AND m.deptype = 'e')
    GROUP BY c.oid, n.nspname, c.relname, c.relkind, c.relowner, c.relacl, c.reloptions;

    FOR v IN SELECT * FROM _gc2_dependent_views WHERE relkind <> 'v' OR triggers > 0 OR rules > 0 LOOP
        RAISE EXCEPTION '%.% depends on the PostGIS raster views and cannot be set aside automatically (kind %, % triggers, % rules). Drop it, run the upgrade and create it again.',
            v.nspname, v.relname, v.relkind, v.triggers, v.rules;
    END LOOP;

    FOR v IN SELECT * FROM _gc2_dependent_views ORDER BY depth DESC LOOP
        EXECUTE format('DROP VIEW %I.%I', v.nspname, v.relname);
    END LOOP;

    PERFORM postgis_extensions_upgrade();

    FOR v IN SELECT * FROM _gc2_dependent_views ORDER BY depth LOOP
        EXECUTE format('CREATE VIEW %I.%I %s AS %s', v.nspname, v.relname,
                       CASE WHEN v.reloptions IS NULL THEN '' ELSE 'WITH (' || array_to_string(v.reloptions, ', ') || ')' END,
                       v.def);
        EXECUTE format('ALTER VIEW %I.%I OWNER TO %I', v.nspname, v.relname, v.owner);
        IF v.comment IS NOT NULL THEN
            EXECUTE format('COMMENT ON VIEW %I.%I IS %L', v.nspname, v.relname, v.comment);
        END IF;
        FOR acl IN SELECT a.grantee, a.privilege_type, a.is_grantable FROM aclexplode(v.relacl) a LOOP
            EXECUTE format('GRANT %s ON %I.%I TO %s%s', acl.privilege_type, v.nspname, v.relname,
                           CASE WHEN acl.grantee = 0 THEN 'PUBLIC' ELSE quote_ident(pg_get_userbyid(acl.grantee)) END,
                           CASE WHEN acl.is_grantable THEN ' WITH GRANT OPTION' ELSE '' END);
        END LOOP;
        RAISE NOTICE 'gc2: recreated view %.%', v.nspname, v.relname;
    END LOOP;
END
$upgrade$;
SQL
)

failed=0
for db in $(psql -d postgres -Atc "SELECT datname FROM pg_database WHERE datallowconn ORDER BY 1"); do
  installed=$(psql -d "$db" -Atc "SELECT string_agg(extname, ',') FROM pg_extension WHERE extname IN ('postgis', 'pgrouting')")
  if [ -z "$installed" ]; then
    echo "$db: no postgis"
    continue
  fi
  case ",$installed," in
  *,postgis,*)
    if out=$(psql -d "$db" -q -v ON_ERROR_STOP=1 -c "$UPGRADE_POSTGIS" 2>&1); then
      echo "$out" | sed -n "s/^NOTICE:  gc2: /$db: /p"
    else
      echo "$db: postgis upgrade FAILED, database left unchanged:"
      echo "$out" | grep -E "^(ERROR|DETAIL|HINT):" | sed 's/^/    /'
      failed=1
    fi
    ;;
  esac
  case ",$installed," in
  *,pgrouting,*)
    if ! out=$(psql -d "$db" -q -v ON_ERROR_STOP=1 -c "ALTER EXTENSION pgrouting UPDATE" 2>&1); then
      echo "$db: pgrouting update FAILED: $out"
      failed=1
    fi
    ;;
  esac
  echo "$db: $(psql -d "$db" -Atc "SELECT string_agg(extname || ' ' || extversion, ', ' ORDER BY extname) FROM pg_extension WHERE extname ~ 'postgis|pgrouting'")"
done
exit $failed
