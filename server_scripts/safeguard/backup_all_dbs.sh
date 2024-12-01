#!/bin/sh

# Script: backup_all_dbs.sh
#
# Dumps all databases to SQL in /srv/BACKUP
#

# Connection to the MySQL database. If on a single tier system omit the H (Host) specification and P (Port)
# You may also be able to use local without password 

connection="mysql -H<SERVER> -P3306 -uheurist -p<PASSWORD>"

servername="<ENTER THE SERVER NAME>"

now=$(date)

cd /srv/BACKUP

# Allow others to read backups
umask 022

# clear BACKUP folder

rm -f /srv/BACKUP/backup_failure_log.txt
rm -f /srv/BACKUP/*.bz2
rm -f /srv/BACKUP/*.sql

# first entry in log file

echo "start" >> /srv/BACKUP/backup_failure_log.txt

((CNT = 0))
((TOT_SIZE = 0))
((SUCCESS = 0))
((FAILURE = 0))
((EMPTY = 0))

# Backup databases starting with "hdb_"

for db in `/usr/bin/mysql --login-path=$connection -N -e "select schema_name from information_schema.schemata where schema_name like 'hdb_%'"`; do
        echo "$db : backing up ..."
        /usr/bin/mysqldump --login-path=$connection --single-transaction --routines --triggers --events --add-drop-database --skip-dump-date --databases $db > $db.sql

        wait

        if [ "$?" -eq 0 ]
            then
                    filesize=$(stat -c%s "$db.sql")

                    if (( filesize > 100))
                         then echo "$db : success ... $filesize"  >> /srv/BACKUP/backup_failure_log.txt
                              echo "$db : success ... $filesize"


            # archive dump

            bzip2 $db.sql
            wait
            
            filesize=$(stat -c%s "$db.sql.bz2")
            ((TOT_SIZE = TOT_SIZE + filesize))
            ((SUCCESS++))

        else 
        
            echo "$db : FAILURE -ddump file size $filesize"  >> /srv/BACKUP/backup_failure_log.txt
                  echo "$db : FAILURE -dump file size $filesize"
                  ((EMPTY++))
                  read -p "$db : FAILURE 90 sec pause...." -t 90
                  echo " continuing ..."
                  echo ""
                  ((FAILURE++))
        fi


#debug stop after 10 databases
((CNT++))
#  if [[ $CNT -eq 10 ]]; then
#    break
#  fi

done
                
echo "Backup completed $(date +'%d-%m-%Y %H:%M:%S')" >> /srv/BACKUP/backup_failure_log.txt

#send email with log file

if [[ $EMPTY -gt 0 || $FAILURE -gt 0 ]]
then
   SUBJECT="Heurist backup FAILURE on $(hostname)"
else
   SUBJECT="Heurist backups OK on $(hostname)"
fi

echo -e "Backup completed $(date +'%d-%m-%Y %H:%M:%S'). Databases: $CNT. Archived successfully $SUCCESS with total size $TOT_SIZE. Empty dump: $EMPTY. Backup failure: $F$
echo "Backup completed $(date +'%d-%m-%Y %H:%M:%S'). Archived $SUCCESS with total size $TOT_SIZE. Empty dump: $EMPTY. Backup failure: $FAILURE"

