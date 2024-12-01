#!/bin/bash

# Script: build_archive_packages.sh               

# Creates complete/full archive packages for all or specified list of databases
# Package contains all database data in various formats and all files from db folder
# It uses buildArchivePackagesCMD.php
# Destintion folder is _BATCH_PROCESS_ARCHIVE_PACKAGE

# Default prefix for databases
DB_PREFIX="hdb_"

# Path to the configuration file
CONFIG_FILE="../heuristConfigIni.php"

# Path to the PHP script relative to the shell script
PHP_SCRIPT="./export/dbbackup/buildArchivePackagesCMD.php"

# Usage instructions
usage() {
    echo "Usage: $0 [ALL | dbname1,dbname2,...]"
    echo "Processes databases with the specified prefix. Default prefix is '$DB_PREFIX'."
    exit 1
}

# Check if arguments are provided
if [ "$#" -ne 1 ]; then
    usage
fi

# Get the list of databases or "ALL"
DB_LIST=$1

# Check if the configuration file exists
if [ ! -f "$CONFIG_FILE" ]; then
    echo "Error: Configuration file not found: $CONFIG_FILE"
    exit 1
fi

# Extract MySQL credentials from the configuration file
DB_HOST=$(grep -Po "(?<=\$dbHost = ')[^']*" "$CONFIG_FILE")
DB_USER=$(grep -Po "(?<=\$dbAdminUsername = ')[^']*" "$CONFIG_FILE")
DB_PASS=$(grep -Po "(?<=\$dbAdminPassword = ')[^']*" "$CONFIG_FILE")

# If dbHost is blank, use localhost
DB_HOST=${DB_HOST:-"localhost"}

# Verify the PHP script exists
if [ ! -f "$PHP_SCRIPT" ]; then
    echo "Error: PHP script not found: $PHP_SCRIPT"
    exit 1
fi

# Fetch all databases with the given prefix if ALL is specified
if [ "$DB_LIST" == "ALL" ]; then
    DB_LIST=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SHOW DATABASES;" | grep "^$DB_PREFIX" | sed "s/^$DB_PREFIX//")
    if [ -z "$DB_LIST" ]; then
        echo "No databases found with prefix '$DB_PREFIX'."
        exit 1
    fi
else
    # Split the comma-separated list into individual database names
    IFS=',' read -r -a DB_ARRAY <<< "$DB_LIST"
    DB_LIST="${DB_ARRAY[@]}"
fi

# Loop through the databases and call the PHP script for each
for DB in $DB_LIST; do
    FULL_DB_NAME="${DB_PREFIX}${DB}"
    echo "Processing database: $FULL_DB_NAME"
    
    # Call the PHP script with the appropriate arguments  -nosql -notsv -nofiles -nodocs
    php "$PHP_SCRIPT" -- -db="$DB"
    if [ $? -ne 0 ]; then
        echo "Error: Failed to process database $FULL_DB_NAME."
        exit 1
    fi
done

echo "Processing complete."
