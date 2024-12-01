#!/bin/bash

# Script: purge_ARCHIVE.sh               

# Removes redundant older bz2 files of SQL database dumps which accumulate in /srv/BACKUP/ARCHIVE

# PURPOSE
# As a precaution against people accidentally messing up important data (or being hacked)
# we use a cron to create a nightly bz2 zip file of an SQL dump (<dbname>.sql.bz2) of every 
# database in  the Heurist filestore (generally /var/www/html/HEURIST/HEURIST_FILESTORE) 
# into /srv/BACKUP.  (see backup_all_dbs.sh)
# Recent changes in these bz2 files are then saved in /srv/BACKUP/ARCHIVE or /data/BACKUP/ARCHIVE
# (see archive_bzip_dbs.sh)
#
# This script purges the backups archive directory leaving only the backup files for last 10 days and 
# latest backup file for each database for each month
#
# It can be run daily and will delete the previous backup file until it gets to the next month
# Expect roughly a 5x reduction in volume on an unpurged archive directory

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


# Directory containing the backup files
BACKUP_DIR="/data/BACKUP/ARCHIVE" # Update this to the actual path

# Change to the backup directory
cd "$BACKUP_DIR" || { echo "Error: Unable to access directory $BACKUP_DIR"; exit 1; }

# Find all unique database names (prefixes)
DB_NAMES=$(ls hdb_*.sql.bz2.* 2>/dev/null | sed -E 's/\.sql\.bz2\.[0-9]{4}-[0-9]{2}-[0-9]{2}$//' | sort -u)

# If no files are found, exit
if [ -z "$DB_NAMES" ]; then
    echo "No database backup files found in $BACKUP_DIR"
    exit 0
fi

echo "Processing backup files in $BACKUP_DIR..."

# Loop through each database name
for DB_NAME in $DB_NAMES; do
    echo "Processing database: $DB_NAME"

    # Get all files for this database
    DB_FILES=$(ls ${DB_NAME}.sql.bz2.* 2>/dev/null)

    # If no files found for this database, skip it
    if [ -z "$DB_FILES" ]; then
        echo "No files found for database: $DB_NAME"
        continue
    fi

    # Create a temporary list to store the files to keep
    FILES_TO_KEEP=()

    # Group files by month
    MONTHS=$(echo "$DB_FILES" | sed -E 's/^.*\.sql\.bz2\.([0-9]{4}-[0-9]{2})-[0-9]{2}$/\1/' | sort -u)

    for MONTH in $MONTHS; do
        # Find the latest file for the current month
        LATEST_FILE=$(echo "$DB_FILES" | grep "\.sql\.bz2\.${MONTH}-" | sort -t. -k4 -r | head -n 1)
        FILES_TO_KEEP+=("$LATEST_FILE")
    echo Keeping $LATEST_FILE
    done

    # Delete all other files except the ones in the keep list
    for FILE in $DB_FILES; do
        if [[ ! " ${FILES_TO_KEEP[@]} " =~ " $FILE " ]]; then
        
    # Check if the file is at least 10 days old 
            if [[ $(find "$FILE" -mtime +10 2>/dev/null) ]]; then 
                echo "Deleting: $FILE"
                rm -f "$FILE" 
            fi
        fi
    done
done

echo "Cleanup completed for all databases."

