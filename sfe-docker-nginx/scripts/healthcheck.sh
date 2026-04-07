#!/bin/bash

LOGFILE="logs/healthcheck.log"
DATE=$(date "+%Y-%m-%d %H:%M:%S")

SERVICES=("nginx_web" "php_fpm" "mariadb_db")

echo "[$DATE] Vérification des services Docker" >> $LOGFILE

for SERVICE in "${SERVICES[@]}"; do
    STATUS=$(docker inspect --format='{{.State.Running}}' $SERVICE 2>/dev/null)

    if [ "$STATUS" != "true" ]; then
        echo "[$DATE] ❌ $SERVICE est arrêté. Redémarrage..." >> $LOGFILE
        docker start $SERVICE >> $LOGFILE 2>&1
    else
        echo "[$DATE] ✅ $SERVICE fonctionne correctement." >> $LOGFILE
    fi
done

echo "----------------------------------------" >> $LOGFILE