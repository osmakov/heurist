#!/bin/sh

# Script: archive_bzip_dbs.sh
#
# Archive databases when /srv/BACKUP copy differs from copy in ARCHIVE directory
# 1. Rename archive with date suffix 
# 2. Copy db archive from BACKUP to ARCHIVE

# Allow others to read backups
umask 022

servername="<ENTER THE SERVER NAME>"

now=$(date)

# anything less than 10K will be a faulty dump which will trigger archiving and then re-archiving when a gooddump is made

cd /srv/BACKUP/ARCHIVE
find . -type f -name "hdb_*" -size -10k -delete

cd /srv/BACKUP
find . -type f -name "hdb_*" -size -10k -delete

for f in `ls *.bz2`; do

        cmd=`diff -a -q $f ARCHIVE/$f   > /dev/null 2>&1`
        rc="$?"
        # echo "diff $f ARCHIVE/$f"
        #echo $rc
        # exit

        if [ $rc -eq  0 ]
        then
                echo "Do nothing with $f"
        fi

        if [ $rc -eq  2 ]
        then
                echo "File is new $f"
                cp $f ARCHIVE/$f
        fi

        if [ $rc -eq  1 ]
        then
                echo "File is changed to ARCHIVING $f"
                mv ARCHIVE/$f "ARCHIVE/$f.$(date +'%Y-%m-%d')" 2> /dev/null
                cp $f ARCHIVE/$f
        fi

done

