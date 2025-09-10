#!/usr/bin/env bash
set -euo pipefail

# Default watch paths
WATCH_PATHS=${WATCH_PATHS:-"/var/www/geocloud2/app/event /var/www/geocloud2/app/models /var/www/geocloud2/app/inc /var/www/geocloud2/app/event/functions /var/www/geocloud2/app/event/tasks /var/www/geocloud2/app/event/sockets"}

PHPSCRIPT=${PHPSCRIPT:-/var/www/geocloud2/app/event/main.php}

if command -v watchexec >/dev/null 2>&1; then
  echo "[dev-entrypoint] Using watchexec hot-reload"
  exec watchexec \
    --watch $WATCH_PATHS \
    --exts php,ini \
    --restart \
    --clear \
    --shell=none \
    --debounce 200ms \
    --signal SIGINT \
    -- /usr/local/bin/php -f "$PHPSCRIPT"
elif command -v inotifywait >/dev/null 2>&1; then
  echo "[dev-entrypoint] Using inotifywait hot-reload"
  while true; do
    /usr/local/bin/php -f "$PHPSCRIPT" &
    PHP_PID=$!
    inotifywait -qq -e modify,create,delete,move -r $WATCH_PATHS >/dev/null 2>&1 || true
    echo "[dev-entrypoint] Changes detected. Restarting..."
    kill -INT "$PHP_PID" 2>/dev/null || true
    wait "$PHP_PID" 2>/dev/null || true
    sleep 0.2
  done
else
  echo "[dev-entrypoint] No watcher available. Running without auto-reload."
  exec /usr/local/bin/php -f "$PHPSCRIPT"
fi
