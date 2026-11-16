#!/bin/sh
#
# Copia de seguridad lógica de MySQL para el stack de Docker Compose.
#
# Uso:
#   ./docker/scripts/backup-mysql.sh [directorio-destino]
#
# Variables de entorno:
#   COMPOSE_FILE            docker-compose.prod.yml por defecto
#   ENV_FILE                .env.production por defecto (vacío = sin --env-file)
#   BACKUP_RETENTION_DAYS   7 por defecto
#
# El dump usa MYSQL_DATABASE y MYSQL_ROOT_PASSWORD del propio contenedor mysql,
# así que las credenciales no aparecen en la línea de comandos del host.
# `--add-drop-database` hace que la restauración sea limpia (DROP + CREATE).
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
FILE="$DESTINATION/api_laravel_car_agency-$STAMP.sql.gz"
TMP="$FILE.tmp"

ENV_ARGS=""
if [ -n "$ENV_FILE" ] && [ -f "$ENV_FILE" ]; then
    ENV_ARGS="--env-file $ENV_FILE"
fi

# shellcheck disable=SC2086
if docker compose $ENV_ARGS -f "$COMPOSE_FILE" exec -T mysql sh -c '
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot \
        --single-transaction --quick --routines --triggers --events \
        --add-drop-database --default-character-set=utf8mb4 \
        --databases "$MYSQL_DATABASE"
' > "$TMP"; then
    gzip -9 -c "$TMP" > "$FILE"
    rm -f "$TMP"
else
    rm -f "$TMP"
    echo "[backup] ERROR: mysqldump falló; no se generó $FILE" >&2
    exit 1
fi

# Retención: borra copias más antiguas que BACKUP_RETENTION_DAYS.
find "$DESTINATION" -type f -name 'api_laravel_car_agency-*.sql.gz' \
    -mtime +"$RETENTION_DAYS" -delete

echo "[backup] Copia creada: $FILE"
