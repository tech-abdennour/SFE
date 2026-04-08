#!/bin/bash

# --- CONFIGURATION DES CHEMINS ---
# Détection dynamique du dossier racine du projet (Marche sur Windows et Docker)
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
BACKUP_DIR="$PROJECT_DIR/backups"

# --- CONFIGURATION BASE DE DONNÉES ---
DB_CONTAINER="mariadb_db"
DB_USER="sfe_user"
DB_PASS="sfe_pass"
DB_NAME="sfe_db"
DATE=$(date +%F_%H-%M-%S)

# Créer le dossier de backup s'il n'existe pas
mkdir -p "$BACKUP_DIR"

echo "=========================================================="
echo "      DÉMARRAGE DU BACKUP COMPLET : $DATE"
echo "=========================================================="

# 1. Sauvegarde de la Base de Données
echo "[INFO] Extraction de la base de données via Docker..."
docker exec "$DB_CONTAINER" mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/db_$DATE.sql" 2>/dev/null

if [ $? -eq 0 ]; then
  echo "[OK] Base de données extraite avec succès."
  gzip -f "$BACKUP_DIR/db_$DATE.sql"
  echo "[OK] Fichier compressé : db_$DATE.sql.gz"
else
  echo "[ERROR] Échec de la sauvegarde de la base de données."
fi

# 2. Sauvegarde des Fichiers (Code source, scripts, etc.)
echo "[INFO] Création de l'archive des fichiers du projet..."
# CRITIQUE : L'ordre des arguments est inversé pour la compatibilité Git Bash/Windows
tar -czf "$BACKUP_DIR/full_app_$DATE.tar.gz" -C "$PROJECT_DIR" --exclude="backups" --exclude="logs" .

if [ $? -eq 0 ]; then
  echo "[OK] Archive complète créée : full_app_$DATE.tar.gz"
else
  echo "[ERROR] Échec de l'archivage des fichiers."
fi

echo "=========================================================="
echo " [DONE] Sauvegarde terminée dans : $BACKUP_DIR"
echo "=========================================================="
