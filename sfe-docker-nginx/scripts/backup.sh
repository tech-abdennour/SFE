#!/bin/bash
DATE=$(date +%F)
docker exec mariadb_db \
  mysqldump -u sfe_user -psfe_pass sfe_db \
  > backups/db_$DATE.sql