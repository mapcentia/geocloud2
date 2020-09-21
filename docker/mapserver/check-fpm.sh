#!/bin/bash
while true; do
    	sleep 30;
	if curl --max-time 25 --output /dev/null --silent --head --fail "127.0.0.1/fpm-ping"; then
		printf '%s\n' "$url exist"
	else
  		/usr/bin/supervisorctl -c /etc/supervisor/conf.d/supervisord.conf restart php-fpm
	fi
done
