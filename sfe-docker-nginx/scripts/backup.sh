#!/bin/bash

# --- CONFIGURATION DES CHEMINS ---
# Détection dynamique du dossier racine du projet
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
BACKUP_DIR="$PROJECT_DIR/backups"

# --- CONFIGURATION BASE DE DONNÉES ---
DB_CONTAINER="mysql" 
DB_USER="root"
DB_PASS="rootpass"           # Le mot de passe que vous avez spécifié
DB_NAME="appdb"              # Le nom de la base identifié via 'SHOW DATABASES'
DATE=$(date +%F_%H-%M-%S)

# Créer le dossier de backup s'il n'existe pas
mkdir -p "$BACKUP_DIR"

echo "=========================================================="
echo "      DÉMARRAGE DU BACKUP COMPLET : $DATE"
echo "=========================================================="

# 1. Sauvegarde de la Base de Données
echo "[INFO] Extraction de la base de données via Docker..."

# Utilisation de -i pour Git Bash
# Note: Pas d'espace entre -p et le mot de passe
docker exec -i "$DB_CONTAINER" mysqldump -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/db_$DATE.sql"

if [ $? -eq 0 ] && [ -s "$BACKUP_DIR/db_$DATE.sql" ]; then
  echo "[OK] Base de données extraite avec succès."
  gzip -f "$BACKUP_DIR/db_$DATE.sql"
  echo "[OK] Fichier compressé : db_$DATE.sql.gz"
else
  echo "[ERROR] Échec de la sauvegarde de la base de données."
  echo "[DEBUG] Vérifiez que le conteneur est prêt et que les accès sont valides."
  rm -f "$BACKUP_DIR/db_$DATE.sql"
fi

# 2. Sauvegarde des Fichiers (Code source, etc.)
echo "[INFO] Création de l'archive des fichiers du projet..."
tar -czf "$BACKUP_DIR/full_app_$DATE.tar.gz" -C "$PROJECT_DIR" --exclude="backups" --exclude="logs" .

if [ $? -eq 0 ]; then
  echo "[OK] Archive complète créée : full_app_$DATE.tar.gz"
else
  echo "[ERROR] Échec de l'archivage des fichiers."
fi

echo "=========================================================="
echo " [DONE] Sauvegarde terminée dans : $BACKUP_DIR"
echo "=========================================================="