"""
GC2 function runtime agent (Python).

Loads the user handler, runs it with the event, writes the JSON result to a
file. Logs go to stdout/stderr; a non-zero exit signals failure.

argv: <codeFile> <handlerName> <eventFile> <resultFile>
env:  GC2_CONTEXT = JSON execution context (caller identity, token, base url)
"""
import importlib.util
import json
import os
import sys
import traceback


def main():
    code_file, handler_name, event_file, result_file = sys.argv[1:5]
    # Let the handler import sibling modules from a multi-file bundle.
    sys.path.insert(0, os.path.dirname(os.path.abspath(code_file)))

    try:
        with open(event_file) as f:
            event = json.load(f)
    except Exception:
        event = {}

    try:
        context = json.loads(os.environ.get("GC2_CONTEXT", "{}"))
    except Exception:
        context = {}

    spec = importlib.util.spec_from_file_location("gc2_user_handler", code_file)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)

    fn = getattr(mod, handler_name, None)
    if not callable(fn):
        sys.stderr.write('Handler "%s" is not callable\n' % handler_name)
        sys.exit(2)

    result = fn(event, context)
    with open(result_file, "w") as f:
        json.dump(result, f)


if __name__ == "__main__":
    try:
        main()
    except SystemExit:
        raise
    except Exception:
        traceback.print_exc()
        sys.exit(1)
