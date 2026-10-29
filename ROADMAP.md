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
- [x] `8.4` Ampliar `phpstan.neon` a `routes`, `database` y `config`: `config('seeders.user_password')` en lugar de `env()` en `UserSeeder` y `UserFactory::definition()` sin el docblock incompatible. `composer analyse` en verde (**0 errores**).

## Fase 9 — Tipado estricto y arquitectura

- [x] `9.1` `declare(strict_types=1)` en `app/Enums`, `app/DTOs` y `app/Models` (21 + 16 archivos). Los DTOs castean con el trait `NormalizesRequestValues` porque `validated()` devuelve `mixed` y la validación acepta `"5"`/`"1"`; `ColorData::fromData()` pasa a `fromRequest()`.
- [x] `9.2` `declare(strict_types=1)` en `app/Services`, `app/Exceptions` y `app/Providers` (26 archivos, sin cambios de comportamiento: los DTOs ya entregan tipos exactos).
- [x] `9.3` `declare(strict_types=1)` en `app/Http` (92 archivos) y `bootstrap/app.php`. Al romper la coerción aparece el punto real: `per_page` de una query string llegaba como `"5"` a los servicios (`?int`), resuelto con `Controller::perPage()`; también se castean `notes`, `email`/`password` y `branch_id` del reporte de ventas.
- [x] `9.4` `tests/Feature/ArchitectureTest.php`: 11 reglas `arch()` (strict types, sin `dd`/`dump`/`env()`, enums string-backed, modelos Eloquent, DTOs `readonly` y sin dependencias de `App\Http`/`App\Services`, servicios sin `App\Http`, controllers/resources sin `DB`/`Builder`) + un test por reflexión que exige un servicio inyectado en cada controller. `toUse()` positivo es agregado por capa, por eso la delegación se comprueba clase a clase. Detectó dos controllers con inyección por método (`CarStatusController`, `PermissionController`), normalizados; `memory_limit=512M` en `phpunit.xml` porque el análisis agota los 128M.
- [x] `9.5` Regla `declare_strict_types` activada en `pint.json`: Pint añadió la declaración a los 96 archivos restantes (`routes`, `config`, `database`, `tests`, `bootstrap/providers.php`, `public/index.php`). Verificado con `migrate:fresh --seed` (52 coches, 3 usuarios, 8 sucursales) y smoke HTTP.

## Fase 10 — Cobertura de tests faltante

- [x] `10.1` `tests/Feature/Auth/LogoutTest.php`: 200 y mensaje, token borrado en `personal_access_tokens` (siguiente petición 401), logout sin token 401 y `logout` solo revoca el token usado (el de otro dispositivo sigue funcionando).
- [x] `10.2` `tests/Feature/Auth/RefreshTokenTest.php`: 200 con token nuevo (distinto), el anterior queda revocado (401), solo queda un token y la expiración se renueva (tras 150 min el original caduca pero el renovado sigue válido); refresh sin token 401.
- [x] `10.3` `tests/Feature/Auth/TokenExpirationTest.php` con `Carbon::setTestNow`: 119 min válido, 121 min 401, `sanctum.expiration` configurable (30 min) y re-resolución del guard al cambiar la config.
- [x] `10.4` `tests/Feature/Reports/SaleReportFiltersTest.php`: `from`/`to` juntos o ninguno (422 en ambos sentidos), fechas inválidas o invertidas (422), `branch_id` inexistente (422), extremos del rango inclusivos, totales en moneda base (no en la de pago) y `branch_id` normalizado desde query string.
- [x] `10.5` `tests/Feature/Sales/CreateSaleTest.php`: cálculo de impuestos (16 % + 3 %), descuento (7,5 %) y tipo de cambio (1,15) sobre un precio conocido, importes derivados redondeados a 2 decimales, coche reservado → 422, `sale_date` fuera de `Y-m-d H:i:s` → 422 e impuestos vacíos/inactivos/duplicados, descuento > 100 y tipo de cambio con 3 decimales → 422.
- [x] `10.6` `tests/Feature/Catalogs/CatalogAndLocationCrudTest.php`: CRUD completo de marcas y de sucursales (código `SUC-0000` generado en servidor y `city.state` cargada), vendedor con `view_*` puede leer catálogos/ubicaciones pero recibe 403 en `store`/`update`/`destroy`, manager gestiona catálogos pero no ubicaciones (matriz de permisos) y `per_page` fuera de 1-100 o no numérico → 422.
- [x] `10.7` `tests/Feature/Users/UserCrudTest.php`: alta con roles/sucursales (contraseña hasheada y permisos derivados del rol), duplicados de email/documento/código → 422, edición de nombre/email/roles, email de otro usuario → 422, desactivar revoca tokens, borrado lógico con revocación y 404 posterior, reutilización de email/documento tras el soft delete (el `code` sigue siendo global) e index/show con relaciones.
- [x] `10.8` `tests/Feature/Http/TransversalErrorsTest.php`: 404 de ruta y de recurso con soft delete, 405 JSON (con y sin `Accept`), 429 de login con `Retry-After` y liberación tras 61 s, 401 JSON en los grupos protegidos, 403 con mensaje del middleware y 500 explícito si una ruta exige un `permission` que no existe en el enum.

## Fase 11 — Rendimiento y consultas

- [x] `11.1` `Model::preventLazyLoading(! app()->isProduction())` en `AppServiceProvider`: los N+1 rompen la petición en `local`/`testing` y se degradan a consulta perezosa en producción. La fase 2.5 ya había centralizado los eager loads, así que no quedó ningún N+1 por corregir; `tests/Feature/Performance/PreventLazyLoadingTest.php` fija el ajuste (colección perezosa lanza `LazyLoadingViolationException`, eager loading y modelos sueltos siguen funcionando).
- [x] `11.2` `User::permissionNames()` cachea los nombres de permiso (`config/permissions.php`, `PERMISSIONS_CACHE_TTL=300`, 0 lo desactiva) y `hasPermissionTo()` compara contra esa lista. Invalidación explícita: `forgetPermissionCache()` al crear/editar/borrar usuario (roles, contraseña o baja) y `flushPermissionCacheForRole()` en `RoleService::updateRole`. `tests/Feature/Users/PermissionCacheTest.php` cubre caché caliente (0 consultas), acumulación sin duplicados, invalidación por cambio de rol, invalidación al cambiar los permisos del rol, expiración del TTL y TTL 0. Baja las consultas de `/api/sales` de 24 a 22.
- [x] `11.3` `EXPLAIN` revisado en MySQL: el filtro de fechas usaba `whereDate()` (`date(sale_date)`, `type=ALL` + filesort) y ahora compara `sale_date` desnuda con límite superior exclusivo (`>= from 00:00`, `< to+1 día`), así que entra por `sales_sale_date_index` (`type=range`) sin cambiar la semántica inclusiva. Nueva migración `add_sales_branch_date_index` con `(branch_id, sale_date)`: con 3 000 ventas de prueba el reporte por sucursal pasa de `ref` + `Using filesort` a `ref` sin orden temporal. Listados de `cars`/`users` se quedan en `ALL` (52 y 3 filas, sin filtros que indexar) y `GROUP BY status` ya usa `cars_status_index`. Sin índices nuevos en `cars` (los duplicados de FK siguen descartados).
- [x] `11.4` `QueryCountTest` ampliado (17 casos): helper `assertConstantQueriesFor()` compara `per_page` pequeño frente a grande en coches, ventas, los 8 catálogos y las 4 ubicaciones; usuarios 1 vs 3; el reporte de coches no suma consultas al pasar de 52 a 152 coches y el PDF reutiliza exactamente las del JSON; el PDF de ventas mantiene el número de consultas con 5 y con 20 ventas. El `beforeEach` calienta la caché de permisos para que la comparación no dependa del primer request.
- [x] `11.5` Benchmark de Dompdf con ventas reales (PHP 8.3, contenedor `app`): 50 filas → 0,68 s / 54 MB / 3 páginas; 200 → 2,40 s / 98 MB / 9; 500 → 8,24 s / 216 MB / 21; 1 000 → 22,34 s / 508 MB / 41; 2 000 → 87,91 s / 1 442 MB / 81. Con el `memory_limit` de 128 MB el proceso moría ya con 500 filas, así que: (a) `docker/php/php.ini` sube `memory_limit` a 512 MB (y apaga `expose_php`), (b) nuevo `config/reports.php` con `REPORTS_MAX_ROWS=500`, y (c) `SaleReportService` cuenta filas antes de hidratar y devuelve 422 (`ReportTooLargeException`) pidiendo acotar fechas o sucursal. El reporte de coches no se limita porque agrega en SQL (3 consultas, salida del tamaño de los catálogos). Smoke real por Nginx+FPM: 500 ventas → `200` en 7,86 s (968 KB); 501 → `422` en 0,29 s.

## Fase 12 — OpenAPI y Postman

- [x] `12.1` `app/OpenApi/ApiDocumentation` (transformador de operaciones registrado en `AppServiceProvider::booted()` para correr después de los de Scramble) documenta `429` en todas las rutas, `403` en las que llevan `permission:*` y `409` en cancelar venta y en los `DELETE` con claves foráneas `RESTRICT` (no en `cars`/`users`, que son borrado lógico). El `429` y el `409` se añaden solo si el código no venía ya inferido por Scramble. `401`, `404` y `422` siguen saliendo del análisis de middleware, route-model binding y FormRequest.
- [x] `12.2` Los dos endpoints PDF (`GET /api/reports/cars/pdf` y `GET /api/reports/sales/pdf`) declaran `#[Response(200, mediaType: 'application/pdf', format: 'binary')]`; el transformador elimina el `application/json` que Scramble infería del tipo de retorno, así que el 200 solo ofrece `application/pdf` con `schema: {type: string, format: binary}`.
- [x] `12.3` Paginación explícita: todo listado con `per_page` recibe además `page` (integer, mínimo 1) y ambos llevan descripción; `per_page` conserva el rango 1-100 del FormRequest. Los filtros de reportes (`from`, `to`, `branch_id`) se describen en la misma tabla `ApiDocumentation::PARAMETERS`, con el aviso de que `from`/`to` van juntos y la fecha final es inclusiva. Los dos endpoints no paginados (`/car-statuses`, `/permissions`) devuelven la colección completa, así que no declaran `page`.
- [x] `12.4` Siete grupos en español con descripción y orden propio (`ApiDocumentation::TAGS`): Autenticación, Coches, Catálogos, Ubicaciones, Ventas, Reportes y Usuarios. Cada recurso declara su grupo y su nombre (`RESOURCES`) y el resumen se genera con la convención `Listar/Ver/Crear/Actualizar/Eliminar` (p. ej. «Listar coches», «Crear el coche»); los casos especiales viven en `SUMMARIES` (`Iniciar sesión`, `Cancelar una venta`, los tres reportes). Se registran con `Scramble::configure()->withTags()` dentro de `booted()`.
- [x] `12.5` `composer docs:export` genera `storage/app/openapi.json` (485 KB, OpenAPI 3.1.2, 41 paths, 87 operaciones, 7 grupos) dentro de `storage/app/`, que está ignorado por Git. Validado con `npx @redocly/cli lint`: el documento es válido; la primera pasada detectó un error real (`no-identical-paths` por `/taxes/{tax}` y `/taxes/{Tax}`, corregido en `fix(taxes): use lowercase route parameter names`) y quedan 2 warnings aceptados (`info-license` y `no-server-example.com`, porque el servidor es relativo a propósito). Importación a Postman comprobada con `npx openapi-to-postmanv2` (la librería del importador de Postman): colección v2.1.0 con 87 peticiones, auth Bearer `{{bearerToken}}` y `baseUrl=http://localhost:8000/api`. Los 2 warnings de `scramble:export` (`UpdateStateRequest::$state`, `UpdateCityRequest::$city`) son limitaciones del análisis estático: el `where` dinámico del único no cambia el esquema documentado.
- [x] `12.6` `tests/Feature/Docs/OpenApiDocumentTest.php` (8 pruebas, 9 casos con datasets) fija el contrato del documento: los 41 paths esperados, los 7 grupos en orden con resumen en cada operación, `bearerAuth` http/bearer, el mapa de errores (login sin 401/403 pero con 429; `logout`/`refresh` sin 403; rutas con `permission:` con 401+403; 404 en `show`; 409 solo en cancelar venta y borrados con FK), los PDF como `application/pdf` binario sin `application/json`, `page`/`per_page` en los 16 listados paginados, los filtros de reportes y la resolución de todas las `$ref` internas. El documento se genera una vez por archivo con un helper memoizado. `DocsAccessTest` conserva la cobertura de 200 en `testing` y del 403 en producción sin `DOCS_ENABLED`.

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
