CREATE OR REPLACE FUNCTION _gc2_notify_transaction()
  RETURNS TRIGGER AS $$
DECLARE
  t text;
  snap jsonb := null;
BEGIN
  IF TG_OP = 'INSERT' OR TG_OP = 'UPDATE'
  THEN
    EXECUTE 'SELECT $1.'||TG_ARGV[0] USING NEW INTO t;
  ELSEIF TG_OP = 'DELETE'
    THEN
      EXECUTE 'SELECT $1.'||TG_ARGV[0] USING OLD INTO t;
      -- Optional before-snapshot: pass 'snapshot' as TG_ARGV[3] to enable
      IF TG_NARGS > 3 AND TG_ARGV[3] = 'snapshot' THEN
        snap := row_to_json(OLD)::jsonb;
      END IF;
  END IF;
  INSERT INTO settings.outbox (op, schema_name, table_name, pk_column, pk_value, payload)
  VALUES (left(TG_OP, 1), TG_ARGV[1], TG_ARGV[2], TG_ARGV[0], t, snap);
  PERFORM pg_notify('_gc2_outbox_wake', '');
  RETURN null;
END;
$$ LANGUAGE plpgsql;
