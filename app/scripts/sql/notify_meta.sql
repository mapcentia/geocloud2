CREATE OR REPLACE FUNCTION _gc2_notify_meta()
  RETURNS TRIGGER AS $$
DECLARE
  t text;
BEGIN
  IF TG_OP = 'INSERT' OR TG_OP = 'UPDATE' OR TG_OP = 'DELETE'
  THEN
    EXECUTE 'SELECT $1._key_' USING NEW INTO t;
    PERFORM pg_notify('_gc2_notify_meta',  TG_OP || ',_key_,' ||t);
  END IF;
  RETURN null;
END;
$$ LANGUAGE plpgsql;