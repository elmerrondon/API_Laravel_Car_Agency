#!/bin/sh
#
# Restaura una copia lógica de PostgreSQL creada por backup-postgres.sh.
#
# Uso:
#   ./docker/scripts/restore-postgres.sh backups/api_laravel_car_agency-XXXX.dump --confirm
#
# Detiene `app`, `worker` y `scheduler` durante la restauración para no dejar
# conexiones abiertas contra una base que se va a recrear. La base se elimina
# con `dropdb --force` y se vuelve a crear con `car_agency` como dueño; después
# `pg_restore` conecta como ese mismo rol (no como el superusuario), así que
# todas las tablas, secuencias e índices quedan en manos de la aplicación.
#
# Variables de entorno:
#   COMPOSE_FILE   docker-compose.prod.yml por defecto
#   ENV_FILE       .env.production por defecto (vacío = sin --env-file)
set -eu

if [ "$#" -ne 2 ] || [ "$2" != "--confirm" ]; then
    echo "Uso: $0 <archivo.dump> --confirm" >&2
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
stop_services() {
    # Se detiene servicio a servicio para que el script también funcione contra
    # el compose de desarrollo, donde `worker` no existe.
    for service in app worker scheduler; do
        docker compose $ENV_ARGS -f "$COMPOSE_FILE" stop "$service" >/dev/null 2>&1 || true
    done
}

# shellcheck disable=SC2086
start_services() {
    for service in app worker scheduler; do
        docker compose $ENV_ARGS -f "$COMPOSE_FILE" start "$service" >/dev/null 2>&1 || true
    done

    # `stop` + `start` vuelve a conectar el contenedor a la red y puede cambiar
    # su IP; Nginx resuelve `app` al cargar la configuración, así que un reload
    # evita un 502 permanente contra la IP antigua.
    docker compose $ENV_ARGS -f "$COMPOSE_FILE" exec -T nginx nginx -s reload \
        >/dev/null 2>&1 || true
}

stop_services

# La base se recrea como superusuario (el rol de app no tiene CREATEDB) pero se
# le entrega la propiedad a POSTGRES_APP_USER.
# shellcheck disable=SC2086
if ! docker compose $ENV_ARGS -f "$COMPOSE_FILE" exec -T postgres sh -c '
    set -e
    PGPASSWORD="${POSTGRES_PASSWORD:?}" dropdb --if-exists --force \
        --username "${POSTGRES_USER:?}" "${POSTGRES_DB:?}"
    PGPASSWORD="${POSTGRES_PASSWORD:?}" createdb \
        --username "${POSTGRES_USER:?}" --owner "${POSTGRES_APP_USER:?}" \
        "${POSTGRES_DB:?}"
'; then
    echo "[restore] ERROR: no se pudo recrear la base de datos" >&2
    start_services
    exit 1
fi

# El dump se envía por stdin; pg_restore corre como car_agency para que el
# resultado pertenezca al rol de la aplicación (--no-owner evita que se intente
# devolver la propiedad al rol original).
# shellcheck disable=SC2086
if docker compose $ENV_ARGS -f "$COMPOSE_FILE" exec -T postgres sh -c '
    PGPASSWORD="${POSTGRES_APP_PASSWORD:?}" exec pg_restore \
        --no-owner --no-privileges --exit-on-error --single-transaction \
        --username "${POSTGRES_APP_USER:?}" --dbname "${POSTGRES_DB:?}"
' < "$BACKUP_FILE"; then
    start_services
    echo "[restore] Restauración completada desde $BACKUP_FILE"
else
    echo "[restore] ERROR: la restauración falló; revisa el contenedor postgres" >&2
    start_services
    exit 1
fi
