#!/bin/bash
# =============================================================================
# Inicialización del contenedor PostgreSQL.
#
# La imagen oficial ejecuta los scripts de /docker-entrypoint-initdb.d una sola
# vez, cuando el volumen de datos está vacío. Aquí se crea el rol de aplicación
# y se le entrega la propiedad de la base y del schema:
#
#   - El superusuario (POSTGRES_USER) solo se usa para la inicialización y los
#     backups; la API nunca conecta con él.
#   - `car_agency` es un rol LOGIN sin privilegios de administración, dueño de
#     la base y del schema `public`, que es lo que necesitan las migraciones.
#   - Con POSTGRES_CREATE_TESTING_DB=true (solo desarrollo) se crea además la
#     base `testing` que usa `phpunit-postgres.xml`.
#
# Variables: POSTGRES_USER, POSTGRES_DB (de la imagen), POSTGRES_APP_USER,
# POSTGRES_APP_PASSWORD y POSTGRES_CREATE_TESTING_DB (definidas en Compose).
#
# Para aplicarlo sobre un volumen ya creado hay que recrearlo o ejecutar el SQL
# a mano: este script no se vuelve a ejecutar.
# =============================================================================
set -euo pipefail

APP_USER="${POSTGRES_APP_USER:-car_agency}"
APP_PASSWORD="${POSTGRES_APP_PASSWORD:?POSTGRES_APP_PASSWORD is required}"
DB_NAME="${POSTGRES_DB:-api_laravel_car_agency}"
TESTING_DB="testing"

if [ "$APP_USER" = "${POSTGRES_USER}" ]; then
    echo "ERROR: POSTGRES_APP_USER must differ from POSTGRES_USER (superuser)." >&2
    exit 1
fi

echo "Creating application role '${APP_USER}' and making it owner of '${DB_NAME}'."

psql -v ON_ERROR_STOP=1 \
    --username "$POSTGRES_USER" \
    --dbname "$DB_NAME" \
    -v app_user="$APP_USER" \
    -v app_password="$APP_PASSWORD" <<'SQL'
-- `\gexec` ejecuta el CREATE/ALTER resultante: así el script es idempotente y
-- el identificador y la contraseña se escapan con %I / %L.
SELECT format(
    'CREATE ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD %L',
    :'app_user', :'app_password'
)
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app_user')\gexec

-- Reafirma los atributos y la contraseña por si el rol ya existía.
SELECT format(
    'ALTER ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD %L',
    :'app_user', :'app_password'
)\gexec

-- La app debe poder crear tablas: es dueña de la base y del schema public.
SELECT format('ALTER DATABASE %I OWNER TO %I', current_database(), :'app_user')\gexec
SELECT format('ALTER SCHEMA public OWNER TO %I', :'app_user')\gexec
SQL

if [ "${POSTGRES_CREATE_TESTING_DB:-false}" = "true" ]; then
    echo "Creating testing database '${TESTING_DB}' owned by '${APP_USER}'."

    psql -v ON_ERROR_STOP=1 \
        --username "$POSTGRES_USER" \
        --dbname "$DB_NAME" \
        -v app_user="$APP_USER" \
        -v testing_db="$TESTING_DB" <<'SQL'
SELECT format('CREATE DATABASE %I OWNER %I', :'testing_db', :'app_user')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'testing_db')\gexec
SQL

    psql -v ON_ERROR_STOP=1 \
        --username "$POSTGRES_USER" \
        --dbname "$TESTING_DB" \
        -v app_user="$APP_USER" <<'SQL'
SELECT format('ALTER SCHEMA public OWNER TO %I', :'app_user')\gexec
SQL
fi
