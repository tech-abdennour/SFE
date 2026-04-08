#!/bin/bash

# --- CONFIGURATION ---
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
mkdir -p "$PROJECT_DIR/logs"

# Format : jour_heure_minute (ex: 08-04-2026_19-45.txt)
DATE_NOM_FICHIER=$(date "+%d-%m-%Y_%H:%M")
LOGFILE="$PROJECT_DIR/logs/healthcheck_$DATE_NOM_FICHIER.txt"

DATE_LOG=$(date "+%Y-%m-%d %H:%M:%S")
SERVICES=("nginx_web" "php_fpm" "mariadb_db") [cite: 3]

# --- EXÉCUTION ---
echo "[$DATE_LOG] Vérification des services Docker" >> "$LOGFILE"

for SERVICE in "${SERVICES[@]}"; do
    # Vérification de l'état du conteneur [cite: 3]
    STATUS=$(docker inspect --format="{{.State.Running}}" $SERVICE 2>/dev/null) [cite: 3]

    if [ "$STATUS" != "true" ]; then
        echo "[$DATE_LOG] ❌ $SERVICE arrêté. Tentative de relance..." >> "$LOGFILE"
        docker start $SERVICE >> "$LOGFILE" 2>&1 [cite: 3]
    else
        echo "[$DATE_LOG] ✅ $SERVICE en ligne" >> "$LOGFILE"
    fi
done

echo "----------------------------------------" >> "$LOGFILE"
echo "[INFO] Terminé. Fichier créé : logs/healthcheck_$DATE_NOM_FICHIER.txt"
