# ROADMAP

> Fuente de verdad del avance del proyecto. Cada micro-tarea equivale a un commit atómico; márcala con `[x]` al terminarla.
>
> **Regla de arquitectura de esta etapa:** todo se implementa dentro de esta API Laravel 12 y su MySQL 8.0. Prohibido usar servicios externos (SMTP/correo, OAuth/Google, analítica externa) o librerías pesadas de terceros. La auditoría y las estadísticas se resuelven con tablas, servicios y consultas propias.

---

## ✅ Hitos Completados (Resumen)

### Iteración 1 — Base funcional (fases 0-6)

- Stack Docker completo: PHP 8.3-FPM, Nginx, MySQL 8.0, tests con SQLite `:memory:` y `RefreshDatabase`.
- Modelo de dominio por carpetas (`Cars`, `Locations`, `Sales`, `Users`, `Reports`), DTOs `readonly` y servicios con la lógica de negocio.
- Ventas transaccionales con impuestos, descuentos, tipo de cambio y redondeo a 2 decimales; cancelación que libera el coche.
- RBAC de 13 permisos con roles `admin`, `manager` y `vendedor`; Sanctum con tokens bearer.
- Reportes de ventas e inventario en JSON y PDF con Dompdf; soft deletes en usuarios y coches.

### Iteración 2 — Calidad, seguridad y documentación (fases 7-13)

- PHPStan/Larastan nivel 5 sin baseline en **0 errores**, `strict_types=1` en todo el código y Pint limpio.
- Tests de arquitectura, paginación estable, conteo de consultas, rate limits, CORS y errores transversales.
- Caché de permisos con invalidación explícita y `preventLazyLoading` fuera de producción.
- OpenAPI 3.1.2 con Scramble: `/docs/api`, `/docs/api.json`, 41 paths, grupos en español e importación verificada a Postman.
- Límite de filas de reportes (`REPORTS_MAX_ROWS=500`) y `memory_limit=512M` justificados con benchmark de Dompdf.

### Mantenimiento y despliegue (M1-M3)

- Paginación determinista con `orderBy('id')` y `withQueryString()` en los 16 listados.
- Stack de producción VPS (`docker-compose.prod.yml`, Nginx, MySQL privado, volúmenes y healthchecks).
- Vía PaaS con imagen monolítica FrankenPHP para Render/Railway y workflow de Docker Hub.
- Seeders seguros para producción: `ProductionAdminSeeder` sin Faker, idempotente y con `ADMIN_*`.

**Estado de referencia (último cierre documentado):** suite en **189 tests / 1645 aserciones**, PHPStan **0 errores**, Pint limpio. **Datos locales verificados el 2026-11-03:** 8 sucursales, 14 ciudades, 52 coches, 3 usuarios, 13 permisos y 0 ventas.

---

## 🧱 Línea Base Verificada del Dominio

### Ventas (`sales`)

- Tabla: `id`, `exchange_rate`, `base_price`, `taxes` (JSON), `total_taxes`, `discount_percentage`, `total_base_amount`, `total_amount_paid`, `total_discount`, `status`, `sale_date`, `car_id`, `user_id`, `branch_id`, `payment_currency_id`, `base_currency_id`, `payment_method_id`, `notes`, timestamps.
- Sin `SoftDeletes`: se cancela con `SaleStatus::CANCELLED` (`completed`, `pending`, `cancelled`).
- Relaciones: `car`, `user`, `branch`, `paymentMethod`, `paymentCurrency`, `baseCurrency`.
- Índices útiles: `sale_date`, `(status, sale_date)` y `(branch_id, sale_date)`.
- **Moneda:** `total_base_amount` está en moneda base y es la métrica comparable; `total_amount_paid` está en la moneda de pago y no se suma entre ventas.

### Coches e inventario (`cars`)

- Tabla: `id`, `price`, `mileage`, `year`, `vin` único, `status`, `color_id`, `car_model_id`, `car_type_id`, `category_id`, `branch_id`, soft deletes, timestamps.
- Estados: `available`, `reserved`, `sold`. Marca accesible por `car_model.brand`; tipo, categoría y color por relaciones directas.

### Sucursales y geografía

- `branches`: `id`, `name`, `code` único, `address`, `description`, `zip_code`, `is_active`, `city_id`.
- `cities` → `states` → `countries`; una sucursal pertenece a una ciudad, así que las ventas se pueden agrupar por ciudad/estado/país mediante `branch.city`.

### Usuarios, roles y autenticación

- `users`: `id`, `name`, `last_name`, `document_number`, `code` único global, `email`, `password`, `is_active`, soft deletes, timestamps. Sin `must_change_password`.
- Pivotes `role_user` y `branch_user`; `roles` y `permissions` con `permission_role`.
- Login público con Sanctum; `logout` y `refresh` protegidos. La expiración es de 120 minutos y `is_active` solo se comprueba en el login.
- No existe `GET /api/auth/me`, ni cambio de contraseña de autoservicio, ni reset administrativo.

### Reportes y permisos

- `GET /api/reports/sales/pdf`, `GET /api/reports/cars` y `GET /api/reports/cars/pdf` con permiso `view_reports`.
- Filtros de ventas `from`/`to` (juntos o ninguno) y `branch_id`; la fecha final es inclusiva y las comparaciones son sargables.
- 13 permisos: `admin` 13, `manager` 10, `vendedor` 5. No hay permisos de auditoría ni de estadísticas.
- **No existe** tabla de auditoría, observadores/eventos de dominio ni endpoints de KPIs/rankings.

### Brechas que ataca esta etapa

1. El `code` de usuario todavía se puede modificar y un rol puede quedar sin permisos.
2. No hay guarda para el último administrador activo ni rechazo inmediato de usuarios desactivados con token vigente.
3. No hay perfil de autoservicio, cambio de contraseña propio ni flujo de clave temporal.
4. No hay trazabilidad de accesos ni de operaciones de negocio.
5. No hay endpoints agregados para el futuro panel de administración.

---

## 🚧 Nueva Etapa — Decisiones de Diseño

1. **Permisos dedicados:** se añaden `view_audit_logs` (solo `admin`) y `view_statistics` (`admin` y `manager`). Los conteos pasan de 13/10/5 a 15/11/5 al cerrar la etapa.
2. **Auditoría en la capa de servicio:** nada de triggers de MySQL ni observadores genéricos. El servicio recibe el actor de Sanctum y captura IP/User-Agent de la petición; si la escritura de auditoría falla, la operación de negocio no se rompe.
3. **Auditoría después del commit:** las operaciones transaccionales (ventas, usuarios) registran el log cuando la transacción ya se confirmó, para no auditar cambios revertidos.
4. **Contraseñas y secretos:** nunca se guardan contraseñas, tokens ni `remember_token` en `old_values`/`new_values`; se redactan antes de persistir.
5. **Flag `must_change_password`:** es informativo para el frontend; el login y `/auth/me` lo exponen, pero la API no bloquea al usuario. El reset administrativo genera una clave temporal y revoca sus tokens.
6. **Sin correo:** el olvido de contraseña se resuelve con un administrador que asigna una clave temporal; no hay recuperación por email.
7. **Dinero y estado:** todos los KPIs monetarios usan `total_base_amount` y solo cuentan ventas `completed`; las canceladas y pendientes quedan fuera.
8. **Fechas:** `start_date`/`end_date` van juntos o ninguno, en formato `Y-m-d`, con fin inclusivo y comparaciones sargables (`>= inicio`, `< fin + 1 día`).
9. **Rankings:** `limit` por defecto 10, configurable por query string, máximo 100; si hay menos filas que el límite se devuelven las existentes sin rellenar.
10. **Sin nuevas dependencias:** no se añaden paquetes Composer; todo se apoya en Laravel, Eloquent y MySQL/SQLite.

---

## Fase 1 — Blindaje de Identidad, Autoservicio y Documentación de Acceso

**Objetivo:** cerrar los huecos de identidad y dar al usuario autenticado un camino seguro para consultar su perfil y cambiar su clave, sin correo ni servicios externos.

- [x] `1.1` Hacer inmutable `users.code`: `UpdateUserRequest` rechaza con `422` un `code` distinto del actual (usando `$this->route('user')`) y `UserService::updateUser()` deja de escribir la columna. Test: enviar otro código falla y el valor original permanece.
- [x] `1.2` Impedir roles sin permisos: `StoreRoleRequest`/`UpdateRoleRequest` exigen `permissions` como array con `min:1` cuando se envía, y `RoleService` rechaza un `sync([])` como defensa adicional. Test: `PATCH /api/roles/{role}` con `permissions: []` devuelve `422`.
- [x] `1.3` Guarda del último administrador: nueva excepción de dominio `LastAdminException` renderizada como `409`. En `UserService`, dentro de la transacción y con bloqueo de las filas de administradores activos, impedir (a) `DELETE /api/users/{user}`, (b) `is_active=false` y (c) un `sync` de roles que quite `admin` cuando el objetivo es el único administrador activo. Debe permitirse si existe otro administrador activo.
- [x] `1.4` Middleware `EnsureUserIsActive` (alias `active`) después de `auth:sanctum` en todo el grupo protegido: si `is_active=false`, responde `401` JSON sin ejecutar el controlador, aunque el token siga vigente. Tests con token válido de usuario desactivado y con usuario activo.
- [x] `1.5` Migración `add_must_change_password_to_users_table`: booleano `must_change_password` por defecto `false`, casteado en `User`, incluido en `UserData`/`UserResource` y en los seeders/factory. `UserService::createUser()` lo fuerza a `true` para los empleados creados desde el panel; `ProductionAdminSeeder`, `UserSeeder` y `UserFactory` lo dejan en `false`.
- [x] `1.6` `GET /api/auth/me`: devolver perfil del usuario autenticado con `id`, `name`, `last_name`, `document_number`, `code`, `email`, `is_active`, `must_change_password`, roles, sucursales y `permissions` como lista plana ordenada de sus permisos efectivos. Nuevo Resource dedicado y eager loading de `roles.permissions` y `branches.city.state.country` (sin N+1).
- [x] `1.7` `PUT /api/auth/password`: `ChangePasswordRequest` con `current_password`, `password` (`confirmed`) y `Password::default()`. Verificar la clave actual con `Hash::check` contra el usuario autenticado (evitando depender del guard por defecto), actualizar, poner `must_change_password=false` y revocar todos los tokens salvo el de la petición actual. Tests: clave actual incorrecta `422`, cambio correcto, token actual sigue válido y los demás quedan revocados.
- [x] `1.8` `POST /api/users/{user}/reset-password` protegido por `manage_users`: el administrador asigna una clave temporal con `Password::default()`, se marca `must_change_password=true` y se revocan todos los tokens del usuario. Responder `200` con mensaje, nunca con la contraseña. Test de `403` sin permiso y de revocación efectiva.
- [x] `1.9` Exponer `must_change_password` en la respuesta de `POST /api/auth/login` además del token, para que el futuro frontend sepa si debe forzar el cambio. No bloquear el login por este flag.
- [x] `1.10` README y `.env.example`: nueva subsección **«Credenciales Iniciales por Defecto»** con el administrador de producción `admin@agency.com` / código `USR-0001` / contraseña `secret1234` cuando no se personalizan `ADMIN_*`; aclarar que los usuarios demo locales siguen siendo `admin@caragency.test` / `password`, documentar las variables `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_CODE`, `ADMIN_NAME`, `ADMIN_LAST_NAME` y `ADMIN_DOCUMENT_NUMBER`, y advertir que se cambie la clave tras el primer arranque.
- [x] `1.11` Documentación OpenAPI: documentar `/auth/me`, `/auth/password`, `/users/{user}/reset-password`, el campo `must_change_password`, respuestas `401/403/422` y el ejemplo de credenciales iniciales. Actualizar `OpenApiDocumentTest` si cambia el número de paths.
- [x] `1.12` Tests de la fase: inmutabilidad de `code`, roles sin permisos, guarda del último administrador (borrar, desactivar y quitar rol; permitir con otro admin), usuario inactivo con token, `/auth/me`, cambio de clave, reset administrativo, flag en login y seeders.
- [x] `1.13` Cierre de fase: `composer test`, `composer analyse` (0 errores), `pint --test`, `migrate:fresh --seed` y smoke HTTP de login, `/auth/me` y cambio de clave.

**Criterio de cierre:** ningún administrador puede dejar el sistema sin administración activa; un usuario desactivado no puede operar con un token viejo; el usuario puede consultar su perfil y cambiar su clave sin correo.

---

## Fase 2 — Módulo de Auditoría y Registro de Accesos (100% Nativo)

**Objetivo:** tener trazabilidad completa de accesos y operaciones críticas, consultable desde el futuro panel de administración, sin triggers de base de datos.

- [x] `2.1` Migración `create_audit_logs_table`: `id`, `user_id` nullable con FK `nullOnDelete`, `action` (string indexado), `auditable_type` y `auditable_id` nullable (polimórfico), `old_values` y `new_values` JSON nullable, `ip_address` (45), `user_agent` (500), `created_at`. Índices `(user_id, created_at)`, `(action, created_at)`, `(auditable_type, auditable_id)` y `created_at`. Sin `updated_at`.
- [x] `2.2` Enum `App\Enums\Audit\AuditAction` (string-backed) y modelo `App\Models\Audit\AuditLog` con casts de arrays y `UPDATED_AT = null`. Registrar un morph map (`user`, `role`, `car`, `branch`, `sale`) para que `auditable_type` sea legible y filtrable.
- [x] `2.3` `AuditService::record()`: recibe acción, modelo auditable opcional, valores anteriores/nuevos y actor opcional; resuelve IP/User-Agent de la petición (null en CLI), trunca el User-Agent a la longitud de la columna y redacta `password`, `remember_token`, `token`, `access_token` y equivalentes. Si la escritura falla, registrar un warning y no romper la operación principal.
- [x] `2.4` Auditoría de autenticación: `auth.login` (éxito), `auth.login_failed` (con el email intentado en `new_values`, nunca la contraseña), `auth.logout` y `auth.token_refreshed`. Registrar también como `auth.login_failed` los intentos bloqueados por el rate limit (`429`), con un motivo `throttled`. El login fallido no debe revelar si el email existe.
- [x] `2.5` Auditoría de usuarios y seguridad: `user.created`, `user.updated`, `user.deleted`, `user.activated`, `user.deactivated`, `user.roles_changed`, `user.password_changed`, `user.password_reset`, `role.created`, `role.updated` y `role.deleted`. Guardar old/new sin secretos y con los IDs de roles/sucursales cuando aplique.
- [x] `2.6` Auditoría de negocio: `car.created`, `car.updated`, `car.deleted`, `branch.created`, `branch.updated`, `branch.deleted`, `sale.created` y `sale.cancelled`. En ventas incluir estado anterior/nuevo, importe base, coche, sucursal y vendedor; registrar después del commit de la transacción.
- [x] `2.7` Nuevo permiso `view_audit_logs` en `PermissionEnum`, `PermissionSeeder` y `RolePermissionSeeder`: solo `admin` (los conteos quedan en 14/10/5 al cerrar esta fase). Actualizar `DatabaseSeedTest`, README y cualquier test que fije el número de permisos.
- [x] `2.8` Endpoints `GET /api/audit-logs` y `GET /api/audit-logs/{auditLog}` protegidos por `view_audit_logs`. Index paginado con filtros `user_id`, `action` (validado con `Rule::enum`), `auditable_type` (alias del morph map), `auditable_id`, `from`/`to` (juntos o ninguno, fin inclusivo) y `per_page` (1-100). Orden determinista `id desc`, `withQueryString()`, eager loading del usuario y `AuditLogResource`.
- [x] `2.9` Auditoría inmutable: no exponer `store`, `update` ni `delete` para `audit_logs`; el modelo no acepta asignación masiva desde peticiones y solo `AuditService` escribe en la tabla.
- [x] `2.10` Tests de la fase: login éxito/fallido/logout/refresh, alta/edición/baja/activación/desactivación de usuarios, cambio de roles y contraseñas, CRUD de roles, altas/bajas de coches y sucursales, creación/cancelación de ventas, filtros del index, show, `401/403`, paginación y verificación de que no se guardan secretos.
- [x] `2.11` OpenAPI: nuevo grupo **Auditoría**, parámetros de filtro documentados, `403` por permiso y recursos de log. Actualizar `OpenApiDocumentTest`.
- [x] `2.12` Cierre de fase: suite, PHPStan, Pint, `migrate:fresh --seed`, smoke de un login fallido + login correcto + consulta de auditoría con un administrador.

**Criterio de cierre:** cada acceso y cada operación crítica deja un registro consultable con actor, IP, User-Agent, valores anteriores/nuevos y fecha, sin afectar la operación de negocio si la auditoría falla.

---

## Fase 3 — Módulo de Estadísticas y Rankings Dinámicos (Dashboard KPIs)

**Objetivo:** entregar al futuro panel de administración métricas agregadas y rankings con filtros de fecha/sucursal y límite dinámico, usando consultas eficientes sobre el esquema actual.

- [ ] `3.1` Nuevo permiso `view_statistics` en `PermissionEnum`, seeders y matriz: `admin` y `manager` (conteos finales **15/11/5**). Actualizar tests, README y la tabla de permisos.
- [ ] `3.2` Requests compartidos: `StatsFilterRequest` (`start_date`/`end_date` juntos o ninguno, `Y-m-d`, fin inclusivo; `branch_id` existente), `RankingRequest` (hereda y añade `limit` con default 10, `min:1`, `max:100`) y requests específicos para tendencia e inventario. Helper `limit()` que normalice a `int` en el borde HTTP.
- [ ] `3.3` `StatsService` base: query de ventas `completed`, filtros sargables por `sale_date` y `branch_id`, moneda base y helpers de redondeo a 2 decimales. Orden estable en rankings (`sales_count desc`, `total_base_amount desc`, nombre/ID asc) y exclusión de ventas canceladas/pendientes. Seleccionar todas las columnas agrupadas para cumplir `ONLY_FULL_GROUP_BY` de MySQL.
- [ ] `3.4` `GET /api/stats/summary`: KPIs del periodo (ventas completadas, ingresos en moneda base, ticket promedio —`0` si no hay ventas—), inventario actual por estado, usuarios activos y sucursales activas. Sin fechas, usar el histórico completo; incluir `base_currency` y los filtros aplicados en `meta`.
- [ ] `3.5` `GET /api/stats/top-users?limit=10`: vendedores ordenados por cantidad de ventas completadas y monto total facturado; incluir `id`, nombre, apellidos, código, `sales_count` y `total_base_amount`. Los usuarios históricos siguen apareciendo aunque estén desactivados o con soft delete.
- [ ] `3.6` `GET /api/stats/top-branches?limit=10`: sucursales con más ventas e ingresos; incluir `id`, nombre, código, ciudad, `sales_count` y `total_base_amount`. Las sucursales sin ventas no se inventan.
- [ ] `3.7` `GET /api/stats/top-cities?limit=10`: agrupación por ciudad (a través de `branch.city`) con ventas e ingresos; incluir ciudad y, si aporta contexto, estado/país.
- [ ] `3.8` `GET /api/stats/top-brands?limit=10`: marcas más vendidas por número de operaciones y monto, uniendo ventas → coches → modelos → marcas. Contar el histórico aunque el coche esté soft-deleted.
- [ ] `3.9` `GET /api/stats/top-models?limit=10`: modelos más vendidos con su marca, `sales_count` y `total_base_amount`, con el mismo criterio histórico.
- [ ] `3.10` `GET /api/stats/inventory`: resumen del inventario actual (sin soft deletes): total de coches, valor total, desglose por estado (`available`/`reserved`/`sold` con etiquetas en español) y por sucursal. Acepta `branch_id`; no aplica filtros de fecha porque es stock actual. Reutilizar `CarStatus::label()` o extraer la etiqueta para no duplicar la de `CarReportService`.
- [ ] `3.11` `GET /api/stats/sales/trend`: serie temporal por mes (`period` `Y-m`) con `sales_count` y `total_base_amount`, filtros `start_date`/`end_date`/`branch_id`. Sin fechas, usar los últimos 12 meses; rellenar los meses sin ventas con cero para que el frontend reciba una serie continua. Usar una expresión de agrupación compatible con MySQL y SQLite (tests).
- [ ] `3.12` Revisar índices con `EXPLAIN` sobre un volumen de ~3 000 ventas: candidatos `sales(status, branch_id, sale_date)` y `cars(status, branch_id)` para inventario. Añadir solo los que mejoren el plan y no dupliquen índices de FK; documentar la decisión.
- [ ] `3.13` Tests de la fase: `limit` por defecto 10, `?limit=5`, `?limit=20`, tope 100, `101` → `422`, y caso con solo 3 filas que devuelve 3; sumas/counts correctos; ventas canceladas excluidas; filtros por rango y sucursal; tendencia mensual con huecos; inventario por estado/sucursal; `401` sin token y `403` para `vendedor`. Añadir los endpoints a `QueryCountTest` con número constante de consultas.
- [ ] `3.14` OpenAPI: nuevo grupo **Estadísticas** con parámetros `limit`, `start_date`, `end_date`, `branch_id`, descripciones y esquemas de respuesta; documentar el `403` y el `422` del límite. Actualizar `OpenApiDocumentTest` y la importación a Postman.
- [ ] `3.15` Cierre de fase: suite, PHPStan, Pint, `migrate:fresh --seed`, smoke HTTP de `summary`, un top con `limit` y la tendencia.

**Criterio de cierre:** el panel puede pedir cualquier ranking con `limit` dinámico, filtrar por fechas y sucursal, y recibir datos correctos aunque haya menos registros que el límite solicitado.

---

## Fase 4 — Testing, Calidad y Documentación

**Objetivo:** cerrar la etapa con la suite, el análisis estático y la documentación al día, sin regresiones.

- [ ] `4.1` Repasar la cobertura Feature de todas las novedades y completar huecos: guarda del último admin, inmutabilidad de `code`, roles sin permisos, usuario inactivo, autoservicio de contraseña, reset administrativo, auditoría de logins/logouts/acciones, rankings con límite dinámico y menos datos que el límite.
- [ ] `4.2` `composer analyse` en **0 errores** (PHPStan/Larastan nivel 5, sin baseline) y `pint --test` limpio en todos los archivos nuevos y modificados.
- [ ] `4.3` Actualizar `AGENTS.md`: nuevos permisos y matriz 15/11/5, middleware `active`, flujo `must_change_password`, guarda del último administrador, esquema y convenciones de `audit_logs` (acciones, redacción, escritura tras commit), convenciones de estadísticas (solo `completed`, moneda base, fechas inclusivas, `limit` 10/100) y la regla de no usar servicios externos.
- [ ] `4.4` Actualizar `README.md`: credenciales iniciales, `/auth/me`, cambio y reset de contraseña, tabla de rutas y permisos, sección de auditoría con filtros, sección de estadísticas con ejemplos `curl`, estados de la suite y troubleshooting de los nuevos `401/403/409/422`.
- [ ] `4.5` Verificación integral en Docker: `migrate:fresh --seed` (sin ventas), suite completa, `composer analyse`, `pint --test`, `composer audit` y exportación OpenAPI (`composer docs:export` + validación con Redocly).
- [ ] `4.6` Smoke HTTP real: login del administrador, `/auth/me`, cambio de clave, reset administrativo, consulta de auditoría de login/logout, `stats/summary`, `top-users?limit=5`, `top-branches`, `top-cities`, `top-brands`, `inventory` y `sales/trend`; comprobar `403` de un vendedor en auditoría/estadísticas y `401` de un usuario desactivado.
- [ ] `4.7` Marcar todas las casillas de esta etapa en `ROADMAP.md`, registrar la memoria técnica en `AGENTS.md` y dejar un commit atómico por micro-tarea completada.

**Criterio de cierre:** suite verde, PHPStan 0, Pint limpio, documentación actualizada y smoke HTTP completo contra Nginx/MySQL.

---

## Verificación

```bash
docker compose ps
docker compose exec -T app php artisan migrate:fresh --seed
docker compose exec -T app composer test
docker compose exec -T app composer analyse
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app composer audit
docker compose exec -T app composer docs:export
```

> La suite usa SQLite `:memory:`; MySQL solo se toca en `migrate:fresh --seed` y en los smoke tests HTTP.
