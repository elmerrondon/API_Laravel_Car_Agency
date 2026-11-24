# AGENTS.md

API Laravel 12 para una agencia de vehículos. La imagen de desarrollo usa PHP 8.3 (el proyecto declara `^8.2`). Casi todo es JSON bajo `/api`; la única vista web es la página de bienvenida y `/up` es el healthcheck.

## Propósito y jerarquía de documentos

- **`AGENTS.md` (este archivo):** guía operativa para agentes. Entorno, comandos, reglas de Git, arquitectura y convenciones. No es un plan ni un historial.
- **`ROADMAP.md`:** único plan de trabajo y estado de las tareas. Aquí viven los objetivos, checklists y criterios de cierre.
- **`MEMORY.md`:** lecciones aprendidas, decisiones históricas y bugs resueltos. Consúltalo antes de empezar y actualízalo al terminar.
- **`README.md`:** documentación de uso y onboarding para humanos (instalación, rutas, ejemplos).

**Regla:** cada dato vive en un solo archivo. Si es plan, va a `ROADMAP.md`; si es historia, a `MEMORY.md`; si es uso, a `README.md`; si es una norma de trabajo, a este archivo. No dupliques contenido entre ellos.

## Entorno obligatorio: Docker (WSL sin PHP/PostgreSQL local)

Este entorno WSL **no tiene PHP, Composer ni PostgreSQL instalados en el host**. No ejecutes `php`, `composer`, `artisan`, `pest`, `pint`, `psql` ni `pg_dump` directamente.

Todo comando dentro del contenedor `app` debe usar `docker compose exec -T` (`-T` desactiva la TTY y evita cuelgues en agentes no interactivos):

```bash
docker compose exec -T app php artisan ...
docker compose exec -T app composer ...
docker compose exec -T app ./vendor/bin/pest
docker compose exec -T app ./vendor/bin/pint
```

Los comandos de orquestación (`docker compose up`, `down`, `ps`, `logs`) se ejecutan en el host porque son de Docker; no sustituyas `app` por un PHP local.

## Infraestructura Docker

- `docker/php/Dockerfile`: multi-stage con targets `base`, `dev` y `prod` sobre `php:8.3-fpm-alpine`. Incluye Composer y las extensiones `pdo_pgsql`, `pdo_sqlite`, `bcmath`, `gd`, `zip`, `mbstring`, `exif`, `pcntl`, `opcache` y `dom`. El target `prod` ejecuta `composer install --no-dev`, `dump-autoload` optimizado y `php artisan package:discover` en build (dentro de `bootstrap/cache`), así el arranque no descubre paquetes ni escribe esa caché. Ninguna imagen lleva `mysqlnd`: el Dockerfile recompila PHP desde el tarball que ya trae la imagen oficial (`docker-php-source extract`) con `--disable-mysqlnd` y los mismos flags que upstream, antes de instalar las extensiones; así no se fija una versión a mano y se hereda el ritmo de parches de seguridad de la imagen base. Tampoco se instalan `pdo_mysql`, `mysqli` ni el cliente `mysql`. Coste: un build sin caché de capas tarda ~10-20 min más que la imagen oficial.
- `docker-compose.yml`: `app` (target `dev`, FPM 9000 interno), `nginx` (host `8000`), `postgres` 17 (`postgres:17-alpine`, solo `127.0.0.1:5432`, `shm_size: 256mb`, DB `api_laravel_car_agency`, healthcheck `pg_isready`) y `scheduler` (`extends: app` con `php artisan schedule:work`, sin puertos). La app conecta como `car_agency` (`POSTGRES_APP_USER`/`POSTGRES_APP_PASSWORD`), no como el superusuario `postgres`, que solo usan la inicialización y los backups. `docker/postgres/init/01-app-role.sh` crea el rol en el primer arranque y le entrega la propiedad de la base y del schema `public`; en desarrollo también crea la base `testing` (`POSTGRES_CREATE_TESTING_DB=true`). Un volumen `pg-data` anterior a este cambio no tiene el rol: recrea el volumen (`docker compose down -v`) o ejecuta el SQL del script a mano. El servidor arranca con `shared_buffers`/`effective_cache_size`/`work_mem` ajustables y `log_min_duration_statement=${POSTGRES_LOG_MIN_DURATION_STATEMENT:-1000}`; las consultas lentas se leen en `docker compose logs postgres` (no hay tabla de slow log). `POSTGRES_INITDB_ARGS` fija `--locale=C.UTF-8 --encoding=UTF8`.
- `docker-compose.prod.yml`: stack de producción independiente del de desarrollo. `app` construye el target `prod` con `image: car-agency-laravel-api:prod`, `env_file: .env.production`, volumen nombrado `app-storage` en `/var/www/html/storage` y healthcheck por `fsockopen` al puerto 9000 más `php artisan health:check` (base de datos + caché); `nginx` se construye desde `docker/nginx/Dockerfile.prod`, publica solo `127.0.0.1:80` (el TLS lo termina un proxy del host), comprueba `/up` por HTTP y espera a `app` con `service_started` (el primer despliegue aún no tiene tablas y el healthcheck falla hasta `migrate`); `postgres` 17 no expone puertos, persiste en `pg-data`, recibe `POSTGRES_*` desde `.env.production` y aplica el mismo script de rol; arranca con `shared_buffers=${POSTGRES_SHARED_BUFFERS:-512MB}`, `effective_cache_size=${POSTGRES_EFFECTIVE_CACHE_SIZE:-1536MB}`, `work_mem` y `log_min_duration_statement`; `worker` reutiliza `app` con `queue:work --sleep=3 --tries=3 --max-time=3600` y `scheduler` con `schedule:work`, ambos con el healthcheck heredado desactivado. No hay Redis: caché y colas usan tablas de PostgreSQL y las sesiones se escriben en `storage/framework/sessions` dentro de `app-storage`. Red propia `car-agency-prod` con subnet fija `172.28.0.0/16`, que debe coincidir con `TRUSTED_PROXIES`.
- `docker-compose.tls.yml` + `docker/nginx/tls.conf`: superposición opcional para terminar TLS dentro del stack. Publica `80:80` y `443:443` con `ports: !override`, redirige `80 → 443`, sirve con TLS 1.2/1.3 y monta `docker/certs` (ignorado por Git y `.dockerignore`) con `fullchain.pem` y `privkey.pem`. En esa vía el propio Nginx fija `HTTPS`/`REQUEST_SCHEME`/`X-Forwarded-Proto` y sobrescribe `X-Forwarded-For` con `$remote_addr` (un solo salto). La vía recomendada sigue siendo un proxy externo (Caddy/Nginx/Traefik) delante de `127.0.0.1:80`.
- `docker/nginx/Dockerfile.prod`: build multi-stage. La etapa `app` es `FROM car-agency-laravel-api:prod` y solo aporta `/var/www/html/public`; la imagen final es `nginx:1.27-alpine` con el `default.conf` compartido.
- **Regla de construcción en Producción:** Debido a que `docker/nginx/Dockerfile.prod` utiliza un multi-stage build que hace `COPY --from=car-agency-laravel-api:prod`, es obligatorio construir el servicio `app` individualmente antes de levantar el stack completo (`docker compose -f docker-compose.prod.yml --env-file .env.production build app`). De lo contrario, Docker fallará al no encontrar la imagen origen (`pull access denied`).
- **Dos vías de despliegue que conviven:** la «Vía VPS» (`docker-compose.prod.yml` + `docker/php/Dockerfile` + `docker/nginx/Dockerfile.prod` + `.env.production.example`, multicontenedor con PostgreSQL propio) y la «Vía PaaS» (imagen monolítica para Render/Railway publicada en Docker Hub, con PostgreSQL gestionado). Los archivos de la vía PaaS son aditivos: `docker/render/*` y `.github/workflows/deploy-dockerhub.yml`. Al tocar la PaaS no modifiques, renombres ni elimines los de la VPS; la única excepción son los renombrados globales de imágenes acordados con el usuario.
- `docker/render/Dockerfile.render`: imagen única sobre `dunglas/frankenphp:1-php8.3-alpine` (Caddy + PHP embebido, sin `php-fpm` ni Nginx aparte). Recompila `/usr/local/lib/libphp.so` desde el tarball de la imagen con `--disable-mysqlnd` y los mismos flags de FrankenPHP (`--enable-embed --enable-zts --disable-zend-signals`), sin rehacer el binario de Caddy/Go. Instala las mismas extensiones con `install-php-extensions`, ejecuta `composer install --no-dev` en una capa propia, `php artisan package:discover` tras copiar el código, corre como `www-data`, hace `EXPOSE 8080` y declara un healthcheck por `fsockopen` al `$PORT`. Reutiliza `docker/php/php.ini` (solo lectura) y no depende de `docker-compose`.
- `docker/render/Caddyfile`: escucha en `:{$PORT:8080}` (Render/Railway inyectan `PORT`), sirve `/var/www/html/public` con `php_server`, añade las mismas cabeceras de seguridad que Nginx (incluida `Permissions-Policy`, sin cabecera `Server` y CSP solo en `/docs/api`) y manda el access log a `stdout`. Al ser una dirección sin hostname, Caddy **no** activa HTTPS automático: el TLS lo termina la plataforma.
- `docker/render/entrypoint.sh`: crea los directorios de `storage/` y `bootstrap/cache`, aborta si falta `APP_KEY`, ejecuta `php artisan migrate --force` solo con `RUN_MIGRATIONS=true` (añade `--seed` si además defines `RUN_SEED=true`) y `php artisan optimize` salvo `APP_OPTIMIZE=false`; termina con `exec "$@"` para que FrankenPHP reciba las señales del orquestador.
- `.github/workflows/deploy-dockerhub.yml`: en `push` a `main` (y `workflow_dispatch`) hace login con `secrets.DOCKER_USERNAME`/`secrets.DOCKER_TOKEN`, construye solo `linux/amd64` desde `docker/render/Dockerfile.render` y publica las etiquetas `latest` y `sha-<corto>` en `<usuario>/car-agency-laravel-api` usando la caché de GitHub Actions. No ejecuta la suite: esa validación vive en `.github/workflows/ci.yml`.
- `.github/workflows/ci.yml`: workflow ligero e independiente del de Docker Hub. En cada `push` y `pull_request` ejecuta dos jobs. `quality` corre la matriz PHP **8.2/8.3/8.4** (el rango de `composer.json`) con SQLite en memoria y lanza `composer test`, `composer analyse`, `pint --test` y `composer audit`. `postgres` corre en PHP 8.3 contra un servicio `postgres:17-alpine` con locale `C.UTF-8`, ejecuta `migrate:fresh --seed --force` y después `composer test:postgres`, `composer analyse`, `pint --test` y `composer audit`: SQLite no reproduce el cotejo binario, los índices de expresión ni los tipos `numeric` de PostgreSQL. No necesita secretos.
- `.github/dependabot.yml`: PRs semanales agrupados para Composer (producción/desarrollo) y GitHub Actions. Los parches de seguridad llegan como PRs independientes.
- `.env.production.example`: plantilla versionable sin credenciales reales (`CHANGE_ME_*`), con `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `CACHE_STORE`/`CACHE_DRIVER=database`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=database`, `DB_CONNECTION=pgsql` y `DB_SSLMODE=prefer` (el `postgres` del compose no habilita TLS; con PostgreSQL gestionado usa `require`/`verify-full`). Las variables `POSTGRES_*` deben coincidir con `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`. El `.env.production` real está ignorado por Git y el `.dockerignore` no lo copia a la imagen.
- `docker/php/www.conf`: pool de PHP-FPM compartido por `dev` y `prod`. `pm = dynamic` con `pm.max_children = 20` (fórmula `floor((RAM_MB - 512) / 75)`; 1 GB→7, 2 GB→20, 4 GB→47, 8 GB→102), `pm.max_requests = 500` y `request_terminate_timeout = 60s` alineado con `fastcgi_read_timeout` de Nginx. FPM lee el pool solo al arrancar: tras cambiar el valor hay que recrear `app`/`worker`/`scheduler`. En `dev` se eliminan `user`/`group` porque FPM no corre como root.
- `docker/php/opcache-prod.ini`: solo se copia en el target `prod`. `opcache.enable=1`, `memory_consumption=128`, `max_accelerated_files=10000`, `validate_timestamps=0` y JIT apagado (`jit_buffer_size=0`). El código va horneado en la imagen, así que cada despliegue necesita un contenedor nuevo; el entorno `dev` no carga este archivo y sigue revalidando los cambios.
- `docker/php/php-prod.ini`: también exclusivo del target `prod`. Fija `display_errors=Off`, `display_startup_errors=Off`, `log_errors=On`, `error_log=/dev/stderr`, `html_errors=Off` y `expose_php=Off`, porque la imagen oficial no activa ningún `php.ini` y hereda `display_errors=1`/`log_errors=0`. `www.conf` ya tiene `catch_workers_output=yes`, así que los errores llegan a `docker compose logs`. Verificar con `docker run --rm --entrypoint php car-agency-laravel-api:prod -i` y no confundirlo con `php.ini` (compartido) ni con `opcache-prod.ini`.
- `docker/nginx/default.conf`: sirve `/var/www/html/public`, pasa PHP a `app:9000`, reenvía `X-Forwarded-For/Host/Port/Proto` del proxy externo y añade `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` y `Referrer-Policy: no-referrer-when-downgrade` con `always`. `client_max_body_size 8m` replica `post_max_size`/`upload_max_filesize` de `docker/php/php.ini`: un cuerpo mayor responde `413` en el borde. `gzip on` comprime `application/json`, `text/csv` y `text/plain` (nunca `application/pdf`, que ya está comprimido); `docker/nginx/tls.conf` repite la misma configuración y el `Caddyfile` de PaaS usa `encode zstd br gzip`. `server_tokens off` oculta la versión, `Permissions-Policy` desactiva geolocalización/micrófono/cámara/pago/USB y un `map $request_uri` añade CSP solo a `/docs/api` (Stoplight Elements carga de `unpkg.com` y usa scripts inline); el resto de respuestas no lleva CSP porque no renderizan HTML. `AddHstsHeader` añade `Strict-Transport-Security` solo cuando la petición es HTTPS y `HSTS_ENABLED=true`; `HSTS_MAX_AGE` y `HSTS_INCLUDE_SUBDOMAINS` viven en `config/security.php`.
- `.dockerignore` excluye `.git`, `vendor`, `node_modules`, `.env`, `storage/*.key`, logs, cachés y artefactos de build.
- `docker/scripts/backup-postgres.sh` y `docker/scripts/restore-postgres.sh` son el runbook ejecutable de backups: `pg_dump -Fc` (formato custom, ya comprimido) se ejecuta dentro del contenedor `postgres` y lee `POSTGRES_DB`/`POSTGRES_USER`/`POSTGRES_PASSWORD` del propio contenedor, así que las credenciales no viajan por el host; el script aplica `umask 077` y `chmod 600` porque el dump contiene datos personales; retención por `BACKUP_RETENTION_DAYS` (7 por defecto). La restauración exige `--confirm`, detiene `app`/`worker`/`scheduler`, recrea la base como superusuario con `dropdb --force`/`createdb --owner car_agency`, importa con `pg_restore` **como `car_agency`** (`--no-owner --no-privileges --single-transaction`) para que las tablas sigan siendo del rol de la app y, al terminar, arranca los servicios y hace `nginx -s reload` (Nginx resuelve `app` al cargar la configuración y `stop`+`start` puede cambiar su IP). `COMPOSE_FILE`/`ENV_FILE` permiten usarlos contra desarrollo. `./backups` está ignorado por Git. El procedimiento y la programación con cron/systemd viven en `README.md`.
- `phpunit.xml` fuerza SQLite `:memory:` para la suite rápida; `phpunit-postgres.xml` apunta a la base `testing` del `postgres` de desarrollo (`DB_CONNECTION=pgsql`, host `postgres`, usuario `car_agency`) y se ejecuta con `composer test:postgres`. La suite de PostgreSQL es la que valida de verdad cotejo, índices de expresión, `numeric` y constraints.
- El target `dev` crea y usa el usuario local UID/GID 1000. Si tu usuario WSL usa otros IDs, reconstruye con `docker compose build --build-arg UID=$(id -u) --build-arg GID=$(id -g) app`.
- `vendor/` es un volumen nombrado y la etapa `dev` no ejecuta `composer install`. En el primer arranque, o si cambia `composer.lock`, ejecuta `docker compose exec -T app composer install`; reconstruir la imagen no llena el volumen.
- `composer setup` **no** es adecuado dentro de `app`: intenta ejecutar `npm install`/`npm run build` y la imagen PHP no incluye Node. Usa `composer install`; Vite no forma parte del stack Docker actual. `composer dev` tampoco aplica: el stack ya sirve con Nginx + PHP-FPM.
- `.env` es local y está ignorado por Git. `.env.example` ya trae los valores del stack Docker (`APP_URL=http://localhost:8000`, `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PORT=5432`, `DB_DATABASE=api_laravel_car_agency`, `DB_USERNAME=car_agency`, `DB_PASSWORD=secret`, `DB_SSLMODE=prefer`, `DB_TIMEZONE=UTC`, `POSTGRES_*`, `SESSION_DRIVER=file`); en el primer arranque cópialo con `cp .env.example .env`. `POSTGRES_APP_PASSWORD` debe coincidir con `DB_PASSWORD`.
- No existe migración de la tabla `sessions`: con `SESSION_DRIVER=database` el login puede fallar. El `.env` local usa `file`; si se cambia a `database`, primero hay que crear esa migración.

## Comandos

```bash
# Primer arranque
cp .env.example .env
docker compose up -d --build
docker compose exec -T app composer install
docker compose exec -T app php artisan key:generate   # solo si APP_KEY está vacío
docker compose exec -T app php artisan migrate --seed

# Arranque y estado (host, no requieren -T)
docker compose up -d --build
docker compose ps
docker compose logs -f app
docker compose down

# Dependencias PHP, clave y base de datos (siempre dentro de app con -T)
docker compose exec -T app composer install
docker compose exec -T app php artisan key:generate   # solo si APP_KEY está vacío
docker compose exec -T app php artisan migrate --seed
docker compose exec -T app php artisan migrate:fresh --seed

# Tests
docker compose exec -T app composer test              # limpia config y ejecuta artisan test
docker compose exec -T app composer test:postgres     # suite contra PostgreSQL real (base `testing`)
docker compose exec -T app ./vendor/bin/pest          # suite completa (SQLite :memory:)
docker compose exec -T app ./vendor/bin/pest --filter='nombre'
docker compose exec -T app ./vendor/bin/pest tests/Feature/FooTest.php

# Formato
docker compose exec -T app ./vendor/bin/pint

# Mantenimiento de auditoría (retención por defecto: AUDIT_RETENTION_DAYS=90)
docker compose exec -T app php artisan audit:prune
docker compose exec -T app php artisan audit:prune --days=30
docker compose exec -T app php artisan schedule:list

# Backups y restauración de PostgreSQL (producción por defecto; COMPOSE_FILE/ENV_FILE para desarrollo)
./docker/scripts/backup-postgres.sh backups
./docker/scripts/restore-postgres.sh backups/api_laravel_car_agency-YYYYMMDD-HHMMSS.dump --confirm

# Cliente psql (rol de la aplicación; el superusuario es solo para administración)
docker compose exec -T postgres psql -U car_agency -d api_laravel_car_agency
docker compose exec -T postgres psql -U postgres -d api_laravel_car_agency

# Producción (usa su propio compose y su propio env; no mezclar con .env)
cp .env.production.example .env.production
docker compose -f docker-compose.prod.yml --env-file .env.production build app
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
docker compose -f docker-compose.prod.yml --env-file .env.production exec -T app php artisan migrate --force --seed
docker compose -f docker-compose.prod.yml --env-file .env.production exec -T app php artisan optimize
docker compose -f docker-compose.prod.yml --env-file .env.production down

# TLS propio (opcional): certificados en docker/certs y superposición
docker compose -f docker-compose.prod.yml -f docker-compose.tls.yml --env-file .env.production up -d
docker compose -f docker-compose.prod.yml -f docker-compose.tls.yml --env-file .env.production exec -T nginx nginx -t
docker compose -f docker-compose.prod.yml -f docker-compose.tls.yml --env-file .env.production exec -T nginx nginx -s reload

# PaaS (imagen monolítica para Render/Railway; sin compose y con PostgreSQL gestionado)
docker build -f docker/render/Dockerfile.render -t car-agency-laravel-api:paas .
docker run --rm -p 8080:8080 \
  -e APP_KEY=base64:... \
  -e DB_CONNECTION=sqlite -e DB_DATABASE=/var/www/html/storage/local.sqlite \
  -e ADMIN_EMAIL=admin@agency.test -e ADMIN_PASSWORD='S3gura-2026' \
  -e RUN_MIGRATIONS=true -e RUN_SEED=true car-agency-laravel-api:paas
curl -i http://localhost:8080/up
# En Render/Railway, en su lugar: DB_CONNECTION=pgsql, DB_HOST/DB_PORT/DB_DATABASE/
# DB_USERNAME/DB_PASSWORD del PostgreSQL gestionado y DB_SSLMODE=require (o verify-full).
# Docker Hub lo publica GitHub Actions (push a main); para probar el push a mano:
docker tag car-agency-laravel-api:paas <usuario>/car-agency-laravel-api:latest
```

El repo no versiona `vendor/`, `.env` ni `node_modules/`. `.env.example` ya está alineado con Docker Compose (`DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PASSWORD=secret`, `SESSION_DRIVER=file`, `APP_URL=http://localhost:8000`) y usa PostgreSQL con la base `api_laravel_car_agency`; `phpunit.xml` fuerza SQLite `:memory:` para la suite rápida y `phpunit-postgres.xml` apunta a la base `testing` de PostgreSQL (`composer test:postgres`). El workflow `.github/workflows/ci.yml` repite `composer test`, `composer analyse`, `pint --test` y `composer audit` en la matriz PHP 8.2/8.3/8.4 con SQLite en memoria, y añade un job `postgres` con PHP 8.3 contra `postgres:17-alpine`; el de Docker Hub solo publica la imagen PaaS (`linux/amd64`).

## Reglas de Git

- **Commits Automáticos por Tarea:** El agente SÍ debe ejecutar `git add` y `git commit` automáticamente al finalizar cada micro-tarea del `ROADMAP.md`, siempre usando Conventional Commits (`feat:`, `fix:`, `refactor:`, `chore:`, `docs:`) y solo después de verificar que el código funciona en Docker. Nunca incluir el archivo `.env` en los commits.
- **Operaciones Prohibidas:** El agente tiene estrictamente prohibido ejecutar `git push`, `git checkout`, `git branch`, `git merge`, `git rebase` o `git reset`. La gestión de ramas y subidas al remoto la hace únicamente el usuario.
- Usa `git status` y `git diff` para preparar cada commit; revisa que no entren `.env`, `vendor/`, `node_modules/` ni artefactos de build.

## Flujo de trabajo

- `ROADMAP.md` es el único plan de trabajo y la fuente de verdad del avance: revísalo antes de empezar y marca cada tarea con `[x]` al terminarla. No crees listas TODO paralelas.
- `MEMORY.md` es el historial técnico: consúltalo antes de empezar y añade una entrada fechada al cerrar cada tarea relevante.
- Cada micro-tarea del roadmap equivale a un commit atómico. Antes de commitear, verifica en Docker con `composer test`, `composer analyse`, `pint --test` y `composer audit`.
- Si una tarea cambia el plan, actualiza `ROADMAP.md`; si cambia una decisión o resuelve un bug no obvio, actualiza `MEMORY.md`. No dupliques en este archivo el plan ni el historial.

## Estructura del código

La app está organizada por dominio de negocio, no por las carpetas estándar de Laravel:

`app/{Enums,DTOs,Models,Services,Http/Controllers,Http/Requests,Http/Resources}/{Cars,Locations,Sales,Users}`; los reportes viven en `app/{Services,Http/Controllers,Http/Requests}/Reports`, la auditoría en `app/{Services,Http/Controllers,Http/Requests}/Audit`, las políticas transversales en `app/Support` (por ejemplo `PasswordPolicy` y `CorsPolicy`), las vistas en `resources/views/reports` y los metadatos de OpenAPI en `app/OpenApi`.

Flujo de una petición: `routes/api.php` -> FormRequest en `Http/Requests/...` -> `DTOs/.../*Data::fromRequest()` -> servicio en `app/Services` -> `Http/Resources/...`.

- Mantén los controllers delgados; reglas de negocio, transacciones y eager loading viven en `app/Services`.
- No hay capa repository; los servicios concentran la lógica y el README describe la arquitectura por dominio.
- Los modelos usan subnamespaces de dominio (`App\Models\Cars\Car`, `App\Models\Users\User`), no `App\Models\Car`. Mantén imports y factories alineados al añadir modelos.
- Las rutas API viven solo en `routes/api.php` (Laravel añade el prefijo `/api`). El route-model binding usa los modelos de dominio directamente.

### Modularidad y tamaño de las clases

- **Umbral práctico:** las clases de aplicación (`app/Services`, `app/Http/Controllers`, `app/OpenApi`, etc.) deben mantenerse idealmente entre 150 y 250 líneas. Un archivo que ya esté por debajo de ~250 líneas no se fragmenta.
- **Sin micro-fragmentación:** no se crea una clase o Action por cada endpoint o método. La unidad de división es el contexto cohesivo, no la operación individual.
- **División por cohesión:** cuando un archivo supere ~250 líneas, se separa en 2–3 clases agrupadas en una subcarpeta de su dominio dentro de `app/Services/<Dominio>/` (o la carpeta equivalente). Ejemplos vigentes:
  - `app/Services/Stats/`: `StatsQuery` (consulta base y helpers), `StatsSummaryService` (KPIs y tendencia), `StatsInventoryService` (inventario) y `StatsRankingService` (rankings).
  - `app/Services/Users/`: `UserService` (CRUD), `UserAdminGuard` (guarda del último administrador) y `UserPasswordService` (claves temporales).
  - `app/Services/Audit/`: `AuditService` (escritura y redacción) y `AuditQueryService` (consulta, paginación y CSV).
  - `app/OpenApi/`: `ApiDocumentation` (catálogos y orquestación), `ApiDocumentationParameters` (paginación y filtros) y `ApiDocumentationResponses` (binarios y errores).
- **Cero cambios de contrato:** al dividir se conservan las rutas, las respuestas JSON y el comportamiento observable. Solo se actualizan las inyecciones de dependencias en los controllers o servicios consumidores.
- **Tests:** los archivos de `tests/` también siguen la regla; si un test monolítico supera ~250 líneas se divide por contexto (por ejemplo `tests/Feature/Docs/OpenApiDocumentTest.php`, `OpenApiOperationsTest.php` y `OpenApiDomainTest.php`) y los helpers compartidos se mueven a `tests/Pest.php`.

## Convenciones y gotchas

- Los DTOs son clases `readonly` con campos nullable y un `fromRequest(FormRequest)` estático. La mayoría de los servicios filtran nulls antes de crear/actualizar, así que las actualizaciones son parciales: omite un campo (o envía `null`) para dejarlo sin cambios.
- Los endpoints de index paginados aceptan `page` (lo lee el paginador de Laravel, por eso no se valida) y `per_page` (1-100, validado en el FormRequest); el tamaño de página por defecto es 15. Cada servicio pagina con `orderBy('id')` y `withQueryString()`: el orden es determinista y los enlaces de paginación conservan `per_page` **y cualquier filtro activo**. `PaginationTest` lo exige en los 16 listados. `ApiDocumentationParameters` añade `page` a la documentación de todo listado que declare `per_page`, así que si creas un listado nuevo el contrato se completa solo.
- Los 16 listados tienen filtros por dominio (matriz en `ROADMAP.md`, fase 6). El controlador castea la query string en un array tipado con `nullableInt`/`nullableFloat`/`nullableBool` (patrón de `AuditLogController`) y el servicio aplica los filtros antes de `orderBy('id')`. La búsqueda de texto usa `ListFilters::search()` / `searchAny()`: prefijo `lower(col) LIKE 'termino%'` con comodines escapados, sin `pg_trgm` y sin substring. Los nombres de columna pasados a `ListFilters` son literales del servicio, nunca datos del usuario. Los filtros booleanos validan con `ListFilters::BOOLEAN_VALUES` (`'1'`, `'0'`, `'true'`, `'false'`; solo cadenas), porque mezclar booleanos y cadenas hace que Scramble documente un enum con duplicados. `from`/`to` de ventas son rangos sargables sobre `sale_date`, nunca `whereDate()`.
- Los Enums son string-backed (`CarStatus`, `SaleStatus`, `RoleEnum`, `PermissionEnum`) y están casteados en los modelos. Valida con `Rule::enum(...)` y compara instancias del enum en los servicios, no strings crudos.
- La creación de ventas es la ruta financiera: `SaleService` envuelve el trabajo en `DB::transaction`, redondea cada importe derivado a 2 decimales, rechaza coches que no estén `available` y marca el coche como `sold`; la cancelación lo devuelve a `available`. La venta persiste siempre la sucursal del coche (`$car->branch_id`) y responde `422` con `SaleBranchMismatchException` si el payload envía otra; el vendedor se fija con `Auth::id()` y `user_id` no existe en `StoreSaleRequest`/`SaleData`. Si no hay moneda base (`Currency::firstWhere('is_base', true)` nulo), responde `409` con `MissingBaseCurrencyException` en lugar de fallar con `500`. Mantén la nueva lógica financiera dentro de transacciones y con 2 decimales.
- Los reportes viven bajo `/api/reports/*` y exigen `view_reports`. `SaleReportService` filtra por `from`/`to` (juntos o ninguno) y `branch_id`, y resume los importes en moneda base; `CarReportService` agrega por SQL por tipo, marca y estado, excluyendo los coches con soft delete. Las vistas Blade comparten `reports/layout.blade.php` y usan DejaVu Sans para los acentos en Dompdf.
- `StoreSaleRequest` exige `sale_date` exactamente como `Y-m-d H:i:s`, no futura y no anterior a `config('sales.min_date')` (`SALES_MIN_DATE`, por defecto `2000-01-01`), un array `taxes` no vacío con IDs de impuestos activos, y `discount_percentage`/`exchange_rate` con como máximo 2 decimales; `exchange_rate` debe ser mayor que `0` (la moneda base usa tasa `1`).
- Los Resources exponen relaciones solo mediante `whenLoaded()`. Haz eager loading en el servicio o las claves de relación desaparecen; cada servicio centraliza sus eager loads en constantes y `QueryCountTest` fija el número de consultas de todos los listados, así que un N+1 o un eager load de más rompe la suite. `Model::preventLazyLoading()` está activo fuera de producción (`AppServiceProvider`).
- `ForceJsonResponse` está prepended a todo el grupo de middleware `api` en `bootstrap/app.php`; mantenlo para que los errores de validación y autenticación sigan siendo JSON.
- La autenticación son tokens bearer de Sanctum desde `POST /api/auth/login`, la única ruta pública. Todo el resto del grupo `api` exige `auth:sanctum`, el middleware `active` y `permission:<permiso>` (alias registrado en `bootstrap/app.php`), que consulta los roles del usuario mediante `User::hasPermissionTo()`. Esa consulta se cachea `PERMISSIONS_CACHE_TTL` segundos (300 por defecto; `0` desactiva la caché) y `UserService`/`RoleService` invalidan la entrada al cambiar usuarios o roles; si tocas permisos por fuera de esos servicios, llama a `User::flushPermissionCacheForRole()`. Los tokens nuevos llevan prefijo `car_` (`SANCTUM_TOKEN_PREFIX`) y los antiguos siguen siendo válidos. Hay 15 permisos sembrados y la matriz rol-permiso vive en `RolePermissionSeeder` (`admin` 15, `manager` 11, `vendedor` 5); los usuarios demo los crea `UserSeeder`. Los `authorize()` de los FormRequest siguen devolviendo `true`: la autorización real es el middleware, no hay policies ni Gates (la única excepción es `viewApiDocs`, que protege la documentación).
- Soft deletes en `User` y `Car`. `Sale` no usa borrado lógico: se cancela con `SaleStatus::CANCELLED`, que además libera el coche. `Sale::car()` usa `withTrashed()` para que el histórico (detalle y reportes) conserve marca, modelo y VIN aunque el coche se haya borrado lógicamente.
- El estado del coche tiene una matriz cerrada: el alta solo admite `available`, la edición manual mueve entre `available` y `reserved`, y `sold` lo asigna únicamente `SaleService`. `CarService::updateCar` rechaza con `422` (`CarStatusTransitionException`) cualquier cambio sobre un coche con venta `completed`; cancelar la venta lo devuelve a `available` y lo libera. Las validaciones de `StoreCarRequest`/`UpdateCarRequest` replican la matriz con `Rule::in`.
- Los códigos de sucursal se generan en el servidor a partir de `max(id)` (`SUC-0001`); el payload de la petición no aporta `code`.
- Los datos demo están en español. `CarSeeder` busca nombres exactos (`Negro`, `Blanco`, `Sedán`, `Pick-up`, `Corolla`, `F-150`, etc.); mantén los seeders sincronizados si renombras. El orden de `DatabaseSeeder` importa: roles/permisos -> ubicaciones -> usuarios base -> coches -> catálogos de ventas, y `CarSeeder` necesita los catálogos y sucursales anteriores.
- **Seeders: datos demo vs. fundacionales.** `DatabaseSeeder` corta en seco cuando `app()->environment('production')`: solo llama a `ProductionAdminSeeder`. Ese seeder no usa Faker; siembra roles/permisos (`RoleSeeder`, `PermissionSeeder`, `RolePermissionSeeder`) y crea/actualiza el administrador con `User::updateOrCreate()` usando `config('seeders.admin_*')` (variables `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME`, `ADMIN_LAST_NAME`, `ADMIN_CODE`, `ADMIN_DOCUMENT_NUMBER`). Es idempotente, identifica al usuario por `code` (no por email), restaura el registro si estaba soft-deleted y re-aplica `ADMIN_PASSWORD` en cada ejecución. Los seeders demo (`UserSeeder` y `CarSeeder`, este último vía `CarFactory`/Faker) solo corren en local/testing; nunca añadas `fake()` ni `factory()` a un seeder que pueda ejecutarse con `composer install --no-dev`.
- Los tests usan Pest con `RefreshDatabase` activo para `tests/Feature` (cada test arranca con migraciones limpias y debe sembrar lo que necesite). `UserFactory` vive en `database/factories/Users/UserFactory.php`, usa el modelo `App\Models\Users\User` y genera usuarios con las columnas reales; `User::factory()` funciona.
- No hay tests JS. La verificación local es toda dentro de Docker (`composer test`, `composer test:postgres`, `composer analyse`, `pint --test` y `composer audit`); `.github/workflows/ci.yml` repite esa misma verificación en PHP 8.2/8.3/8.4 con SQLite en memoria y añade un job con PostgreSQL 17 real. El análisis necesita `--memory-limit=1G` (el script `composer analyse` ya lo pasa); PHPStan 2 rechaza `memoryLimit` en `phpstan.neon`.
- Todo el código de `app/`, `routes/`, `database/` y `config/` declara `strict_types=1`; los únicos archivos sin él son los generados en `bootstrap/cache/`. Los límites de PHP obligan a castear en el borde (`Controller::perPage()`, `(int)`/`(string)` sobre `validated()`), no dentro de los servicios.

### Identidad y acceso

- `EnsureUserIsActive` (`active`) rechaza con `401` el token de una cuenta desactivada, aunque se emitiera antes de la desactivación. El login también exige `is_active`.
- `must_change_password` es informativo por defecto: se expone en el login y en `/auth/me`, y `PUT /auth/password` lo limpia, conserva el token de la petición y revoca los demás. Toda clave fijada por un administrador (`PATCH /users/{user}` con `password` o `POST /users/{user}/reset-password`) marca el flag, revoca los tokens del objetivo y registra `USER_PASSWORD_CHANGED`/`USER_PASSWORD_RESET`. El middleware `password.changed` solo bloquea cuando `ENFORCE_PASSWORD_CHANGE=true` (config `security.enforce_password_change`, desactivado por defecto): con el flag activo solo permite `GET /api/auth/me`, `PUT /api/auth/password` y `POST /api/auth/logout`; el resto responde `403`.
- La política de contraseñas es 100 % local (`App\Support\PasswordPolicy`): producción exige 8 caracteres con mayúsculas, minúsculas, números y símbolos; local/testing usa mínimo 6. **Nunca** añadas `->uncompromised()` ni otra regla que haga HTTP saliente. `PasswordPolicyTest` fija la regla fuerte y comprueba que `uncompromised` está desactivado.
- `POST /users/{user}/reset-password` (permiso `manage_users`) asigna una clave temporal, marca `must_change_password` y revoca todos los tokens del objetivo.
- `UserService` protege al último administrador activo: desactivarlo, borrarlo o quitarle el rol `admin` responde `409` (`LastAdminException`). Solo cuentan administradores activos y no borrados lógicamente.

### CORS

- `config/cors.php` normaliza `CORS_ALLOWED_ORIGINS` (lista separada por comas; vacío equivale a `*`). En `local`/`testing` el comodín es válido y es el valor por defecto.
- En producción el arranque HTTP lanza `InsecureCorsConfigurationException` (500) si la lista está vacía o contiene `*`. Los comandos artisan omiten la comprobación para que `package:discover` y `optimize` funcionen durante el build, donde aún no existe `.env.production`.
- `CorsPolicy` (`app/Support`) concentra la regla y `CorsConfigTest` la cubre. `.env.production.example` trae un origen explícito (`https://app.example.com`).

### Proxies de confianza

- `TRUSTED_PROXIES` es una IP o una lista de IPs/CIDR separados por comas: la red de Compose en la VPS, la red del borde en PaaS. `TrustedProxyPolicy` (`app/Support`) rechaza `*` en producción en el arranque HTTP (los comandos artisan lo omiten, igual que CORS); dejarlo vacío es seguro pero desactiva `X-Forwarded-Proto` y la granularidad por cliente.
- Nunca uses `*` como atajo: Laravel se queda con la **última** IP de `X-Forwarded-For`, así que si el borde antepone la IP real y conserva la del cliente, gana la falsificada. La decisión no depende del proveedor: el edge debe **sobrescribir** la cabecera con una sola IP.
- `ClientIpResolver` (`app/Support`) es la única fuente de IP para auditoría (`AuditService`, `LoginFailureAuditor`) y para las claves de todos los limitadores (`AppServiceProvider`). Acepta `X-Forwarded-For` solo si el par inmediato está en `TRUSTED_PROXIES` y la cabecera contiene una única IP válida; con varios saltos, IP inválida o par no confiable usa la IP de la conexión (fail closed). No llames a `$request->ip()` directamente en esos consumidores.
- Cobertura en `TrustedProxyConfigTest` (política) y `TrustedProxyTest` (auditoría, `login_ip` y `api-anon`). La recomendación de VPS y PaaS es la misma; solo cambia el valor configurado.

### Auditoría

- `audit_logs` es append-only: `$guarded = ['*']`, solo escribe `AuditService` con `forceFill()` y no hay rutas de escritura (responden `405`).
- La auditoría vive en los servicios, nunca en middleware o controladores. Captura el actor Sanctum (`Auth::user()`), IP, User-Agent, valores antiguos/nuevos y redacta claves sensibles (`password`, `token`, `secret`, etc.). Un fallo al insertar registra un warning y devuelve `null`, sin romper la operación de negocio.
- En operaciones con `DB::transaction` la auditoría se escribe **después** del commit; así no queda un registro de algo que se revirtió.
- `AuditAction` enumera las acciones (`auth.*`, `user.*`, `role.*`, `car.*`, `branch.*`, `sale.*`, `catalog.*`, `location.*`) y el morph map de `AppServiceProvider` fija los alias `user`, `role`, `car`, `branch`, `sale`, `brand`, `car_model`, `car_type`, `category`, `color`, `currency`, `payment_method`, `tax`, `country`, `state` y `city` en `auditable_type`.
- `GET /api/audit-logs` y el show exigen `view_audit_logs` (solo `admin`) y filtran por `user_id`, `action`, `auditable_type`, `auditable_id` y rango `from`/`to` inclusivo (juntos o ninguno).
- `GET /api/audit-logs/export` reutiliza los mismos filtros (sin paginación) y devuelve un CSV por `StreamedResponse` con cursor, BOM UTF-8 y escape de celdas que empiezan por `=`, `+`, `-`, `@`, tabulador o retorno de carro. Va declarado antes del `apiResource` para que `export` no choque con `{audit_log}`.
- La tabla no crece sin límite: `config/audit.php` define `retention_days` (`AUDIT_RETENTION_DAYS=90`), el comando `audit:prune {--days=}` borra solo lo anterior al corte, en lotes de 1000 ids para no bloquear la tabla, y `routes/console.php` lo programa a diario a las 03:00 con `withoutOverlapping()`. Sin `--days`, usa la configuración. En el mismo archivo, `sanctum:prune-expired --hours=24` corre a las 03:15 para que `personal_access_tokens` no acumule tokens caducados; ambos los ejecuta el servicio `scheduler` (`schedule:work`). En PaaS hace falta un cron externo con `php artisan schedule:run`.

### Estadísticas

- Los ocho endpoints `/api/stats/*` exigen `view_statistics` (`admin` y `manager`). Solo agregan ventas `completed` y usan `total_base_amount` en moneda base.
- `start_date`/`end_date` son `Y-m-d` inclusivos y van juntos o ninguno; `branch_id` es un filtro opcional. Usa comparaciones sargables (`>= inicio 00:00`, `< fin + 1 día`), nunca `whereDate()`.
- Los rankings aceptan `limit` 1-100 (10 por defecto) y devuelven menos filas si no hay datos; el orden es estable (`sales_count desc`, `total_base_amount desc`, nombre, id).
- Usuarios y coches históricos se leen con joins crudos para incluir soft deletes; el inventario usa el scope de `Car` y sí los excluye. La tendencia rellena con cero los meses sin ventas y agrupa con `to_char(sale_date, 'YYYY-MM')` en PostgreSQL y `strftime('%Y-%m', sale_date)` en SQLite (`StatsQuery::monthExpression()`).
- Los índices de estadísticas son `sales(branch_id, status, sale_date)` y `cars(branch_id, status)`; se eligieron con `EXPLAIN` y `IndexesTest` los exige. Con 3 000 ventas, `EXPLAIN (ANALYZE, BUFFERS)` confirma que los rankings y la tendencia usan `sales_branch_id_sale_date_index` (bitmap, ~0,8 ms); el resumen con sucursal y un rango amplio también (375 filas, 68 buffers), mientras que un rango corto prefiere `sales_sale_date_index` y el resumen sin filtros hace `Seq Scan` (1,3 ms), porque a ese volumen leer la tabla es más barato; con `enable_seqscan=off` se comprueba que `sales_status_sale_date_index` sigue siendo usable.
- Los ocho agregados se cachean `STATS_CACHE_TTL` segundos (45 por defecto) en `StatsCache`, con clave `stats:v{version}:{endpoint}:{md5(filtros)}`. `SaleService` incrementa la versión tras crear o cancelar una venta (después del commit y de la auditoría); un TTL de `0` desactiva la caché. `QueryCountTest` fuerza `stats.cache_ttl=0` para seguir midiendo consultas reales.

### Autosuficiencia del stack

- No añadas servicios externos ni dependencias pesadas: nada de SMTP/email, OAuth/Google, Redis, brokers de colas externos ni almacenamiento gestionado. Todo vive en la API + PostgreSQL (Sanctum, caché y colas en tablas, sesiones en archivo); en producción el servicio `worker` procesa la tabla `jobs`. Tampoco hay llamadas HTTP salientes en tiempo de ejecución: por eso la política de contraseñas no usa `uncompromised()` (Have I Been Pwned) y la auditoría se exporta con código nativo, sin librerías de terceros. En la vía PaaS el PostgreSQL es gestionado por la plataforma (Render/Railway/Neon/Supabase), pero sigue siendo el único servicio externo y se conecta con `DB_SSLMODE=require` o `verify-full`.

### Unicidad e índices (PostgreSQL)

- `cars.vin` y `users.code` son únicos a nivel global: no incluyen `deleted_at` y no se pueden reutilizar tras un soft delete. Es una decisión de trazabilidad y auditoría, no un descuido; no los conviertas en índices compuestos. `currencies.code`, `taxes.code` y `branches.code` también conservan su índice plano porque la API normaliza el código en el borde antes de escribirlo.
- **El cotejo por defecto es `C`/binario**: `Sedán` y `sedán` serían filas distintas y `WHERE name = 'sedan'` no encontraría `Sedán`. La unicidad de catálogos y usuarios se implementa con índices de expresión `lower(...)` (`2026_11_18_000001_add_case_insensitive_unique_indexes.php`), que además son los que aprovechan las búsquedas `whereRaw('lower(name) = ?')`. Los índices de `users.email` y `users.document_number` son **parciales** (`WHERE deleted_at IS NULL`) porque el par `(email, deleted_at)` no protege la unicidad entre filas activas (los `NULL` son distintos entre sí). La unicidad real la impone el FormRequest con `Rule::unique(...)->withoutTrashed()`; si añades una ruta que escriba usuarios sin pasar por ese FormRequest, replica la validación.
- **PostgreSQL no crea índice por cada clave foránea.** `2026_11_18_000000_add_foreign_key_indexes.php` los añade para que `DELETE`/`UPDATE` sobre la tabla padre y los filtros por FK no hagan `Seq Scan`; no repite columnas que ya van al frente de un índice compuesto (`cars.branch_id`, `sales.branch_id`, `audit_logs.user_id`, etc.). Antes de crear un índice, revisa `\d tabla` (o `pg_indexes`) y no dupliques estructura.
- Los índices de estadísticas son `sales(branch_id, status, sale_date)` y `cars(branch_id, status)`; se eligieron con `EXPLAIN` y `IndexesTest` los exige.
- `cars` ya no tiene índices compuestos con `deleted_at`: el antiguo índice llamado `deleted_at` era un duplicado de `cars_vin_unique` sobre `vin` y se eliminó.

### Alcance multi-sucursal

- **No hay scoping por sucursal, y es intencional.** Cualquier usuario con el permiso de lectura ve los datos de las 8 sucursales; `branch_id` es un filtro opcional en reportes, nunca una restricción de autorización. Se revisó BOLA/OWASP API1 y se descartó porque el negocio (una agencia con administración centralizada) necesita visión global.
- No añadas filtros automáticos por la sucursal del usuario ni un middleware de scope sin decisión explícita del usuario: rompería reportes y listados del `manager`. Si algún día hace falta aislamiento, va en la capa de servicio con una constante de scopes revisada, no en el middleware `permission`.

### Documentación OpenAPI

- Scramble 0.13 documenta rutas, `FormRequest` y `Resource`; `app/OpenApi/ApiDocumentation.php` añade lo que no puede inferir: grupos en español, resúmenes, `page`/`per_page`, filtros de reportes y errores `403`/`409`/`429`.
- El esquema `bearerAuth` se declara en `app/OpenApi/BearerAuthSecurityStrategy.php` y `config/scramble.php` solo referencia la clase con un array de opciones. No devuelvas objetos `SecurityScheme` desde la configuración: `php artisan config:cache` no los puede serializar y `php artisan optimize` falla con `value at "scramble.security_strategy.1.scheme" is non-serializable`.
- El transformador se registra en `AppServiceProvider::boot()` dentro de `$this->app->booted()` a propósito: Scramble es un provider de paquete y su `boot()` corre después del de la app, así que si lo registras directamente en `boot()` sus extensiones se ejecutan después y pisan tags/resúmenes.
- `/docs/api` y `/docs/api.json` viven **fuera** de `/api` y los protege el Gate `viewApiDocs`: abiertos en `local`/`testing`, en el resto solo con `DOCS_ENABLED=true`. `composer docs:export` escribe `storage/app/openapi.json` (ignorado por Git).
- Los PDF se documentan con `#[Response(200, mediaType: 'application/pdf', format: 'binary')]`; el transformador elimina el `application/json` que Scramble infiere de `download()`.
- La exportación CSV se documenta con `#[Response(200, mediaType: 'text/csv', format: 'binary')]`; el transformador elimina el `application/json` y la variante `text/csv; charset=...` para dejar un único `text/csv`.
- El documento declara la licencia MIT desde `ApiDocumentation::document()` (Scramble no la expone por configuración) y los dos FormRequest de ubicaciones leen el modelo con `$this->route('state'|'city')`: Scramble no evalúa propiedades dinámicas como `$this->state` y avisaba de reglas incompletas en `scramble:export`.
- El 403 solo se añade a rutas con middleware `permission:*`; el 409 a `PATCH /sales/{sale}/cancel`, a los `DELETE` con FK y a la guarda del último administrador (no a `cars`/`users`, que usan soft delete); el 429 a todas.
- Los parámetros de ruta van en minúscula (`Tax $tax`, no `Tax $Tax`): una mayúscula genera `/taxes/{Tax}` y la validación OpenAPI lo marca como path ambiguo/duplicado frente a `/taxes/{tax}`.
- Si ejecutas `php artisan scramble:cache`, el documento queda congelado; usa `scramble:clear` después de cambiar rutas o validaciones.
- Validación del contrato (en el host, la imagen PHP no trae Node): `npx @redocly/cli lint storage/app/openapi.json` y `npx openapi-to-postmanv2 -s storage/app/openapi.json -o /tmp/postman.json`.

### Límites y rendimiento

- Rate limits en `config/rate_limits.php`: `login` 5/min (email+IP), `login_ip` 20/min (IP), `api-anon` 30/min (IP sin token), `api` 120/min, `reports` 30/min, `reports-pdf` 5/min y `audit-export` 10/min (usuario, o IP sin token), además de `health` 30/min por IP para `/up`. Ajustables con `RATE_LIMIT_LOGIN`, `RATE_LIMIT_LOGIN_IP`, `RATE_LIMIT_API_ANON`, `RATE_LIMIT_API`, `RATE_LIMIT_REPORTS`, `RATE_LIMIT_REPORTS_PDF`, `RATE_LIMIT_AUDIT_EXPORT` y `RATE_LIMIT_HEALTH`. Los PDF llevan el cupo general de reportes **y** el suyo propio, porque Dompdf cuesta mucho más que el JSON; la exportación CSV de auditoría también suma el cupo `api` y el suyo.
- `/up` se registra con `then:` fuera del grupo `api` (sin sesión ni CSRF), así que `ThrottleAnonymousApi` no lo alcanza y necesita el limitador `health`. `HealthChecker::check()` cachea el resultado 10 s; `php artisan health:check --fresh` lo lee en directo. Un fallo del store de caché se traduce en `cache: false` (503), nunca en una excepción 500.
- `ThrottleAnonymousApi` es middleware **global** y corre antes de que el kernel reenlace la petición en `dispatchToRouter()`; en ese punto `$request->user()` todavía devuelve `null` porque la petición no tiene *user resolver*. El limitador `api-anon` debe comprobar `Auth::guard('sanctum')->user()`: con `$request->user()` el tráfico autenticado consume el cupo anónimo (30/min) y `RateLimitTest` lo detecta.
- PDF: `REPORTS_MAX_ROWS=300` corta antes de hidratar y responde `422` (`ReportTooLargeException`); `docker/php/php.ini` fija `memory_limit=512M`. Medido con 3 000 ventas: 259 filas ≈ 128 MB y ~2,5 s, 307 ≈ 147 MB y ~2,8 s, 511 ≈ 228 MB y ~5,8 s, 751 ≈ 350 MB y ~10,7 s, 1 003 ≈ 508 MB y ~18 s, y el dataset completo (3 000) agota los 512 MB. Por eso el límite es 300: deja ~365 MB de margen y ~3 s por reporte.
- `SaleReportService` usa comparaciones sargables (`>= from 00:00`, `< to + 1 día`) para aprovechar el índice `(branch_id, sale_date)`; `whereDate()` compila a `date(col)` y lo anula.
- Los permisos del usuario se cachean `PERMISSIONS_CACHE_TTL` segundos (300 por defecto) para no repetir el join en cada petición.
- `SANCTUM_LAST_USED_AT=false` por defecto: Sanctum deja de escribir `personal_access_tokens.last_used_at` en cada petición autenticada (un UPDATE menos por request). La API no usa esa marca; actívalo solo si necesitas auditar el uso por token.
- La exportación de auditoría usa `cursor()` y escritura directa a `php://output`: no hidrata toda la tabla ni compite con el límite de memoria de los PDF.

### PostgreSQL

- Versión y locale: `postgres:17-alpine` con `POSTGRES_INITDB_ARGS="--locale=C.UTF-8 --encoding=UTF8"`. El cotejo es binario (orden por bytes, sin ICU) pero el encoding UTF8 conserva los acentos; no cambies el locale de un volumen existente sin recrearlo.
- La app nunca conecta como superusuario: `car_agency` es un rol LOGIN sin `SUPERUSER`/`CREATEDB`/`CREATEROLE` y dueño de la base y del schema `public`. Si un volumen antiguo no tiene ese rol, recrea el volumen o ejecuta el SQL de `docker/postgres/init/01-app-role.sh` a mano (el script solo corre con el volumen vacío).
- TLS: `DB_SSLMODE=prefer` en desarrollo (el `postgres` del compose no negocia TLS). Con PostgreSQL gestionado usa `require` o `verify-full`; `config/database.php` mapea `sslmode` y `timezone` (`DB_TIMEZONE=UTC`).
- Ajuste del servidor por variables del compose: `POSTGRES_SHARED_BUFFERS` (~25 % de la RAM), `POSTGRES_EFFECTIVE_CACHE_SIZE` (~75 %), `POSTGRES_WORK_MEM` y `POSTGRES_LOG_MIN_DURATION_STATEMENT` (ms; 1000 por defecto). Las consultas lentas aparecen en `docker compose logs postgres`, no en una tabla.
- Si aparece `remaining connection slots are reserved`, baja `pm.max_children` en `docker/php/www.conf` (cada worker de FPM y cada `worker`/`scheduler` abre su propia conexión) o sube `max_connections` en el servidor. `php artisan health:check` abre y cierra una conexión por ejecución.
- Las migraciones usan `Schema`/`DB` portables; para SQL específico (`lower(...)`, índices parciales, `to_char`) usa `DB::statement` y limita el `sqlite` a un `match` explícito, como en `StatsQuery::monthExpression()`.

## Skills de agentes

- `.agents/` contiene las skills instaladas localmente (hoy, `security-audit`) y está ignorado por Git y por `.dockerignore`: no es código de la aplicación ni se hornea en las imágenes.
- `skills-lock.json` **sí se versiona**. Fija el origen (`cloudflare/security-audit-skill`), la ruta del `SKILL.md` y un hash SHA-256, para que cualquiera reproduzca la misma instalación.
- Restaurar en un clon nuevo: `npx skills install` (alias de `experimental_install`; lee el lockfile y no lo modifica). Actualizar: `npx skills update`, revisando el diff antes de commitear el nuevo hash.
- Las skills son instrucciones que ejecutan agentes: trátalas como una dependencia de desarrollo y revisa cualquier cambio antes de aceptarlo.

## Memoria del proyecto

**Consulta [`MEMORY.md`](MEMORY.md) antes de empezar cualquier tarea** y actualízalo al terminarla: es la fuente única de decisiones técnicas, bugs resueltos y hallazgos no obvios. Cada entrada va fechada (`YYYY-MM-DD`) y el archivo **no puede superar las 100 líneas**; verifica con `wc -l MEMORY.md` antes de añadir contenido y, si se acerca al límite, condensa entradas o archiva las antiguas en `MEMORY-ARCHIVE.md`. Aquí no se copian las lecciones; solo se referencia el archivo y su regla.
