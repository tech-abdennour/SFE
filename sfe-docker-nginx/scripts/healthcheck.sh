#!/bin/bash

# ==================================================
# Script : healthcheck.sh
# Rôle   : Vérifier l’état des conteneurs Docker
#          et les relancer automatiquement si arrêtés
# ==================================================

# --- CONFIGURATION ---
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
LOG_DIR="$PROJECT_DIR/logs"

mkdir -p "$LOG_DIR"

# Nom du fichier log (sans ':' pour compatibilité Windows)
DATE_NOM_FICHIER=$(date "+%d-%m-%Y_%H-%M")
LOGFILE="$LOG_DIR/healthcheck_$DATE_NOM_FICHIER.txt"

DATE_LOG=$(date "+%Y-%m-%d %H:%M:%S")

# Liste des conteneurs à surveiller
SERVICES=("nginx_web" "php_fpm" "mariadb_db")

# --- EXÉCUTION ---
echo "[$DATE_LOG] 🔍 Vérification des services Docker" >> "$LOGFILE"

for SERVICE in "${SERVICES[@]}"; do

    # Vérifier si le conteneur existe
    if ! docker inspect "$SERVICE" >/dev/null 2>&1; then
        echo "[$DATE_LOG] ⚠️  $SERVICE n'existe pas" >> "$LOGFILE"
        continue
    fi

    # Vérifier l'état du conteneur
    STATUS=$(docker inspect --format="{{.State.Running}}" "$SERVICE" 2>/dev/null)

    if [ "$STATUS" != "true" ]; then
        echo "[$DATE_LOG] ❌ $SERVICE arrêté. Tentative de relance..." >> "$LOGFILE"
        docker start "$SERVICE" >> "$LOGFILE" 2>&1
    else
        echo "[$DATE_LOG] ✅ $SERVICE en ligne" >> "$LOGFILE"
    fi
done

echo "----------------------------------------" >> "$LOGFILE"
echo "[INFO] Terminé. Fichier créé : logs/healthcheck_$DATE_NOM_FICHIER.txt"
