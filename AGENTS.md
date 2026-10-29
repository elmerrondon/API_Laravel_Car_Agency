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
- Los endpoints de index paginados aceptan `page` (lo lee el paginador de Laravel, por eso no se valida) y `per_page` (1-100, validado en el FormRequest); el tamaño de página por defecto es 15. `ApiDocumentation` añade `page` a la documentación de todo listado que declare `per_page`, así que si creas un listado nuevo el contrato se completa solo.
- Los Enums son string-backed (`CarStatus`, `SaleStatus`, `RoleEnum`, `PermissionEnum`) y están casteados en los modelos. Valida con `Rule::enum(...)` y compara instancias del enum en los servicios, no strings crudos.
- La creación de ventas es la ruta financiera: `SaleService` envuelve el trabajo en `DB::transaction`, redondea cada importe derivado a 2 decimales, rechaza coches que no estén `available` y marca el coche como `sold`; la cancelación lo devuelve a `available`. Mantén la nueva lógica financiera dentro de transacciones y con 2 decimales.
- Los reportes viven bajo `/api/reports/*` y exigen `view_reports`. `SaleReportService` filtra por `from`/`to` (juntos o ninguno) y `branch_id`, y resume los importes en moneda base; `CarReportService` agrega por SQL por tipo, marca y estado, excluyendo los coches con soft delete. Las vistas Blade comparten `reports/layout.blade.php` y usan DejaVu Sans para los acentos en Dompdf.
- `StoreSaleRequest` exige `sale_date` exactamente como `Y-m-d H:i:s`, un array `taxes` no vacío con IDs de impuestos activos, y `discount_percentage`/`exchange_rate` con como máximo 2 decimales.
- Los Resources exponen relaciones solo mediante `whenLoaded()`. Haz eager loading en el servicio o las claves de relación desaparecen; cada servicio centraliza sus eager loads en constantes y `QueryCountTest` fija el número de consultas de todos los listados, así que un N+1 o un eager load de más rompe la suite. `Model::preventLazyLoading()` está activo fuera de producción (`AppServiceProvider`).
- `ForceJsonResponse` está prepended a todo el grupo de middleware `api` en `bootstrap/app.php`; mantenlo para que los errores de validación y autenticación sigan siendo JSON.
- La autenticación son tokens bearer de Sanctum desde `POST /api/auth/login`, la única ruta pública. Todo el resto del grupo `api` exige `auth:sanctum` y el middleware `permission:<permiso>` (alias registrado en `bootstrap/app.php`), que consulta los roles del usuario mediante `User::hasPermissionTo()`. Esa consulta se cachea `PERMISSIONS_CACHE_TTL` segundos (300 por defecto; `0` desactiva la caché) y `UserService`/`RoleService` invalidan la entrada al cambiar usuarios o roles; si tocas permisos por fuera de esos servicios, llama a `User::flushPermissionCacheForRole()`. Hay 13 permisos sembrados y la matriz rol-permiso vive en `RolePermissionSeeder` (`admin` 13, `manager` 10, `vendedor` 5); los usuarios demo los crea `UserSeeder`. Los `authorize()` de los FormRequest siguen devolviendo `true`: la autorización real es el middleware, no hay policies ni Gates (la única excepción es `viewApiDocs`, que protege la documentación).
- Soft deletes en `User` y `Car`. `Sale` no usa borrado lógico: se cancela con `SaleStatus::CANCELLED`, que además libera el coche.
- Los códigos de sucursal se generan en el servidor a partir de `max(id)` (`SUC-0001`); el payload de la petición no aporta `code`.
- Los datos demo están en español. `CarSeeder` busca nombres exactos (`Negro`, `Blanco`, `Sedán`, `Pick-up`, `Corolla`, `F-150`, etc.); mantén los seeders sincronizados si renombras. El orden de `DatabaseSeeder` importa: roles/permisos -> ubicaciones -> usuarios base -> coches -> catálogos de ventas, y `CarSeeder` necesita los catálogos y sucursales anteriores.
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
- El transformador se registra en `AppServiceProvider::boot()` dentro de `$this->app->booted()` a propósito: Scramble es un provider de paquete y su `boot()` corre después del de la app, así que si lo registras directamente en `boot()` sus extensiones se ejecutan después y pisan tags/resúmenes.
- `/docs/api` y `/docs/api.json` viven **fuera** de `/api` y los protege el Gate `viewApiDocs`: abiertos en `local`/`testing`, en el resto solo con `DOCS_ENABLED=true`. `composer docs:export` escribe `storage/app/openapi.json` (ignorado por Git).
- Los PDF se documentan con `#[Response(200, mediaType: 'application/pdf', format: 'binary')]`; el transformador elimina el `application/json` que Scramble infiere de `download()`.
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

Registra aquí decisiones técnicas, bugs resueltos y hallazgos no obvios. Añade una entrada fechada al terminar una tarea relevante; no borres entradas anteriores.

- 2026-10-20 — Toolchain estandarizado en Docker (PHP 8.3-FPM + Nginx + MySQL 8.0) porque el host WSL no tiene PHP, Composer ni MySQL. Los tests siguen usando SQLite `:memory:`.
- 2026-10-20 — `composer setup` no se usa dentro de `app` porque incluye npm/Vite y la imagen PHP no trae Node.
- 2026-10-20 — Usar siempre `docker compose exec -T` en automatizaciones; sin `-T`, los comandos interactivos pueden colgarse.
- 2026-10-20 — `ROADMAP.md` absorbió todo `Pendientes.txt` y se eliminó el archivo original; el roadmap es la única lista de trabajo.
- 2026-10-20 — Plan aprobado: `Sale` sin `SoftDeletes` (estado `cancelled`), solo `POST /api/auth/login` público, RBAC de 13 permisos, `vin`/`users.code` con unicidad global y reportes PDF con `barryvdh/laravel-dompdf:^3.1`.
- 2026-10-20 — `ForceJsonResponse` solo corre en rutas registradas, así que un 404 de ruta `/api/*` inexistente salía en HTML. Se añadió `shouldRenderJsonWhen` en `bootstrap/app.php` para que todo `/api/*` responda JSON.
- 2026-10-20 — `RefreshDatabase` está activo para `tests/Feature`; todo test de Feature arranca con migraciones limpias y debe sembrar lo que necesite.
- 2026-10-20 — `sales.deleted_at` eliminado: las ventas no se borran, se cancelan con `SaleStatus::CANCELLED`. Los índices de FK ya cubren `cars.branch_id`, `cars.car_model_id` y `sales.user_id`, y el índice `deleted_at` de `cars` era un duplicado de `cars_vin_unique`.
- 2026-10-20 — Los índices `(email, deleted_at)` y `(document_number, deleted_at)` no garantizan unicidad entre usuarios activos en MySQL (los `NULL` son distintos); la unicidad efectiva depende de `Rule::unique(...)->withoutTrashed()` en los FormRequest.
- 2026-10-21 — En Docker, `env_file` inyecta el `.env` en `$_SERVER`; PHPUnit con `force="true"` solo escribe `$_ENV` y Laravel prefiere `$_SERVER`, así que `RefreshDatabase` corría contra MySQL y borraba la base de desarrollo. `tests/TestCase.php` copia las claves de `phpunit.xml` de `$_ENV` a `$_SERVER` antes de arrancar la app; la suite ahora usa SQLite `:memory:` y tarda menos de 1s.
- 2026-10-21 — `CarResource` usa `whenLoaded('car_model')`/`whenLoaded('car_type')` con snake_case, pero las relaciones son `carModel`/`carType`, así que esas claves nunca se serializan. El eager loading ya está hecho; la corrección de los Resources queda en la tarea 2.5.
- 2026-10-21 — Tarea 2.5 completada: `CarResource` ya usa `carModel`/`carType`, cada servicio centraliza sus eager loads en constantes y las altas/ediciones devuelven las relaciones cargadas. `QueryCountTest` (2.6) fija el número de consultas de `/api/cars` y `/api/sales` para que no crezca con las filas.
- 2026-10-21 — Fase 3 (transacciones): `SaleService` bloquea con `lockForUpdate` el coche al crear la venta y tanto la venta como el coche al cancelarla, así dos peticiones concurrentes no venden dos veces el mismo coche ni cancelan dos veces la misma venta. `UserService` ya envolvía usuario + roles + sucursales en `DB::transaction`; `CarService` solo escribe una tabla, así que no necesita transacción. Las excepciones de dominio de ventas (`app/Exceptions/Sales`) se renderizan como JSON desde `bootstrap/app.php`: `CarNotAvailableException` → 422 y `SaleAlreadyCancelledException`/`SaleNotCancellableException` → 409; el `SaleController` ya no captura `LogicException`.
- 2026-10-21 — Fase 4 (RBAC): 13 permisos; `RolePermissionSeeder::matrix()` es la fuente única de la matriz (`admin` 13, `manager` 10, `vendedor` 5) y `UserSeeder` crea los usuarios demo con `SEED_USER_PASSWORD` (fallback `password`). Cada usuario semilla queda con un rol y la primera sucursal activa. `User::hasPermissionTo()` consulta roles/permisos con un `whereHas` por petición (sin cachear relaciones) y el alias `permission` vive en `bootstrap/app.php`; solo `POST /api/auth/login` es público. Los tests autentican con el helper `actingAsRole()` de `tests/Pest.php`.
- 2026-10-21 — Fase 5 (reportes PDF): `barryvdh/laravel-dompdf` ^3.1 (v3.1.2) se instala en el volumen `vendor/` con `composer require`. `GET /api/reports/sales/pdf`, `GET /api/reports/cars` y `GET /api/reports/cars/pdf` exigen `view_reports`; las ventas se filtran por `from`/`to` (juntos o ninguno) y `branch_id`, y sus totales se expresan en moneda base. `CarReportService` agrega por SQL (tipo, marca y estado) y excluye soft deletes; los reportes comparten `resources/views/reports/layout.blade.php`. Los tests validan 401/403, `application/pdf`, `%PDF` y el contenido renderizado de las vistas.
- 2026-10-21 — Verificación final (fase 6): `migrate:fresh --seed` OK (50 coches y 3 usuarios demo), `pest` 48 tests / 215 aserciones y `pint` limpio en 233 archivos. Smoke test HTTP real: login de `admin@caragency.test`, `GET /api/reports/cars` responde 50 coches y las dos variantes PDF devuelven `%PDF-1.7` con `Content-Type: application/pdf`; sin token la API responde 401.
- 2026-10-28 — Endurecimiento de auth y validaciones: el login exige `is_active`, el limiter `login` permite 5 intentos/minuto por email+IP (`429` al sexto), cambiar contraseña/desactivar/eliminar revoca tokens, los FormRequest alinean `document_number`/`code` a 10, la regla `unique` del VIN incluye los coches con soft delete, `year` se limita a 1900-2100 y la descripción de sucursal a 300. Las violaciones de FK se renderizan como `409` y borrar un rol con usuarios también devuelve `409` (`RoleInUseException`). El seed conserva 8 sucursales (Ventura Plaza al final, `SUC-0008`) y 52 coches (50 factory + 2 manuales, IDs 51-52), con tests nuevos y la suite en 63 tests / 260 aserciones.
- 2026-10-28 — README reescrito como guía de onboarding Docker: copiar `.env.example`, levantar el stack, crear/verificar la base, migrar/sembrar, mapear rutas con permisos, ejemplos `curl`, tests, comandos y troubleshooting. `.env.example` se alineó con Compose (`APP_URL=http://localhost:8000`, `DB_HOST=mysql`, `DB_PASSWORD=secret`, `SESSION_DRIVER=file`) para que `cp .env.example .env` funcione sin ajustes manuales.
- 2026-10-28 — Iteración 2, calidad (fases 7-10): PHPStan/Larastan pasó de 180 errores a 0 en **nivel 5 sin baseline**; `parseModelCastsMethod: true` eliminó 60 falsos positivos de atributos casteados y los Resources declaran `/** @mixin Model */`. `strict_types=1` en todo el código de la app obligó a castear en el borde HTTP (`Controller::perPage()` y `(int)`/`(string)` sobre `validated()`) y a un trait `NormalizesRequestValues` para los DTOs, porque `validated()` devuelve `mixed`. Las pruebas de arquitectura se partieron en dos: reglas `arch()` agregadas por namespace y un test de reflexión que exige que cada controller delegue en un servicio.
- 2026-10-28 — Iteración 2, rendimiento (fase 11): `Model::preventLazyLoading(! app()->isProduction())` no encontró N+1 pendientes porque la fase 2.5 ya centralizó los eager loads; `QueryCountTest` cubre ya 17 casos con `assertConstantQueriesFor()`. Los permisos se cachean 300 s (`config/permissions.php`) con invalidación explícita desde `UserService` y `RoleService`. Los reportes usan predicados sargables y el índice `(branch_id, sale_date)`, y se cortan en `REPORTS_MAX_ROWS` (500) con `memory_limit=512M` en la imagen. Ese límite necesita ~216 MB y ~8 s; 1 000 filas necesitan ~508 MB.
- 2026-10-28 — Iteración 2, documentación (fase 12): Scramble 0.13.52 con OpenAPI 3.1.2 y servidor relativo `/api`; `app/OpenApi/ApiDocumentation.php` se registra en `$this->app->booted()` (no en `boot()`) porque el `boot()` de Scramble corre después y re-registra sus extensiones. La validación con `@redocly/cli` destapó que `TaxController` declaraba `Tax $Tax` y generaba `/taxes/{Tax}` además de `/taxes/{tax}`; los parámetros de ruta van en minúscula. El documento exportado (41 paths, 87 operaciones) se importa en Postman con `openapi-to-postmanv2`, y `OpenApiDocumentTest` fija paths, grupos, errores, parámetros y `$ref`. Los dos warnings de `scramble:export` (`UpdateStateRequest::$state`, `UpdateCityRequest::$city`) son limitaciones del análisis estático y no afectan al contrato.
