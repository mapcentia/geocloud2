#!/bin/bash

listcommand="ls /var/www/geocloud2/app/wms/mapcache -l $*"

newfilelist=$( $listcommand )
while true
do
	if [[ $oldfilelist != $newfilelist ]]
	then
		oldfilelist=$newfilelist
		/usr/bin/node /reload.js
	fi
	sleep 0.1
	newfilelist=$( $listcommand )
done

