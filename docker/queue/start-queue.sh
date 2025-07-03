#!/bin/bash

set -e

MAX_TRIES=20
SLEEP_INTERVAL=3
TRIES=0

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

log "⏳ Ожидание готовности базы данных..."

until php artisan migrate:status > /dev/null 2>&1; do
    ((TRIES++))
    if [ "$TRIES" -ge "$MAX_TRIES" ]; then
        log "❌ Не удалось подключиться к базе данных после $((MAX_TRIES * SLEEP_INTERVAL)) секунд."
        exit 1
    fi
    log "🔄 Попытка $TRIES/$MAX_TRIES: база данных еще не готова. Ждем $SLEEP_INTERVAL сек..."
    sleep "$SLEEP_INTERVAL"
done

log "✅ База данных доступна."

# Запуск очереди
log "🎯 Запуск воркера очереди..."
php artisan queue:work --queue=default --tries=3 --timeout=60 --sleep=5

EXIT_CODE=$?
if [ $EXIT_CODE -ne 0 ]; then
    log "❌ Воркеры завершились с ошибкой. Код: $EXIT_CODE"
    exit $EXIT_CODE
fi
