#!/bin/sh
#
# Restaura una copia lógica de MySQL creada por backup-mysql.sh.
#
# Uso:
#   ./docker/scripts/restore-mysql.sh backups/api_laravel_car_agency-XXXX.sql.gz --confirm
#
# Detiene `app` y `scheduler` durante la restauración para no dejar conexiones
# abiertas contra una base que se va a recrear. El dump incluye
# `DROP DATABASE` + `CREATE DATABASE` (--add-drop-database).
#
# Variables de entorno:
#   COMPOSE_FILE   docker-compose.prod.yml por defecto
#   ENV_FILE       .env.production por defecto (vacío = sin --env-file)
set -eu

if [ "$#" -ne 2 ] || [ "$2" != "--confirm" ]; then
    echo "Uso: $0 <archivo.sql.gz> --confirm" >&2
    echo "La restauración recrea la base de datos; por eso exige --confirm." >&2
    exit 1
fi

BACKUP_FILE=$1

if [ ! -f "$BACKUP_FILE" ]; then
    echo "[restore] ERROR: no existe $BACKUP_FILE" >&2
    exit 1
fi

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
REPO_ROOT=$(dirname "$(dirname "$SCRIPT_DIR")")
cd "$REPO_ROOT"

COMPOSE_FILE=${COMPOSE_FILE:-docker-compose.prod.yml}
ENV_FILE=${ENV_FILE-.env.production}

ENV_ARGS=""
if [ -n "$ENV_FILE" ] && [ -f "$ENV_FILE" ]; then
    ENV_ARGS="--env-file $ENV_FILE"
fi

# shellcheck disable=SC2086
docker compose $ENV_ARGS -f "$COMPOSE_FILE" stop app scheduler >/dev/null 2>&1 || true

# shellcheck disable=SC2086
if gunzip -c "$BACKUP_FILE" | docker compose $ENV_ARGS -f "$COMPOSE_FILE" exec -T mysql sh -c '
    MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot
'; then
    echo "[restore] Restauración completada desde $BACKUP_FILE"
else
    echo "[restore] ERROR: la restauración falló; revisa el contenedor mysql" >&2
    # shellcheck disable=SC2086
    docker compose $ENV_ARGS -f "$COMPOSE_FILE" start app scheduler >/dev/null 2>&1 || true
    exit 1
fi

# shellcheck disable=SC2086
docker compose $ENV_ARGS -f "$COMPOSE_FILE" start app scheduler >/dev/null 2>&1 || true

echo "[restore] Servicios app y scheduler reiniciados"
