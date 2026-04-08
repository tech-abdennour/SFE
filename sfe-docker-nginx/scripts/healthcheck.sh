#!/bin/bash

LOGFILE="/var/www/html/logs/healthcheck.log"
DATE=$(date "+%Y-%m-%d %H:%M:%S")

SERVICES=("nginx_web" "php_fpm" "mariadb_db")

echo "[$DATE] Vérification des services Docker" >> $LOGFILE

for SERVICE in "${SERVICES[@]}"; do
    STATUS=$(docker inspect --format="{{.State.Running}}" $SERVICE 2>/dev/null)

    if [ "$STATUS" != "true" ]; then
        echo "[$DATE] ❌ $SERVICE arrêté. Tentative de relance..." >> $LOGFILE
        docker start $SERVICE >> $LOGFILE 2>&1
    else
        USAGE=$(docker stats $SERVICE --no-stream --format "{{.CPUPerc}}" 2>/dev/null)
        echo "[$DATE] ✅ $SERVICE en ligne (Charge CPU: $USAGE)" >> $LOGFILE
    fi
done

echo "----------------------------------------" >> $LOGFILE
