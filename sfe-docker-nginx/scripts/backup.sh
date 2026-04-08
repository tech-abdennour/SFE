#!/bin/bash

DATE=$(date +%F)
PROJECT_DIR="/var/www/html"
BACKUP_DIR="$PROJECT_DIR/backups"
DB_CONTAINER="mariadb_db"
DB_USER="sfe_user"
DB_PASS="sfe_pass"
DB_NAME="sfe_db"

mkdir -p "$BACKUP_DIR"

echo "[INFO] Sauvegarde de la base de données..."
docker exec "$DB_CONTAINER" \
  mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  > "$BACKUP_DIR/db_$DATE.sql" 2>/dev/null

if [ $? -eq 0 ]; then
  echo "[OK] Base de données sauvegardée : db_$DATE.sql"
  gzip -f "$BACKUP_DIR/db_$DATE.sql" 2>/dev/null
  echo "[OK] Compression effectuée : db_$DATE.sql.gz"
else
  echo "[ERROR] Échec de la sauvegarde de la base de données"
fi

echo "[INFO] Sauvegarde des fichiers du site..."
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" -C "$PROJECT_DIR" . --exclude="backups" --exclude="logs" 2>/dev/null

if [ $? -eq 0 ]; then
  echo "[OK] Fichiers sauvegardés : files_$DATE.tar.gz"
else
  echo "[ERROR] Échec de la sauvegarde des fichiers"
fi

echo "[DONE] Backup complet effectué dans $BACKUP_DIR"
