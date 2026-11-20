#!/bin/sh
#
# Copia de seguridad lógica de PostgreSQL para el stack de Docker Compose.
#
# Uso:
#   ./docker/scripts/backup-postgres.sh [directorio-destino]
#
# Variables de entorno:
#   COMPOSE_FILE            docker-compose.prod.yml por defecto
#   ENV_FILE                .env.production por defecto (vacío = sin --env-file)
#   BACKUP_RETENTION_DAYS   7 por defecto
#
# `pg_dump -Fc` (formato custom, ya comprimido) se ejecuta dentro del
# contenedor postgres y lee POSTGRES_DB, POSTGRES_USER y POSTGRES_PASSWORD del
# propio contenedor, así que las credenciales no aparecen en el host. El dump
# no crea la base: restore-postgres.sh la recrea antes de importarlo.
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
REPO_ROOT=$(dirname "$(dirname "$SCRIPT_DIR")")
cd "$REPO_ROOT"

COMPOSE_FILE=${COMPOSE_FILE:-docker-compose.prod.yml}
ENV_FILE=${ENV_FILE-.env.production}
RETENTION_DAYS=${BACKUP_RETENTION_DAYS:-7}
DESTINATION=${1:-backups}

mkdir -p "$DESTINATION"

STAMP=$(date -u +%Y%m%d-%H%M%S)
FILE="$DESTINATION/api_laravel_car_agency-$STAMP.dump"
TMP="$FILE.tmp"

ENV_ARGS=""
if [ -n "$ENV_FILE" ] && [ -f "$ENV_FILE" ]; then
    ENV_ARGS="--env-file $ENV_FILE"
fi

# shellcheck disable=SC2086
if docker compose $ENV_ARGS -f "$COMPOSE_FILE" exec -T postgres sh -c '
    PGPASSWORD="${POSTGRES_PASSWORD:?}" exec pg_dump \
        -U "${POSTGRES_USER:?}" -d "${POSTGRES_DB:?}" \
        --format=custom --no-password
' > "$TMP"; then
    mv "$TMP" "$FILE"
else
    rm -f "$TMP"
    echo "[backup] ERROR: pg_dump falló; no se generó $FILE" >&2
    exit 1
fi

# Retención: borra copias más antiguas que BACKUP_RETENTION_DAYS.
find "$DESTINATION" -type f -name 'api_laravel_car_agency-*.dump' \
    -mtime +"$RETENTION_DAYS" -delete

echo "[backup] Copia creada: $FILE"
