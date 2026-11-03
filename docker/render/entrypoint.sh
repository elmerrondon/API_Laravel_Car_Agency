#!/bin/sh
#
# Punto de entrada de la imagen monolítica de PaaS.
#
# Los PaaS no tienen volumen persistente ni comandos de `docker compose exec`,
# así que aquí se prepara lo mínimo para arrancar: directorios escribibles,
# validación de APP_KEY, migraciones opcionales y cachés de Laravel.
#
set -e

APP_DIR=/var/www/html

# Render/Railway inyectan PORT; en local usamos 8080 (mismo valor que el
# Caddyfile y el EXPOSE del Dockerfile).
export PORT="${PORT:-8080}"

cd "$APP_DIR"

# storage/ y bootstrap/cache son efímeros en un PaaS, pero deben existir y ser
# escribibles por www-data (el contenedor no corre como root).
mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ -z "${APP_KEY}" ]; then
    echo "[entrypoint] ERROR: APP_KEY no está definida." >&2
    echo "[entrypoint] Genera una con: php artisan key:generate --show" >&2
    echo "[entrypoint] y configúrala como variable de entorno en la plataforma." >&2
    exit 1
fi

# Migraciones opcionales. En Render lo recomendable es usar el Pre-Deploy
# Command (php artisan migrate --force); en Railway, preDeployCommand.
# RUN_MIGRATIONS=true las ejecuta al arrancar (útil para demos y pruebas).
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "[entrypoint] Ejecutando migraciones..."
    php artisan migrate --force
fi

# Las cachés se generan al arrancar (no en el build) porque dependen de las
# variables de entorno de la plataforma. Desactívalas con APP_OPTIMIZE=false.
if [ "${APP_OPTIMIZE}" != "false" ]; then
    echo "[entrypoint] Cacheando configuración, rutas, eventos y vistas..."
    php artisan optimize
fi

exec "$@"
