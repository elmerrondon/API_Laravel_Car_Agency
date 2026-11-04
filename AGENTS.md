# AGENTS.md

API Laravel 12 para una agencia de vehículos. La imagen de desarrollo usa PHP 8.3 (el proyecto declara `^8.2`). Casi todo es JSON bajo `/api`; la única vista web es la página de bienvenida y `/up` es el healthcheck.

## Entorno obligatorio: Docker (WSL sin PHP/MySQL local)

Este entorno WSL **no tiene PHP, Composer ni MySQL instalados en el host**. No ejecutes `php`, `composer`, `artisan`, `pest`, `pint` ni `mysql` directamente.

Todo comando dentro del contenedor `app` debe usar `docker compose exec -T` (`-T` desactiva la TTY y evita cuelgues en agentes no interactivos):

```bash
docker compose exec -T app php artisan ...
docker compose exec -T app composer ...
docker compose exec -T app ./vendor/bin/pest
docker compose exec -T app ./vendor/bin/pint
```

Los comandos de orquestación (`docker compose up`, `down`, `ps`, `logs`) se ejecutan en el host porque son de Docker; no sustituyas `app` por un PHP local.

## Infraestructura Docker

- `docker/php/Dockerfile`: multi-stage con targets `base`, `dev` y `prod` sobre `php:8.3-fpm-alpine`. Incluye Composer y las extensiones `pdo_mysql`, `pdo_sqlite`, `bcmath`, `gd`, `zip`, `mbstring`, `exif`, `pcntl`, `opcache` y `dom`.
- `docker-compose.yml`: `app` (target `dev`, FPM 9000 interno), `nginx` (host `8000`) y `mysql` 8.0 (host `3306`, DB `api_laravel_car_agency`, healthcheck).
- `docker-compose.prod.yml`: stack de producción independiente del de desarrollo. `app` construye el target `prod` con `image: car-agency-laravel-api:prod`, `env_file: .env.production`, volumen nombrado `app-storage` en `/var/www/html/storage` y healthcheck por `fsockopen` al puerto 9000; `nginx` se construye desde `docker/nginx/Dockerfile.prod`, publica solo `80:80` y espera a `app` con `service_healthy`; `mysql` 8.0 no expone puertos y persiste en `mysql-data`. No hay Redis: caché y colas usan tablas de MySQL y las sesiones se escriben en `storage/framework/sessions` dentro de `app-storage`. Red propia `car-agency-prod`.
- `docker/nginx/Dockerfile.prod`: build multi-stage. La etapa `app` es `FROM car-agency-laravel-api:prod` y solo aporta `/var/www/html/public`; la imagen final es `nginx:1.27-alpine` con el `default.conf` compartido.
- **Regla de construcción en Producción:** Debido a que `docker/nginx/Dockerfile.prod` utiliza un multi-stage build que hace `COPY --from=car-agency-laravel-api:prod`, es obligatorio construir el servicio `app` individualmente antes de levantar el stack completo (`docker compose -f docker-compose.prod.yml --env-file .env.production build app`). De lo contrario, Docker fallará al no encontrar la imagen origen (`pull access denied`).
- **Dos vías de despliegue que conviven:** la «Vía VPS» (`docker-compose.prod.yml` + `docker/php/Dockerfile` + `docker/nginx/Dockerfile.prod` + `.env.production.example`, multicontenedor con MySQL propio) y la «Vía PaaS» (imagen monolítica para Render/Railway publicada en Docker Hub). Los archivos de la vía PaaS son aditivos: `docker/render/*` y `.github/workflows/deploy-dockerhub.yml`. Al tocar la PaaS no modifiques, renombres ni elimines los de la VPS; la única excepción son los renombrados globales de imágenes acordados con el usuario (2026-11-02: todo pasó a `car-agency-laravel-api`).
- `docker/render/Dockerfile.render`: imagen única sobre `dunglas/frankenphp:1-php8.3-alpine` (Caddy + PHP embebido, sin `php-fpm` ni Nginx aparte). Instala las mismas extensiones con `install-php-extensions`, ejecuta `composer install --no-dev` en una capa propia, `php artisan package:discover` tras copiar el código, corre como `www-data`, hace `EXPOSE 8080` y declara un healthcheck por `fsockopen` al `$PORT`. Reutiliza `docker/php/php.ini` (solo lectura) y no depende de `docker-compose`.
- `docker/render/Caddyfile`: escucha en `:{$PORT:8080}` (Render/Railway inyectan `PORT`), sirve `/var/www/html/public` con `php_server` y manda el access log a `stdout`. Al ser una dirección sin hostname, Caddy **no** activa HTTPS automático: el TLS lo termina la plataforma.
- `docker/render/entrypoint.sh`: crea los directorios de `storage/` y `bootstrap/cache`, aborta si falta `APP_KEY`, ejecuta `php artisan migrate --force` solo con `RUN_MIGRATIONS=true` (añade `--seed` si además defines `RUN_SEED=true`) y `php artisan optimize` salvo `APP_OPTIMIZE=false`; termina con `exec "$@"` para que FrankenPHP reciba las señales del orquestador.
- `.github/workflows/deploy-dockerhub.yml`: en `push` a `main` (y `workflow_dispatch`) hace login con `secrets.DOCKER_USERNAME`/`secrets.DOCKER_TOKEN`, construye solo `linux/amd64` desde `docker/render/Dockerfile.render` y publica las etiquetas `latest` y `sha-<corto>` en `<usuario>/car-agency-laravel-api` usando la caché de GitHub Actions. No ejecuta la suite: los tests siguen siendo locales (`composer test`, `composer analyse`, `pint --test`).
- `.env.production.example`: plantilla versionable sin credenciales reales (`CHANGE_ME_*`), con `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `CACHE_STORE`/`CACHE_DRIVER=database`, `SESSION_DRIVER=file` y `QUEUE_CONNECTION=database`. El `.env.production` real está ignorado por Git y el `.dockerignore` no lo copia a la imagen.
- `docker/nginx/default.conf`: sirve `/var/www/html/public` y pasa PHP a `app:9000`.
- `.dockerignore` excluye `.git`, `vendor`, `node_modules`, `.env`, `storage/*.key`, logs, cachés y artefactos de build.
- El target `dev` crea y usa el usuario local UID/GID 1000. Si tu usuario WSL usa otros IDs, reconstruye con `docker compose build --build-arg UID=$(id -u) --build-arg GID=$(id -g) app`.
- `vendor/` es un volumen nombrado y la etapa `dev` no ejecuta `composer install`. En el primer arranque, o si cambia `composer.lock`, ejecuta `docker compose exec -T app composer install`; reconstruir la imagen no llena el volumen.
- `composer setup` **no** es adecuado dentro de `app`: intenta ejecutar `npm install`/`npm run build` y la imagen PHP no incluye Node. Usa `composer install`; Vite no forma parte del stack Docker actual. `composer dev` tampoco aplica: el stack ya sirve con Nginx + PHP-FPM.
- `.env` es local y está ignorado por Git. `.env.example` ya trae los valores del stack Docker (`APP_URL=http://localhost:8000`, `DB_HOST=mysql`, `DB_DATABASE=api_laravel_car_agency`, `DB_USERNAME=root`, `DB_PASSWORD=secret`, `SESSION_DRIVER=file`); en el primer arranque cópialo con `cp .env.example .env`.
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
docker compose exec -T app ./vendor/bin/pest          # suite completa
docker compose exec -T app ./vendor/bin/pest --filter='nombre'
docker compose exec -T app ./vendor/bin/pest tests/Feature/FooTest.php

# Formato
docker compose exec -T app ./vendor/bin/pint

# Cliente MySQL dentro del contenedor mysql
docker compose exec -T mysql mysql -uroot -psecret api_laravel_car_agency

# Producción (usa su propio compose y su propio env; no mezclar con .env)
cp .env.production.example .env.production
docker compose -f docker-compose.prod.yml --env-file .env.production build app
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
docker compose -f docker-compose.prod.yml --env-file .env.production exec -T app php artisan migrate --force --seed
docker compose -f docker-compose.prod.yml --env-file .env.production exec -T app php artisan optimize
docker compose -f docker-compose.prod.yml --env-file .env.production down

# PaaS (imagen monolítica para Render/Railway; no usa compose ni MySQL propio)
docker build -f docker/render/Dockerfile.render -t car-agency-laravel-api:paas .
docker run --rm -p 8080:8080 \
  -e APP_KEY=base64:... \
  -e DB_CONNECTION=sqlite -e DB_DATABASE=/var/www/html/storage/local.sqlite \
  -e ADMIN_EMAIL=admin@agency.test -e ADMIN_PASSWORD='S3gura-2026' \
  -e RUN_MIGRATIONS=true -e RUN_SEED=true car-agency-laravel-api:paas
curl -i http://localhost:8080/up
# Docker Hub lo publica GitHub Actions (push a main); para probar el push a mano:
docker tag car-agency-laravel-api:paas <usuario>/car-agency-laravel-api:latest
```

El repo no versiona `vendor/`, `.env` ni `node_modules/`. `.env.example` ya está alineado con Docker Compose (`DB_HOST=mysql`, `DB_PASSWORD=secret`, `SESSION_DRIVER=file`, `APP_URL=http://localhost:8000`) y usa MySQL con la base `api_laravel_car_agency`; `phpunit.xml` fuerza SQLite `:memory:` para los tests (por eso la imagen instala `pdo_sqlite`).

## Reglas de Git

- **Commits Automáticos por Tarea:** El agente SÍ debe ejecutar `git add` y `git commit` automáticamente al finalizar cada micro-tarea del `ROADMAP.md`, siempre usando Conventional Commits (`feat:`, `fix:`, `refactor:`, `chore:`, `docs:`) y solo después de verificar que el código funciona en Docker. Nunca incluir el archivo `.env` en los commits.
- **Operaciones Prohibidas:** El agente tiene estrictamente prohibido ejecutar `git push`, `git checkout`, `git branch`, `git merge`, `git rebase` o `git reset`. La gestión de ramas y subidas al remoto la hace únicamente el usuario.
- Usa `git status` y `git diff` para preparar cada commit; revisa que no entren `.env`, `vendor/`, `node_modules/` ni artefactos de build.

## Flujo de trabajo y roadmap

- El plan detallado vive en `ROADMAP.md`, que absorbió por completo el antiguo `Pendientes.txt` (ya eliminado), y es la fuente de verdad del avance.
- Marca cada tarea terminada con `[x]` en `ROADMAP.md`; no crees listas TODO paralelas.
- Antes de empezar una tarea grande, revisa `ROADMAP.md` y actualízalo al terminarla.
- Cada micro-tarea del roadmap equivale a un commit atómico.

## Estructura del código

La app está organizada por dominio de negocio, no por las carpetas estándar de Laravel:

`app/{Enums,DTOs,Models,Services,Http/Controllers,Http/Requests,Http/Resources}/{Cars,Locations,Sales,Users}`; los reportes viven en `app/{Services,Http/Controllers,Http/Requests}/Reports`, las vistas en `resources/views/reports` y los metadatos de OpenAPI en `app/OpenApi`.

Flujo de una petición: `routes/api.php` -> FormRequest en `Http/Requests/...` -> `DTOs/.../*Data::fromRequest()` -> servicio en `app/Services` -> `Http/Resources/...`.

- Mantén los controllers delgados; reglas de negocio, transacciones y eager loading viven en `app/Services`.
- No hay capa repository; los servicios concentran la lógica y el README describe la arquitectura por dominio.
- Los modelos usan subnamespaces de dominio (`App\Models\Cars\Car`, `App\Models\Users\User`), no `App\Models\Car`. Mantén imports y factories alineados al añadir modelos.
- Las rutas API viven solo en `routes/api.php` (Laravel añade el prefijo `/api`). El route-model binding usa los modelos de dominio directamente.

## Convenciones y gotchas

- Los DTOs son clases `readonly` con campos nullable y un `fromRequest(FormRequest)` estático. La mayoría de los servicios filtran nulls antes de crear/actualizar, así que las actualizaciones son parciales: omite un campo (o envía `null`) para dejarlo sin cambios.
- Los endpoints de index paginados aceptan `page` (lo lee el paginador de Laravel, por eso no se valida) y `per_page` (1-100, validado en el FormRequest); el tamaño de página por defecto es 15. Cada servicio pagina con `orderBy('id')` y `withQueryString()`: el orden es determinista y los enlaces de paginación conservan `per_page`. `PaginationTest` lo exige en los 16 listados. `ApiDocumentation` añade `page` a la documentación de todo listado que declare `per_page`, así que si creas un listado nuevo el contrato se completa solo.
- Los Enums son string-backed (`CarStatus`, `SaleStatus`, `RoleEnum`, `PermissionEnum`) y están casteados en los modelos. Valida con `Rule::enum(...)` y compara instancias del enum en los servicios, no strings crudos.
- La creación de ventas es la ruta financiera: `SaleService` envuelve el trabajo en `DB::transaction`, redondea cada importe derivado a 2 decimales, rechaza coches que no estén `available` y marca el coche como `sold`; la cancelación lo devuelve a `available`. Mantén la nueva lógica financiera dentro de transacciones y con 2 decimales.
- Los reportes viven bajo `/api/reports/*` y exigen `view_reports`. `SaleReportService` filtra por `from`/`to` (juntos o ninguno) y `branch_id`, y resume los importes en moneda base; `CarReportService` agrega por SQL por tipo, marca y estado, excluyendo los coches con soft delete. Las vistas Blade comparten `reports/layout.blade.php` y usan DejaVu Sans para los acentos en Dompdf.
- `StoreSaleRequest` exige `sale_date` exactamente como `Y-m-d H:i:s`, un array `taxes` no vacío con IDs de impuestos activos, y `discount_percentage`/`exchange_rate` con como máximo 2 decimales.
- Los Resources exponen relaciones solo mediante `whenLoaded()`. Haz eager loading en el servicio o las claves de relación desaparecen; cada servicio centraliza sus eager loads en constantes y `QueryCountTest` fija el número de consultas de todos los listados, así que un N+1 o un eager load de más rompe la suite. `Model::preventLazyLoading()` está activo fuera de producción (`AppServiceProvider`).
- `ForceJsonResponse` está prepended a todo el grupo de middleware `api` en `bootstrap/app.php`; mantenlo para que los errores de validación y autenticación sigan siendo JSON.
- La autenticación son tokens bearer de Sanctum desde `POST /api/auth/login`, la única ruta pública. Todo el resto del grupo `api` exige `auth:sanctum` y el middleware `permission:<permiso>` (alias registrado en `bootstrap/app.php`), que consulta los roles del usuario mediante `User::hasPermissionTo()`. Esa consulta se cachea `PERMISSIONS_CACHE_TTL` segundos (300 por defecto; `0` desactiva la caché) y `UserService`/`RoleService` invalidan la entrada al cambiar usuarios o roles; si tocas permisos por fuera de esos servicios, llama a `User::flushPermissionCacheForRole()`. Hay 14 permisos sembrados y la matriz rol-permiso vive en `RolePermissionSeeder` (`admin` 14, `manager` 10, `vendedor` 5); los usuarios demo los crea `UserSeeder`. Los `authorize()` de los FormRequest siguen devolviendo `true`: la autorización real es el middleware, no hay policies ni Gates (la única excepción es `viewApiDocs`, que protege la documentación).
- Soft deletes en `User` y `Car`. `Sale` no usa borrado lógico: se cancela con `SaleStatus::CANCELLED`, que además libera el coche.
- Los códigos de sucursal se generan en el servidor a partir de `max(id)` (`SUC-0001`); el payload de la petición no aporta `code`.
- Los datos demo están en español. `CarSeeder` busca nombres exactos (`Negro`, `Blanco`, `Sedán`, `Pick-up`, `Corolla`, `F-150`, etc.); mantén los seeders sincronizados si renombras. El orden de `DatabaseSeeder` importa: roles/permisos -> ubicaciones -> usuarios base -> coches -> catálogos de ventas, y `CarSeeder` necesita los catálogos y sucursales anteriores.
- **Seeders: datos demo vs. fundacionales.** `DatabaseSeeder` corta en seco cuando `app()->environment('production')`: solo llama a `ProductionAdminSeeder`. Ese seeder no usa Faker; siembra roles/permisos (`RoleSeeder`, `PermissionSeeder`, `RolePermissionSeeder`) y crea/actualiza el administrador con `User::updateOrCreate()` usando `config('seeders.admin_*')` (variables `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME`, `ADMIN_LAST_NAME`, `ADMIN_CODE`, `ADMIN_DOCUMENT_NUMBER`). Es idempotente, identifica al usuario por `code` (no por email), restaura el registro si estaba soft-deleted y re-aplica `ADMIN_PASSWORD` en cada ejecución. Los seeders demo (`UserSeeder` y `CarSeeder`, este último vía `CarFactory`/Faker) solo corren en local/testing; nunca añadas `fake()` ni `factory()` a un seeder que pueda ejecutarse con `composer install --no-dev`.
- Los tests usan Pest con `RefreshDatabase` activo para `tests/Feature` (cada test arranca con migraciones limpias y debe sembrar lo que necesite). `UserFactory` vive en `database/factories/Users/UserFactory.php`, usa el modelo `App\Models\Users\User` y genera usuarios con las columnas reales; `User::factory()` funciona.
- No hay CI ni tests JS; la verificación es toda local dentro de Docker: `composer test`, `composer analyse` (PHPStan/Larastan **nivel 5 sin baseline**) y `pint --test`. El análisis necesita `--memory-limit=1G` (el script `composer analyse` ya lo pasa); PHPStan 2 rechaza `memoryLimit` en `phpstan.neon`.
- Todo el código de `app/`, `routes/`, `database/` y `config/` declara `strict_types=1`; los únicos archivos sin él son los generados en `bootstrap/cache/`. Los límites de PHP obligan a castear en el borde (`Controller::perPage()`, `(int)`/`(string)` sobre `validated()`), no dentro de los servicios.

### Unicidad e índices

- `cars.vin` y `users.code` son únicos a nivel global: no incluyen `deleted_at` y no se pueden reutilizar tras un soft delete. Es una decisión de trazabilidad y auditoría, no un descuido; no los conviertas en índices compuestos.
- `users.email` y `users.document_number` tienen índices únicos compuestos `(columna, deleted_at)` para permitir reutilizar el valor cuando el usuario está borrado lógicamente. En MySQL ese índice **no protege la unicidad entre filas activas**, porque los `NULL` de `deleted_at` son distintos entre sí: dos usuarios activos podrían compartir email o documento a nivel de BD. La unicidad real la impone el FormRequest con `Rule::unique(...)->withoutTrashed()`; si añades una ruta que escriba usuarios sin pasar por ese FormRequest, replica la validación.
- Antes de crear un índice, revisa `SHOW INDEX FROM tabla`: `foreignId()->constrained()` ya crea el índice de la clave foránea (`cars.branch_id`, `cars.car_model_id`, `sales.user_id`), así que añadirlo otra vez solo duplica estructura.
- `cars` ya no tiene índices compuestos con `deleted_at`: el antiguo índice llamado `deleted_at` era un duplicado de `cars_vin_unique` sobre `vin` y se eliminó en la fase 1.

### Alcance multi-sucursal

- **No hay scoping por sucursal, y es intencional.** Cualquier usuario con el permiso de lectura ve los datos de las 8 sucursales; `branch_id` es un filtro opcional en reportes, nunca una restricción de autorización. Se revisó BOLA/OWASP API1 y se descartó porque el negocio (una agencia con administración centralizada) necesita visión global.
- No añadas filtros automáticos por la sucursal del usuario ni un middleware de scope sin decisión explícita del usuario: rompería reportes y listados del `manager`. Si algún día hace falta aislamiento, va en la capa de servicio con una constante de scopes revisada, no en el middleware `permission`.

### Documentación OpenAPI

- Scramble 0.13 documenta rutas, `FormRequest` y `Resource`; `app/OpenApi/ApiDocumentation.php` añade lo que no puede inferir: grupos en español, resúmenes, `page`/`per_page`, filtros de reportes y errores `403`/`409`/`429`.
- El esquema `bearerAuth` se declara en `app/OpenApi/BearerAuthSecurityStrategy.php` y `config/scramble.php` solo referencia la clase con un array de opciones. No devuelvas objetos `SecurityScheme` desde la configuración: `php artisan config:cache` no los puede serializar y `php artisan optimize` falla con `value at "scramble.security_strategy.1.scheme" is non-serializable`.
- El transformador se registra en `AppServiceProvider::boot()` dentro de `$this->app->booted()` a propósito: Scramble es un provider de paquete y su `boot()` corre después del de la app, así que si lo registras directamente en `boot()` sus extensiones se ejecutan después y pisan tags/resúmenes.
- `/docs/api` y `/docs/api.json` viven **fuera** de `/api` y los protege el Gate `viewApiDocs`: abiertos en `local`/`testing`, en el resto solo con `DOCS_ENABLED=true`. `composer docs:export` escribe `storage/app/openapi.json` (ignorado por Git).
- Los PDF se documentan con `#[Response(200, mediaType: 'application/pdf', format: 'binary')]`; el transformador elimina el `application/json` que Scramble infiere de `download()`.
- El documento declara la licencia MIT desde `ApiDocumentation::document()` (Scramble no la expone por configuración) y los dos FormRequest de ubicaciones leen el modelo con `$this->route('state'|'city')`: Scramble no evalúa propiedades dinámicas como `$this->state` y avisaba de reglas incompletas en `scramble:export`.
- El 403 solo se añade a rutas con middleware `permission:*`; el 409 a `PATCH /sales/{sale}/cancel` y a los `DELETE` con FK (no a `cars`/`users`, que usan soft delete); el 429 a todas.
- Los parámetros de ruta van en minúscula (`Tax $tax`, no `Tax $Tax`): una mayúscula genera `/taxes/{Tax}` y la validación OpenAPI lo marca como path ambiguo/duplicado frente a `/taxes/{tax}`.
- Si ejecutas `php artisan scramble:cache`, el documento queda congelado; usa `scramble:clear` después de cambiar rutas o validaciones.
- Validación del contrato (en el host, la imagen PHP no trae Node): `npx @redocly/cli lint storage/app/openapi.json` y `npx openapi-to-postmanv2 -s storage/app/openapi.json -o /tmp/postman.json`.

### Límites y rendimiento

- Rate limits en `config/rate_limits.php`: `login` 5/min (email+IP), `api` 120/min y `reports` 30/min (usuario, o IP sin token). Ajustables con `RATE_LIMIT_LOGIN`, `RATE_LIMIT_API` y `RATE_LIMIT_REPORTS`.
- PDF: `REPORTS_MAX_ROWS=500` corta antes de hidratar y responde `422` (`ReportTooLargeException`); `docker/php/php.ini` fija `memory_limit=512M`. 500 filas consumen ~216 MB y ~8 s; 1 000 filas ~508 MB, así que sube ambas cosas a la vez.
- `SaleReportService` usa comparaciones sargables (`>= from 00:00`, `< to + 1 día`) para aprovechar el índice `(branch_id, sale_date)`; `whereDate()` compila a `date(col)` y lo anula.
- Los permisos del usuario se cachean `PERMISSIONS_CACHE_TTL` segundos (300 por defecto) para no repetir el join en cada petición.

## Memoria y Lecciones Aprendidas

Las decisiones técnicas, bugs resueltos y hallazgos no obvios viven en [`Memory.md`](Memory.md), que es la fuente única de memoria del proyecto. Debe actualizarse al terminar cada tarea relevante con una entrada fechada y **no puede superar las 100 líneas**: verifica con `wc -l Memory.md` antes de añadir contenido y, si se acerca al límite, condensa o archiva las entradas antiguas en `Memory-archive.md`. No dupliques lecciones en este archivo; aquí solo va la referencia y la regla.
