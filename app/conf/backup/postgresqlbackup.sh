#!/bin/bash

echo `date`

YEAR=`date +%Y`						    # Year e.g. 2008
MONTH=`date +%m`				    	    # MONTH e.g. 08
DAY=`date +%d`						    # Date of the Month e.g. 27
DNOW=`date +%u`						    # Day number of the week 1 to 7 where 1 represents Monday

BACKUPALTS="year/${YEAR}
month/${MONTH}
day/${DAY}"

function mkdbbackup {
	HOST=$1
	PORT=$2
	BACKUPDIR=$3
	CREDS="--user=postgres"
	SSHHOST="ubuntu@${HOST}"
	BACKUPBASE="${BACKUPDIR}/${HOST}"
	BACKUPLATEST="${BACKUPBASE}/latest"
	if [ ! -d $BACKUPDIR ]; then
            mkdir $BACKUPDIR
    fi
	if [ ! -d $BACKUPLATEST ]; then
		mkdir -vp $BACKUPLATEST
	else
		rm -fv $BACKUPLATEST/*
	fi
	for DATABASE in `psql ${CREDS} --list |grep postgres |tr -d " " |cut -d "|" -f1`
    	do
		DUMPPATH="${BACKUPLATEST}/${DATABASE}.bak"
        	echo "Backing up: ${HOST}.${DATABASE} to ${DUMPPATH}"
        	pg_dump ${CREDS} --format=c  ${DATABASE} > ${DUMPPATH}
    	done

    	for BDIR in $BACKUPALTS
    	do
        	TARGETDIR="${BACKUPBASE}/${BDIR}"
        	if [ ! -d $TARGETDIR ]; then
            		mkdir -vp $TARGETDIR
        	else
            		rm -fv $TARGETDIR/*
        	fi
        	cp -v $BACKUPLATEST/* $TARGETDIR/. 
    	done
}

echo "_ts_ host1 start" `date`
mkdbbackup 127.0.0.1 5432 /var/www/geocloud2/public/backups
echo "_ts_ host1 end" `date`
