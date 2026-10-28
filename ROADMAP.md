# ROADMAP

Fuente de verdad del avance del proyecto. Cada micro-tarea equivale a un commit atómico; márcala con `[x]` al terminarla.

El contenido de `Pendientes.txt` quedó absorbido y verificado en este documento, por lo que ese archivo se eliminó.

La Iteración 1 (fases 0 a 6) está completa y se conserva al final como historial. La Iteración 2 arranca en la fase 7 con el plan de QA, seguridad, rendimiento y documentación OpenAPI.

## Decisiones de la Iteración 2

1. **PHPStan nivel 5 sin baseline.** Se corrigen los 184 errores reales hasta llegar a 0 (fase 8). El nivel 6 se evaluará más adelante solo si el nivel 5 queda estable.
2. **Alcance multi-sucursal sin scoping (decisión de negocio intencional).** Un usuario con permiso consulta coches y ventas de todas las sucursales para ver stock y operaciones entre sedes. No se añade filtro por sucursal ni control de acceso por objeto; se documenta en `AGENTS.md`.
3. **Documentación OpenAPI por entorno.** Habilitada por defecto en `local` y `testing`; en producción queda cerrada salvo que `DOCS_ENABLED=true`. La compuerta es el Gate `viewApiDocs` que ya consume `RestrictedDocsAccess`.
4. **Sin `pest-plugin-mutate` ni drivers de cobertura.** No se toca el `Dockerfile` para instalar Xdebug/PCOV; la calidad se mide con PHPStan, Pint y la suite funcional.
5. **Compatibilidad Postman (resuelto sin degradar el paquete).** Scramble 0.13.x solo emite 3.1/3.2 (no existe `V3_0` en su enum) y la rama 0.12 también emite `3.1.0`, así que degradar no da acceso a OpenAPI 3.0. Se fija `V3_1` (documento `3.1.2`), versión que Postman importa de forma oficial, con un servidor relativo `/api` resuelto por `APP_URL` y export local con `composer docs:export` a `storage/app/openapi.json`.

## Fase 7 — Dependencias, configuración y endurecimiento base

- [x] `7.1` Consolidar `dedoc/scramble:^0.13.52` y `larastan/larastan:^3.13` en `composer.json`/`composer.lock` y verificar que el volumen `vendor/` los tiene instalados.
- [x] `7.2` Actualizar las dependencias con avisos de `composer audit` (guzzle, laravel/framework, league/*, symfony/*) hasta dejar la auditoría en 0.
- [x] `7.3` Crear `phpstan.neon` en nivel 5 (sin baseline) con memoria suficiente para el contenedor y el script `composer analyse`. Baseline al configurarlo (2026-10-28): **180 errores** que la fase 8 debe llevar a 0.
- [x] `7.4` Crear `pint.json` (preset `laravel`) y comprobar que `pint --test` sigue limpio (241 archivos).
- [x] `7.5` Publicar `config/scramble.php`: versión OpenAPI explícita (3.1.2, único soporte de Scramble 0.13.x), info del API, UI, `security_strategy` Bearer (`bearerAuth`) de Sanctum y dev tools por entorno.
- [x] `7.6` Crear `config/docs.php` + Gate `viewApiDocs` (`local`/`testing` o `DOCS_ENABLED=true`) y test de acceso 200/403.
- [x] `7.7` Mover la expiración de Sanctum a `SANCTUM_EXPIRATION` y completar `.env.example` (`API_VERSION`, `DOCS_ENABLED`, `SCRAMBLE_DEV_TOOLS`, `CORS_ALLOWED_ORIGINS`, `SANCTUM_EXPIRATION`).
- [x] `7.8` Definir limitadores `api` (120/min) y `reports` (30/min) con límites configurables (`config/rate_limits.php`), aplicarlos en `routes/api.php` y testear el 429.
- [x] `7.9` Publicar `config/cors.php` con orígenes desde `CORS_ALLOWED_ORIGINS` y test de cabeceras CORS.
- [x] `7.10` Validación de cierre de la fase 7 en Docker: `pest` **74 tests / 296 aserciones**, `pint --test` limpio (**249 archivos**), `composer audit` sin avisos y `phpstan` nivel 5 con la línea base de **180 errores** que asume la fase 8.

## Fase 8 — PHPStan nivel 5 hasta 0 errores

- [x] `8.1` Modelos: `casts()` protegido, tipos de retorno en relaciones y `parseModelCastsMethod` en Larastan (180 → 107 errores; desaparecen los 60 `relationExistence`).
- [x] `8.2` Resources: anotar `@mixin` con el modelo subyacente en los 17 Resources (107 → 12 errores).
- [x] `8.3` Servicios: `str_pad()` casteado en `BranchService`, `CarReportService` tipado con `Collection<int, Car>` + `getAttribute()` en los agregados, y `SaleData::$discountPercentage` sin `??` (12 → 0 errores).
- [ ] `8.4` Controllers, Requests, DTOs, Enums, Seeders y Middleware: cerrar los errores restantes hasta `[OK] No errors`.

## Fase 9 — Tipado estricto y arquitectura

- [ ] `9.1` Añadir `declare(strict_types=1)` a `app/Enums`, `app/DTOs` y `app/Models` y ajustar lo que rompa.
- [ ] `9.2` Añadir `declare(strict_types=1)` a `app/Services`, `app/Exceptions` y `app/Providers`.
- [ ] `9.3` Añadir `declare(strict_types=1)` a `app/Http` (Controllers, Requests, Resources, Middleware) y `bootstrap/app.php`.
- [ ] `9.4` Crear `tests/Feature/ArchitectureTest.php` con `arch()`: `strict_types`, capas (Controller → Service → Model), sin `dd`/`dump`/`var_dump`/`env()` fuera de `config`, DTOs `readonly`, controllers delgados y Resources sin queries.
- [ ] `9.5` Activar la regla `declare_strict_types` en `pint.json` y dejar `pint` + `phpstan` + `pest` en verde.

## Fase 10 — Cobertura de tests faltante

- [ ] `10.1` Test de `POST /api/auth/logout`: 200, token revocado (siguiente petición 401) y logout sin token 401.
- [ ] `10.2` Test de `POST /api/auth/refresh`: 200 con token nuevo, token anterior revocado y expiración renovada.
- [ ] `10.3` Test de expiración de token (120 min configurables) con `Carbon::setTestNow`.
- [ ] `10.4` Test de filtros de reportes: `from`/`to` juntos o ninguno, `branch_id`, totales en moneda base y 422 por fechas inválidas.
- [ ] `10.5` Tests de creación de venta: impuestos, descuento, tipo de cambio, redondeo a 2 decimales, coche no disponible (422) y `sale_date` inválido.
- [ ] `10.6` Tests de catálogos y ubicaciones: CRUD completo, `manage_*` vs `view_*` (403) y `per_page` fuera de rango.
- [ ] `10.7` Tests de usuarios: alta/edición/baja, email y documento duplicados, revocación de tokens al desactivar/eliminar.
- [ ] `10.8` Tests de errores transversales: 404 JSON, 405, 429 de login y de API, y `permission` inexistente.

## Fase 11 — Rendimiento y consultas

- [ ] `11.1` Activar `Model::preventLazyLoading()` fuera de producción y corregir los N+1 que destape.
- [ ] `11.2` Cachear los permisos de `User::hasPermissionTo()` con TTL corto e invalidar al cambiar roles o permisos.
- [ ] `11.3` Revisar `EXPLAIN` de los listados principales y añadir solo los índices que falten (evitando duplicados de FK).
- [ ] `11.4` Ampliar `QueryCountTest` a catálogos, ubicaciones, usuarios y reportes.
- [ ] `11.5` Medir el coste de los reportes PDF y documentar el límite de filas o la paginación aplicada.

## Fase 12 — OpenAPI y Postman

- [ ] `12.1` Documentar las respuestas de error 401/403/404/409/422/429 en las rutas o controladores (`#[Response]`/extensiones).
- [ ] `12.2` Documentar los PDF como `application/pdf` binario en lugar de JSON.
- [ ] `12.3` Documentar explícitamente paginación (`page`, `per_page`) y filtros de reportes (`from`, `to`, `branch_id`).
- [ ] `12.4` Añadir tags y summaries en español por dominio (coches, catálogos, ubicaciones, ventas, reportes, usuarios, auth).
- [ ] `12.5` Exportar el documento con `composer docs:export` (`storage/app/openapi.json`) y validar la importación en Postman (OpenAPI 3.1.2 está soportado).
- [ ] `12.6` Test del documento OpenAPI: 200 en `testing`, `securitySchemes` Bearer, paths esperados, PDFs binarios y 403 en producción sin `DOCS_ENABLED`.

## Fase 13 — Cierre y documentación

- [ ] `13.1` README: sección de OpenAPI/Postman, comandos de calidad (`analyse`, `pint`, `test`) y rate limits.
- [ ] `13.2` `AGENTS.md`: decisión multi-sucursal, política de docs, límites, PHPStan nivel 5 y lecciones de la iteración.
- [ ] `13.3` Verificación final: `composer audit`, `pest`, `phpstan`, `pint` y `migrate:fresh --seed` con resultados anotados aquí.
- [ ] `13.4` Smoke test HTTP real: login, `/docs/api`, `/docs/api.json`, reportes PDF y 429.

## Historial completado (Iteración 1)

### Decisiones acordadas

- `Sale` deja de usar borrado lógico: se maneja con `SaleStatus` (`pending` → `completed` → `cancelled`). Cancelar devuelve el coche a `available`.
- Solo `POST /api/auth/login` es público; el resto de rutas exige `auth:sanctum` + permiso.
- RBAC granular con 13 permisos: `admin` completo, `manager` operativo, `vendedor` comercial.
- Usuarios semilla: `admin@caragency.test`, `manager@caragency.test`, `vendedor@caragency.test`; contraseña desde `SEED_USER_PASSWORD` (fallback `password`, solo desarrollo).
- `vin` y `users.code` conservan unicidad global (un valor reciclado rompe la trazabilidad). `email` y `document_number` se validan con `withoutTrashed()` en el FormRequest, porque el índice `(email, deleted_at)` no protege en MySQL (los `NULL` son distintos).

### Fase 0 — Infraestructura y base de tests

- [x] `0.1` Crear `docker/php/Dockerfile` multi-stage (`base`, `dev`, `prod`) con PHP 8.3-FPM, Composer y extensiones para MySQL, SQLite en memoria, bcmath, PDFs, tests y opcache.
- [x] `0.2` Crear `docker-compose.yml` con `app` (target `dev`), `nginx` en `8000` y `mysql` 8.0 con healthcheck.
- [x] `0.3` Configurar Nginx para `/var/www/html/public` y `fastcgi_pass app:9000`.
- [x] `0.4` Crear `.dockerignore` y `.env` local con `DB_HOST=mysql`, `DB_DATABASE=api_laravel_car_agency`, `DB_USERNAME=root`, `DB_PASSWORD=secret`, `SESSION_DRIVER=file`.
- [x] `0.5` Levantar el stack y validar `php artisan about`, migraciones, seeders, `composer test` y `pint`.
- [x] `0.6` Activar `RefreshDatabase` en `tests/Pest.php` y crear un test de humo que ejecute migraciones + `DatabaseSeeder`.
- [x] `0.7` Añadir test base de respuesta JSON (`ForceJsonResponse`) para 404 y validación.
- [x] `0.8` Aislar la suite del MySQL de Docker: PHPUnit solo escribía en `$_ENV`, pero Laravel prefiere `$_SERVER`, donde `env_file` inyecta el `.env` real; `RefreshDatabase` borraba la base de desarrollo.

### Fase 1 — Modelos, borrado lógico e índices

- [x] `1.1` Quitar `SoftDeletes` de `App\Models\Sales\Sale` y crear migración que elimina `sales.deleted_at`; ajustar Resource y servicios que filtren por borrado.
- [x] `1.2` Crear migración de índices: `cars(status)`, `cars(branch_id)`, `cars(car_model_id)`, `sales(sale_date)`, `sales(status, sale_date)`, `sales(user_id)`, `users(is_active)` (`cars.branch_id`, `cars.car_model_id` y `sales.user_id` ya quedan cubiertos por sus claves foráneas, y se elimina el índice único duplicado `deleted_at` sobre `cars.vin`).
- [x] `1.3` Eliminar relaciones inversas no usadas en los modelos (empezando por `CarType::cars`), verificando que ningún `with()` ni Resource las consuma.
- [x] `1.4` Corregir `UpdateUserRequest` (`Rule::unique('users','document')` → `document_number`) y `UpdateCurrencyRequest` (valida `code` contra la columna `name`).
- [x] `1.5` Documentar en `AGENTS.md` la política de unicidad de `vin`/`users.code` y el comportamiento de los índices compuestos con `deleted_at` en MySQL.

### Fase 2 — N+1 y Resources

- [x] `2.1` `CarService`: eager loading con `with()` en `getAllPaginated`/`show` y `load()` en el detalle.
- [x] `2.2` Catálogos de coches (`brands`, `car_models`, `car_types`, `categories`, `colors`): eager loading y `select` de columnas necesarias.
- [x] `2.3` Ubicaciones (`countries`, `states`, `cities`, `branches`): eager loading y `select` de columnas necesarias.
- [x] `2.4` Catálogos de ventas (`currencies`, `payment_methods`, `taxes`): eager loading y `select` de columnas necesarias.
- [x] `2.5` Revisar Resources: cada `whenLoaded()` debe tener su eager load y los controladores deben devolver `new XxxResource` en lugar del modelo crudo.
- [x] `2.6` Test de conteo de consultas (Query Log) para el listado de coches y el de ventas, asegurando que no crece con el número de filas.

### Fase 3 — Transacciones y excepciones

- [x] `3.1` `SaleService::cancelSale`: ejecutar en `DB::transaction` con `lockForUpdate`, rechazar ventas ya canceladas y no cancelables, y devolver el coche a `available`.
- [x] `3.2` Crear excepciones de dominio de ventas (`CarNotAvailableException`, `SaleAlreadyCancelledException`, `SaleNotCancellableException`) y mapearlas a JSON 409/422 en `bootstrap/app.php`.
- [x] `3.3` Adelgazar `SaleController`: quitar `try/catch` genéricos y dejar que las excepciones de dominio se rendericen.
- [x] `3.4` Envolver en transacción los `create`/`update` de `CarService` y `UserService` cuando toquen pivotes o varias tablas.
- [x] `3.5` Tests de cancelación: éxito, doble cancelación (409), coche ya vendido (422) y liberación del coche.

### Fase 4 — Roles, permisos y protección de rutas

- [x] `4.1` Ampliar `PermissionEnum` a los 13 permisos con `description()`: coches (4), catálogos (2, coches y ventas), ubicaciones (2), ventas (3), usuarios (1, `manage_users`), reportes (1).
- [x] `4.2` Actualizar `PermissionSeeder` con `updateOrCreate` para que sea idempotente.
- [x] `4.3` Crear `RolePermissionSeeder` con la matriz: `admin` (13), `manager` (10), `vendedor` (5).
- [x] `4.4` Crear `UserSeeder` con los tres usuarios base, asignación de rol y sucursal, e incorporarlo a `DatabaseSeeder` tras roles y permisos.
- [x] `4.5` Reparar `UserFactory` (namespace `App\Models\Users\User`, columnas reales de `users`) para que `User::factory()` funcione.
- [x] `4.6` Implementar `User::hasPermissionTo(PermissionEnum)` y el middleware `permission` registrado en `bootstrap/app.php`.
- [x] `4.7` Aplicar `auth:sanctum` + `permission:` a todas las rutas; dejar público solo `POST /api/auth/login`.
- [x] `4.8` Tests de autorización: 401 sin token, 403 sin permiso, 200 con permiso, y login público.

### Fase 5 — Reportes PDF

- [x] `5.1` Instalar `barryvdh/laravel-dompdf:^3.1` con `composer require` dentro del contenedor y verificar que persiste en el volumen `vendor/`.
- [x] `5.2` Crear `SaleReportService` + vista Blade `resources/views/reports/sales-pdf.blade.php` + `GET /api/reports/sales/pdf` con filtros de fecha y sucursal, protegido por `view_reports`.
- [x] `5.3` Crear `GET /api/reports/cars` (agregado JSON por tipo, marca y estado) con variante PDF, protegido por `view_reports`.
- [x] `5.4` Tests de reportes: autenticación, cabeceras `application/pdf` y contenido esperado.

### Fase 6 — Cierre

- [x] `6.1` Actualizar `AGENTS.md` (memoria y lecciones aprendidas) con las decisiones de esta iteración.
- [x] `6.2` Ejecutar `docker compose exec -T app ./vendor/bin/pest` y `docker compose exec -T app ./vendor/bin/pint`, y marcar aquí el resultado. Resultado 2026-10-21: `migrate:fresh --seed` OK, **48 tests / 215 aserciones** y **Pint limpio (233 archivos)**; smoke test HTTP con login real y reportes PDF `%PDF-1.7`.

## Verificación

```bash
docker compose ps
docker compose exec -T app php artisan about
docker compose exec -T app php artisan migrate:fresh --seed
docker compose exec -T app ./vendor/bin/pest
docker compose exec -T app ./vendor/bin/pint
docker compose exec -T app ./vendor/bin/phpstan analyse --no-progress
docker compose exec -T app composer audit
```
