#!/bin/bash

# Make sure we're not confused by old, incompletely-shutdown fpm

rm /var/run/php-fpm.sock

exec /usr/sbin/php-fpm7.3
