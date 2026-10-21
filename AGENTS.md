# AGENTS.md

API Laravel 12 para una agencia de vehículos. La imagen de desarrollo usa PHP 8.3 (el proyecto declara `^8.2`). Casi todo es JSON bajo `/api`; la única ruta web es la página de bienvenida.

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
- `.env` es local y está ignorado por Git. Valores de desarrollo: `DB_HOST=mysql`, `DB_DATABASE=api_laravel_car_agency`, `DB_USERNAME=root`, `DB_PASSWORD=secret`, `SESSION_DRIVER=file`.
- No existe migración de la tabla `sessions`: con `SESSION_DRIVER=database` el login puede fallar. El `.env` local usa `file`; si se cambia a `database`, primero hay que crear esa migración.

## Comandos

```bash
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

El repo no versiona `vendor/`, `.env` ni `node_modules/`. `.env.example` usa MySQL con la base `api_laravel_car_agency`; `phpunit.xml` fuerza SQLite `:memory:` para los tests (por eso la imagen instala `pdo_sqlite`).

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

`app/{Enums,DTOs,Models,Services,Http/Controllers,Http/Requests,Http/Resources}/{Cars,Locations,Sales,Users}`

Flujo de una petición: `routes/api.php` -> FormRequest en `Http/Requests/...` -> `DTOs/.../*Data::fromRequest()` -> servicio en `app/Services` -> `Http/Resources/...`.

- Mantén los controllers delgados; reglas de negocio, transacciones y eager loading viven en `app/Services`.
- No hay capa repository, aunque el README mencione "Service-Repository".
- Los modelos usan subnamespaces de dominio (`App\Models\Cars\Car`, `App\Models\Users\User`), no `App\Models\Car`. Mantén imports y factories alineados al añadir modelos.
- Las rutas API viven solo en `routes/api.php` (Laravel añade el prefijo `/api`). El route-model binding usa los modelos de dominio directamente.

## Convenciones y gotchas

- Los DTOs son clases `readonly` con campos nullable y un `fromRequest(FormRequest)` estático. La mayoría de los servicios filtran nulls antes de crear/actualizar, así que las actualizaciones son parciales: omite un campo (o envía `null`) para dejarlo sin cambios.
- Los endpoints de index paginados aceptan `per_page` (1-100) y lo pasan a `paginate()`; el tamaño de página por defecto es 15.
- Los Enums son string-backed (`CarStatus`, `SaleStatus`, `RoleEnum`, `PermissionEnum`) y están casteados en los modelos. Valida con `Rule::enum(...)` y compara instancias del enum en los servicios, no strings crudos.
- La creación de ventas es la ruta financiera: `SaleService` envuelve el trabajo en `DB::transaction`, redondea cada importe derivado a 2 decimales, rechaza coches que no estén `available` y marca el coche como `sold`; la cancelación lo devuelve a `available`. Mantén la nueva lógica financiera dentro de transacciones y con 2 decimales.
- `StoreSaleRequest` exige `sale_date` exactamente como `Y-m-d H:i:s`, un array `taxes` no vacío con IDs de impuestos activos, y `discount_percentage`/`exchange_rate` con como máximo 2 decimales.
- Los Resources exponen relaciones solo mediante `whenLoaded()`. Haz eager loading en el servicio o las claves de relación desaparecen; `SaleService` lo hace, pero muchos servicios de catálogos no (la limpieza de N+1 está en la fase 2 de `ROADMAP.md`).
- `ForceJsonResponse` está prepended a todo el grupo de middleware `api` en `bootstrap/app.php`; mantenlo para que los errores de validación y autenticación sigan siendo JSON.
- La autenticación son tokens bearer de Sanctum desde `POST /api/auth/login`. Solo logout/refresh usan `auth:sanctum`; el resto de rutas son públicas y todos los `authorize()` de los FormRequest devuelven `true`. Los roles/permisos se siembran pero no se aplican, no hay filas pivote rol-permiso sembradas y no hay seeder de usuarios: no asumas que existen policies, Gates ni middleware RBAC.
- Soft deletes en `User` y `Car`. `Sale` no usa borrado lógico: se cancela con `SaleStatus::CANCELLED`, que además libera el coche.
- Los códigos de sucursal se generan en el servidor a partir de `max(id)` (`SUC-0001`); el payload de la petición no aporta `code`.
- Los datos demo están en español. `CarSeeder` busca nombres exactos (`Negro`, `Blanco`, `Sedán`, `Pick-up`, `Corolla`, `F-150`, etc.); mantén los seeders sincronizados si renombras. El orden de `DatabaseSeeder` importa: roles/permisos -> ubicaciones -> coches -> catálogos de ventas, y `CarSeeder` necesita los catálogos y sucursales anteriores.
- Los tests usan Pest con `RefreshDatabase` activo para `tests/Feature` (cada test arranca con migraciones limpias y debe sembrar lo que necesite). `UserFactory` sigue roto (importa `App\Models\User`, que no existe, y sus campos no coinciden con la migración `users`), y `User::factory()` resuelve a `Database\Factories\Users\UserFactory`, que tampoco existe; crea usuarios manualmente o corrige la factory (fase 4.5).
- No hay workflows de CI, PHPStan ni tests JS configurados; la verificación disponible es `composer test` + `pint` dentro de Docker.

### Unicidad e índices

- `cars.vin` y `users.code` son únicos a nivel global: no incluyen `deleted_at` y no se pueden reutilizar tras un soft delete. Es una decisión de trazabilidad y auditoría, no un descuido; no los conviertas en índices compuestos.
- `users.email` y `users.document_number` tienen índices únicos compuestos `(columna, deleted_at)` para permitir reutilizar el valor cuando el usuario está borrado lógicamente. En MySQL ese índice **no protege la unicidad entre filas activas**, porque los `NULL` de `deleted_at` son distintos entre sí: dos usuarios activos podrían compartir email o documento a nivel de BD. La unicidad real la impone el FormRequest con `Rule::unique(...)->withoutTrashed()`; si añades una ruta que escriba usuarios sin pasar por ese FormRequest, replica la validación.
- Antes de crear un índice, revisa `SHOW INDEX FROM tabla`: `foreignId()->constrained()` ya crea el índice de la clave foránea (`cars.branch_id`, `cars.car_model_id`, `sales.user_id`), así que añadirlo otra vez solo duplica estructura.
- `cars` ya no tiene índices compuestos con `deleted_at`: el antiguo índice llamado `deleted_at` era un duplicado de `cars_vin_unique` sobre `vin` y se eliminó en la fase 1.

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
