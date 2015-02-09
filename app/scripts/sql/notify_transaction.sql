CREATE OR REPLACE FUNCTION _gc2_notify_transaction()
  RETURNS TRIGGER AS $$
DECLARE
  t text;
BEGIN
  IF TG_OP = 'INSERT' OR TG_OP = 'UPDATE'
  THEN
    EXECUTE 'SELECT $1.'||TG_ARGV[0] USING NEW INTO t;
    PERFORM pg_notify('_gc2_notify_transaction',  TG_OP || ',' || TG_ARGV[1] || ',' || TG_ARGV[2] || ',' || TG_ARGV[0] || ',' ||t);
  ELSEIF TG_OP = 'DELETE'
    THEN
      EXECUTE 'SELECT $1.'||TG_ARGV[0] USING OLD INTO t;
      PERFORM pg_notify('_gc2_notify_transaction',  TG_OP || ',' || TG_ARGV[1] || ',' || TG_ARGV[2] || ',' || t);
  END IF;
  RETURN null;
END;
$$ LANGUAGE plpgsql;