#!/bin/sh

# Script: remove_dupes_from_archive.sh               

# Removes redundant older bz2 files of SQL database dumps which accumulate in /srv/BACKUP/ARCHIVE

# PURPOSE
# As a precaution against people accidentally messing up important data (or being hacked)
# we use a cron to create a nightly bz2 zip file of an SQL dump (<dbname>.sql.bz2) of every 
# database in  the Heurist filestore (generally /var/www/html/HEURIST/HEURIST_FILESTORE) 
# into /srv/BACKUP. Recent changes in these bz2 files are then saved in /srv/BACKUP/ARCHIVE 
# (see below).
# The current script removes redundant older copies of these archived and dated bz2 files.

# SQL DATA
# The SQL data is the easily edited and regularly modified part of any Heurist database, and
# having an instantly available copy over the last few weeks/months allows rapid restoration 
# without bothering the server administrators or waiting days or weeks to get a backup restored.

# FILE DATA
# Note that we don't do the same for the file directories for a couple of reasons.
# First, they are often many times larger than a zipped SQL dump and we can't afford the space.
# Second, the file info is relatively static and incremental in nature, rather than being easily 
# edited. Much of it is either shared by all databases, copies of media which the user probably 
# has elsewhere, and is unlikely to have been corrupted by user actions other than intentional 
# deletion. The cost of such compulsive disk-based backups is therefore out of proportion with 
# their practical value, and in the exceptional case that the file data need to be recovered 
# one can always refer to the system backups.
# The inclusion of file data is also complicated on multi-tier installations because it will 
# generally be on a different server from the SQL databases.

# TODO - rsynch mirroring 
# it would be good to add a script which would push incremental changes of both the 
# SQL dumps and the file data to another server using rsynch, configured in 
# heuristConfigIni.php as a form of low cost mirroring. 
# These are convenience backups for rapid reinstatement of services. Neither method will
# protect against hostile corruption/deletion if root access is gained to the server -
# for that you need the system backups.

# CHANGE ARCHIVING
# We compare, on a nightly cron, the dbname.sql.bz2 files with the file of the same name
# in /srv/BACKUP/ARCHIVE. If they are the same size we do nothing (the bz2 in /srv/BACKUP 
# will continue to be overwritten until it changes)
# If they differ, or if there is no file of that name in /srv/BACUP/ARCHIVE, we rename the 
# existing bz2 file in /srv/BACKUP/ARCHIVE (if any) with the current date 
# (dbname.sql.bz2.yyyy-mm-dd) and move the new bz2 file into /srv/BACKUP/ARCHIVE. 
# Note: This simple change detection will not necessarily pick up all changes, but it is fast
# and will pick up any significant editing of the data, which is what we care about safeguarding.

# REMOVE EMPTY AND IDENTICAL SIZED COPIES
# First, remove any hdb_ files smaller than 10K - these will be failures, the smallest are 50K+
# Then, remove multiple copies of the bz2 files with the same dbname and size, but different date 
# strings, keeping the newest (broadly speaking these are identical or near-identical database copies). 
# The reason for doing this is that it cleans up multiple copies resulting from an incomplete
# bz2 which is of a different size (ofter 0) and triggers a new dated copy due to difference 
# from the preceding one, then a second copy due to difference with the next one - this has 
# occurred in the past.

# PURGE OLDER BZ2 FILES
# Never delete a bz2 file without a date string appended
#   (these will always be the last safeguard, even if faulty)
# Leave all files up to 7 days old (these are the most useful for instant restore after errors).
# Remove all except the newest from 8-15, 16-30, 31-60 days old (recover within a couple of months).
# Remove all except the newest from 60-180, 181-365, 366-730, >731
#  (this allows for people wanting databases they forgot about for 2 - 3 years ... 
#   That covers people who go away on extended overseas fieldwork, an exchange posting or hiatus 
#   in a project. In any case, this should leave 1 copy of long-term inactive and deleted databases)


cd /srv/BACKUP/ARCHIVE
# Allow others to read backups
umask 022 

servername="<ENTER THE SERVER NAME>"

now=$(date)

# anything less than 10K will be a faulty dump which will trigger archiving and then re-archiving when a gooddump is made

cd /srv/BACKUP/ARCHIVETEST
find . -type f -name "hdb_*" -size -10k -delete

# show first entry in archive deletions log file

echo "start" >> /srv/BACKUP/ARCHIVETEST/__archive_file_deletions_log.txt

((CNT = 0))


# Returns all the file sizes in loop variable $i, b to get byte size
for i in `du -xab *.sql.bz2* | cut -f 1`; do

   # gets all files
   find .  ! -name `find . -type f -size $i | tail -1` -ls

   done

TODO:  NEED CLEANUP CODE HERE


echo "Multiple copy cleanup completed $(date +'%d-%m-%Y %H:%M:%S')" >> /srv/BACKUP/ARCHIVETEST/__archive_file_deletions_log.txt



