#!/bin/bash

echo `date`

BACKUPDIR="/Users/mh/Downloads/postgresqlbackup"

#if [ ! -d /mnt/usbdisk1/lost+found ]; then
#        echo "BACKUP DISK NOT SETUP!!!";
#        exit;
#fi

if [ ! -d $BACKUPDIR ]; then
        mkdir $BACKUPDIR
fi


YEAR=`date +%Y`						    # Year e.g. 2008
MONTH=`date +%m`				    	# MONTH e.g. 08
DAY=`date +%d`						    # Date of the Month e.g. 27
DNOW=`date +%u`						    # Day number of the week 1 to 7 where 1 represents Monday

BACKUPALTS="year/${YEAR}
month/${MONTH}
day/${DAY}"

function mkdbbackup {
	HOST=$1
	PORT=$2
	DATABASES=$3[@]
	PEM=$4
	CREDS="--user=postgres"
	#SSHHOST="root@${HOST}"
	SSHHOST="-i ${PEM} ubuntu@${HOST}"
	BACKUPBASE="${BACKUPDIR}/${HOST}"
	BACKUPLATEST="${BACKUPBASE}/latest"
	if [ ! -d $BACKUPLATEST ]; then
		mkdir -vp $BACKUPLATEST
	else
		rm -fv $BACKUPLATEST/*
	fi
	CHECK=`ssh ${SSHHOST} psql ${CREDS} --list`
	if [ -n "$CHECK" ]; then
	    dbs=("${!DATABASES}")
		for DATABASE in "${dbs[@]}"
			do
			DUMPPATH="${BACKUPLATEST}/${DATABASE}.bak"
				echo "Backing up: ${HOST}.${DATABASE} to ${DUMPPATH}"
				ssh ${SSHHOST} pg_dump ${CREDS} --format=c  ${DATABASE} > ${DUMPPATH}
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
	else
		echo "Could not connect to server!"
	fi 
	
}

echo "_ts_ host1 start" `date`
databases=(kappel glostrup nyborg)
mkdbbackup cowi.mapcentia.com 5432 databases /Users/mh/Documents/mapcentia/pem/cowi.pem
echo "_ts_ host1 end" `date`

echo "_ts_ host2 start" `date`
databases=(vmus jammerbugt esbjerg)
mkdbbackup 54.73.44.135 5432 databases /Users/mh/Documents/mapcentia/pem/eu1_mapcentia.pem
echo "_ts_ host2 end" `date`

echo "_ts_ host3 start" `date`
databases=(pipeh)
mkdbbackup 54.217.236.19 5432 databases /Users/mh/Documents/mapcentia/pem/dragoer.pem
echo "_ts_ host3 end" `date`

echo "_ts_ host4 start" `date`
databases=(mapcentia)
mkdbbackup 107.22.234.29 5432 databases /Users/mh/Documents/mapcentia/pem/us1_mapcentia.pem
echo "_ts_ host4 end" `date`

echo "_ts_ host5 start" `date`
databases=(ballerup)
mkdbbackup 54.247.97.19 5432 databases /Users/mh/Documents/mapcentia/pem/ballerup.pem
echo "_ts_ host5 end" `date`
