CREATE OR REPLACE FUNCTION _gc2_notify_transaction()
  RETURNS TRIGGER AS $$
DECLARE
BEGIN
  IF TG_OP = 'INSERT' OR TG_OP = 'UPDATE'
  THEN
    PERFORM pg_notify('_gc2_notify_transaction',  TG_OP || ',' || TG_TABLE_SCHEMA || ',' || TG_TABLE_NAME || ',gid,' || NEW.gid);
  ELSEIF TG_OP = 'DELETE'
    THEN
      PERFORM pg_notify('_gc2_notify_transaction',  TG_OP || ',' || TG_TABLE_SCHEMA || ',' || TG_TABLE_NAME || ',' || OLD.gid);
  END IF;
  RETURN null;
END;
$$ LANGUAGE plpgsql;