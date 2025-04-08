#!/bin/bash

rm -rf /var/run/apache2/*
exec /usr/sbin/apachectl -D FOREGROUND
