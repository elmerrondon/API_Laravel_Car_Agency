# ROADMAP

Fuente de verdad del avance del proyecto. Cada micro-tarea equivale a un commit atómico; márcala con `[x]` al terminarla.

El contenido de `Pendientes.txt` quedó absorbido y verificado en este documento, por lo que ese archivo se eliminó.

## Decisiones acordadas

- `Sale` deja de usar borrado lógico: se maneja con `SaleStatus` (`pending` → `completed` → `cancelled`). Cancelar devuelve el coche a `available`.
- Solo `POST /api/auth/login` es público; el resto de rutas exige `auth:sanctum` + permiso.
- RBAC granular con 13 permisos: `admin` completo, `manager` operativo, `vendedor` comercial.
- Usuarios semilla: `admin@caragency.test`, `manager@caragency.test`, `vendedor@caragency.test`; contraseña desde `SEED_USER_PASSWORD` (fallback `password`, solo desarrollo).
- `vin` y `users.code` conservan unicidad global (un valor reciclado rompe la trazabilidad). `email` y `document_number` se validan con `withoutTrashed()` en el FormRequest, porque el índice `(email, deleted_at)` no protege en MySQL (los `NULL` son distintos).

## Fase 0 — Infraestructura y base de tests

- [x] `0.1` Crear `docker/php/Dockerfile` multi-stage (`base`, `dev`, `prod`) con PHP 8.3-FPM, Composer y extensiones para MySQL, SQLite en memoria, bcmath, PDFs, tests y opcache.
- [x] `0.2` Crear `docker-compose.yml` con `app` (target `dev`), `nginx` en `8000` y `mysql` 8.0 con healthcheck.
- [x] `0.3` Configurar Nginx para `/var/www/html/public` y `fastcgi_pass app:9000`.
- [x] `0.4` Crear `.dockerignore` y `.env` local con `DB_HOST=mysql`, `DB_DATABASE=api_laravel_car_agency`, `DB_USERNAME=root`, `DB_PASSWORD=secret`, `SESSION_DRIVER=file`.
- [ ] `0.5` Levantar el stack y validar `php artisan about`, migraciones, seeders, `composer test` y `pint`.
- [ ] `0.6` Activar `RefreshDatabase` en `tests/Pest.php` y crear un test de humo que ejecute migraciones + `DatabaseSeeder`.
- [ ] `0.7` Añadir test base de respuesta JSON (`ForceJsonResponse`) para 404 y validación.

## Fase 1 — Modelos, borrado lógico e índices

- [ ] `1.1` Quitar `SoftDeletes` de `App\Models\Sales\Sale` y crear migración que elimina `sales.deleted_at`; ajustar Resource y servicios que filtren por borrado.
- [ ] `1.2` Crear migración de índices: `cars(status)`, `cars(branch_id)`, `cars(car_model_id)`, `sales(sale_date)`, `sales(status, sale_date)`, `sales(user_id)`, `users(is_active)`.
- [ ] `1.3` Eliminar relaciones inversas no usadas en los modelos (empezando por `CarType::cars`), verificando que ningún `with()` ni Resource las consuma.
- [ ] `1.4` Corregir `UpdateUserRequest` (`Rule::unique('users','document')` → `document_number`) y `UpdateCurrencyRequest` (valida `code` contra la columna `name`).
- [ ] `1.5` Documentar en `AGENTS.md` la política de unicidad de `vin`/`users.code` y el comportamiento de los índices compuestos con `deleted_at` en MySQL.

## Fase 2 — N+1 y Resources

- [ ] `2.1` `CarService`: eager loading con `with()` en `getAllPaginated`/`show` y `load()` en el detalle.
- [ ] `2.2` Catálogos de coches (`brands`, `car_models`, `car_types`, `categories`, `colors`): eager loading y `select` de columnas necesarias.
- [ ] `2.3` Ubicaciones (`countries`, `states`, `cities`, `branches`): eager loading y `select` de columnas necesarias.
- [ ] `2.4` Catálogos de ventas (`currencies`, `payment_methods`, `taxes`): eager loading y `select` de columnas necesarias.
- [ ] `2.5` Revisar Resources: cada `whenLoaded()` debe tener su eager load y los controladores deben devolver `new XxxResource` en lugar del modelo crudo.
- [ ] `2.6` Test de conteo de consultas (Query Log) para el listado de coches y el de ventas, asegurando que no crece con el número de filas.

## Fase 3 — Transacciones y excepciones

- [ ] `3.1` `SaleService::cancelSale`: ejecutar en `DB::transaction` con `lockForUpdate`, rechazar ventas ya canceladas y no cancelables, y devolver el coche a `available`.
- [ ] `3.2` Crear excepciones de dominio de ventas (`CarNotAvailableException`, `SaleAlreadyCancelledException`, `SaleNotCancellableException`) y mapearlas a JSON 409/422 en `bootstrap/app.php`.
- [ ] `3.3` Adelgazar `SaleController`: quitar `try/catch` genéricos y dejar que las excepciones de dominio se rendericen.
- [ ] `3.4` Envolver en transacción los `create`/`update` de `CarService` y `UserService` cuando toquen pivotes o varias tablas.
- [ ] `3.5` Tests de cancelación: éxito, doble cancelación (409), coche ya vendido (422) y liberación del coche.

## Fase 4 — Roles, permisos y protección de rutas

- [ ] `4.1` Ampliar `PermissionEnum` a los 13 permisos con `description()`: coches (4), catálogos de coches (2), ubicaciones (2), ventas (3), usuarios (2), reportes (1).
- [ ] `4.2` Actualizar `PermissionSeeder` con `updateOrCreate` para que sea idempotente.
- [ ] `4.3` Crear `RolePermissionSeeder` con la matriz: `admin` (13), `manager` (10), `vendedor` (5).
- [ ] `4.4` Crear `UserSeeder` con los tres usuarios base, asignación de rol y sucursal, e incorporarlo a `DatabaseSeeder` tras roles y permisos.
- [ ] `4.5` Reparar `UserFactory` (namespace `App\Models\Users\User`, columnas reales de `users`) para que `User::factory()` funcione.
- [ ] `4.6` Implementar `User::hasPermissionTo(PermissionEnum)` y el middleware `permission` registrado en `bootstrap/app.php`.
- [ ] `4.7` Aplicar `auth:sanctum` + `permission:` a todas las rutas; dejar público solo `POST /api/auth/login`.
- [ ] `4.8` Tests de autorización: 401 sin token, 403 sin permiso, 200 con permiso, y login público.

## Fase 5 — Reportes PDF

- [ ] `5.1` Instalar `barryvdh/laravel-dompdf:^3.1` con `composer require` dentro del contenedor y verificar que persiste en el volumen `vendor/`.
- [ ] `5.2` Crear `SaleReportService` + vista Blade `resources/views/reports/sales-pdf.blade.php` + `GET /api/reports/sales/pdf` con filtros de fecha y sucursal, protegido por `view_reports`.
- [ ] `5.3` Crear `GET /api/reports/cars` (agregado JSON por tipo, marca y estado) con variante PDF, protegido por `view_reports`.
- [ ] `5.4` Tests de reportes: autenticación, cabeceras `application/pdf` y contenido esperado.

## Fase 6 — Cierre

- [ ] `6.1` Actualizar `AGENTS.md` (memoria y lecciones aprendidas) con las decisiones de esta iteración.
- [ ] `6.2` Ejecutar `docker compose exec -T app ./vendor/bin/pest` y `docker compose exec -T app ./vendor/bin/pint`, y marcar aquí el resultado.

## Verificación

```bash
docker compose ps
docker compose exec -T app php artisan about
docker compose exec -T app php artisan migrate:fresh --seed
docker compose exec -T app ./vendor/bin/pest
docker compose exec -T app ./vendor/bin/pint
```
