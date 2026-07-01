"""
GC2 pooled runtime agent (Python). Long-lived: loads the handler once, then
serves invocations in a loop. Protocol is newline-delimited JSON: one request
object per line on stdin ({event, context}), one response per line on stdout.
Handler stdout/stderr is captured in-band into `logs`.

argv: <codeFile> <handlerName>
"""
import importlib.util
import io
import json
import os
import sys
import time
import traceback


def main():
    code_file, handler_name = sys.argv[1:3]
    # Let the handler import sibling modules from a multi-file bundle.
    sys.path.insert(0, os.path.dirname(os.path.abspath(code_file)))
    spec = importlib.util.spec_from_file_location("gc2_user_handler", code_file)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    fn = getattr(mod, handler_name, None)
    if not callable(fn):
        sys.stderr.write('Handler "%s" is not callable\n' % handler_name)
        sys.exit(2)

    protocol = sys.stdout  # keep a clean reference for responses

    # readline() (not `for line in sys.stdin`) to avoid the iterator's read-ahead
    # buffering deadlocking on a pipe.
    while True:
        line = sys.stdin.readline()
        if line == "":
            break  # EOF
        line = line.strip()
        if not line:
            continue
        try:
            req = json.loads(line)
        except Exception:
            continue

        start = time.time()
        buf = io.StringIO()
        real_out, real_err = sys.stdout, sys.stderr
        sys.stdout, sys.stderr = buf, buf
        try:
            result = fn(req.get("event"), req.get("context") or {})
            resp = {"status": "succeeded", "output": result}
        except Exception:
            resp = {"status": "failed", "error": traceback.format_exc()}
        finally:
            sys.stdout, sys.stderr = real_out, real_err

        resp["logs"] = buf.getvalue()
        resp["duration_ms"] = int((time.time() - start) * 1000)
        protocol.write(json.dumps(resp) + "\n")
        protocol.flush()


if __name__ == "__main__":
    try:
        main()
    except Exception:
        traceback.print_exc()
        sys.exit(1)
