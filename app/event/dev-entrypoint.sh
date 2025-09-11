#!/usr/bin/env bash
set -euo pipefail

PHP_SCRIPT=${PHPSCRIPT:-/var/www/geocloud2/app/event/main.php}

if command -v watchexec >/dev/null 2>&1; then
  echo "[dev-entrypoint] Using watchexec hot-reload"
  watchexec \
       --watch /var/www/geocloud2/app/models \
       --watch /var/www/geocloud2/app/inc \
       --watch /var/www/geocloud2/app/event \
       --watch /var/www/geocloud2/app/event/functions \
       --watch /var/www/geocloud2/app/event/tasks \
       --watch /var/www/geocloud2/app/event/sockets \
       --exts php,ini \
       --restart \
       --clear \
       --shell=none \
       --debounce 200ms \
       -- /usr/local/bin/php -f "$PHP_SCRIPT"
else
  echo "[dev-entrypoint] No watcher available. Running without auto-reload."
  exec /usr/local/bin/php -f "$PHP_SCRIPT"
fi
