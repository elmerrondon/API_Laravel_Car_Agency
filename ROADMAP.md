# ROADMAP

> **Propósito:** único plan de trabajo y registro de avance del proyecto. Aquí viven objetivos, tareas, criterios de cierre y estado. La guía operativa está en `AGENTS.md`, las lecciones en `MEMORY.md` y el uso en `README.md`; no dupliques esos contenidos aquí.
>
> **Regla de arquitectura de esta etapa:** todo se implementa dentro de esta API Laravel 12 y su MySQL 8.0. Prohibido usar servicios externos (SMTP/correo, OAuth/Google, analítica externa) o librerías pesadas de terceros. La auditoría y las estadísticas se resuelven con tablas, servicios y consultas propias.

---

## 📌 Estado actual

- **Actualizado:** 2026-11-04.
- **Etapa en curso:** Fase 3 — Estadísticas y rankings (Fases 1 y 2 cerradas).
- **Verificación vigente:** 237 tests / 1 967 aserciones, PHPStan 0 errores, Pint limpio.
- **Permisos:** 14 sembrados (`admin` 14, `manager` 10, `vendedor` 5). La Fase 3 los lleva a 15/11/5 con `view_statistics`.
- **Datos locales:** 8 sucursales, 14 ciudades, 52 coches, 3 usuarios y 0 ventas.

---

## ✅ Hitos completados

### Iteración 1 — Base funcional (fases 0-6)

- Stack Docker (PHP 8.3-FPM, Nginx, MySQL 8.0) y tests con SQLite `:memory:` + `RefreshDatabase`.
- Dominio por carpetas (`Cars`, `Locations`, `Sales`, `Users`, `Reports`) con DTOs `readonly` y servicios.
- Ventas transaccionales (impuestos, descuentos, tipo de cambio, redondeo a 2 decimales) y cancelación que libera el coche.
- RBAC de 13 permisos con roles `admin`, `manager`, `vendedor`; Sanctum con tokens bearer.
- Reportes de ventas e inventario en JSON/PDF con Dompdf; soft deletes en usuarios y coches.

### Iteración 2 — Calidad y documentación (fases 7-13)

- PHPStan/Larastan nivel 5 sin baseline en 0 errores, `strict_types=1` y Pint limpio.
- Tests de arquitectura, paginación estable, conteo de consultas, rate limits, CORS y errores transversales.
- Caché de permisos con invalidación explícita y `preventLazyLoading` fuera de producción.
- OpenAPI 3.1.2 con Scramble (`/docs/api`, 41 paths, grupos en español e importación a Postman).
- Límite `REPORTS_MAX_ROWS=500` y `memory_limit=512M` justificados con benchmark de Dompdf.

### Mantenimiento y despliegue (M1-M3)

- Paginación determinista (`orderBy('id')` + `withQueryString()`) en los 16 listados.
- Stack VPS de producción (`docker-compose.prod.yml`, Nginx, MySQL privado, volúmenes y healthchecks).
- Vía PaaS con FrankenPHP y workflow de Docker Hub.
- Seeders seguros para producción (`ProductionAdminSeeder` sin Faker, idempotente y con `ADMIN_*`).

### Fase 1 — Identidad y autoservicio ✅

- `users.code` inmutable y roles que no pueden quedar sin permisos.
- Guarda del último administrador activo (`LastAdminException` → `409`).
- Middleware `active` que rechaza tokens de cuentas desactivadas.
- `GET /api/auth/me`, `PUT /api/auth/password` y `POST /api/users/{user}/reset-password`.
- Flag `must_change_password` expuesto en login y `/auth/me`.
- **Cierre:** ningún administrador puede dejar el sistema sin administración activa; un usuario desactivado no opera con token viejo; el autoservicio funciona sin correo.
- Detalle y verificación: entrada del 2026-11-03 en `MEMORY.md`.

### Fase 2 — Auditoría nativa ✅

- Tabla append-only `audit_logs` y `AuditService` con redacción de secretos y escritura tras commit.
- Auditoría de login éxito/fallo/throttled, logout, refresh, usuarios, roles, contraseñas, coches, sucursales y ventas.
- Permiso `view_audit_logs` (solo `admin`) y endpoints `GET /api/audit-logs` + show con filtros y paginación.
- **Cierre:** cada acceso y operación crítica deja un registro consultable con actor, IP, User-Agent, valores y fecha, sin romper la operación si la auditoría falla.
- Detalle y verificación: entrada del 2026-11-04 en `MEMORY.md`.

---

## 🧱 Contexto técnico para la Fase 3

- `sales` no tiene soft deletes; solo las ventas `completed` cuentan para KPIs y rankings.
- `total_base_amount` es la única métrica monetaria comparable; `total_amount_paid` está en moneda de pago.
- Las marcas se alcanzan por `cars.car_model.brand`; no hay relación inversa `Car::sales()`, así que las consultas parten de `Sale` con joins.
- Índices actuales de `sales`: `sale_date`, `(status, sale_date)` y `(branch_id, sale_date)`. Candidato a evaluar con `EXPLAIN`: `(status, branch_id, sale_date)`.
- Los tests usan SQLite `:memory:` y producción MySQL 8.0: la agrupación por fecha debe ser compatible con ambos.
- La base local no tiene ventas. Para el smoke de cierre se puede sembrar un `SaleSeeder` demo (solo local/testing) o crear ventas vía API; no añadir Faker a seeders de producción.

---

## Fase 3 — Módulo de Estadísticas y Rankings Dinámicos (Dashboard KPIs)

**Objetivo:** entregar al futuro panel de administración métricas agregadas y rankings con filtros de fecha/sucursal y límite dinámico, usando consultas eficientes sobre el esquema actual.

- [x] `3.1` Nuevo permiso `view_statistics` en `PermissionEnum`, seeders y matriz: `admin` y `manager` (conteos finales **15/11/5**). Actualizar tests, README y la tabla de permisos.
- [ ] `3.2` Requests compartidos: `StatsFilterRequest` (`start_date`/`end_date` juntos o ninguno, `Y-m-d`, fin inclusivo; `branch_id` existente), `RankingRequest` (hereda y añade `limit` con default 10, `min:1`, `max:100`) y requests específicos para tendencia e inventario. Helper `limit()` que normalice a `int` en el borde HTTP.
- [x] `3.3` `StatsService` base: query de ventas `completed`, filtros sargables por `sale_date` y `branch_id`, moneda base y helpers de redondeo a 2 decimales. Orden estable en rankings (`sales_count desc`, `total_base_amount desc`, nombre/ID asc) y exclusión de ventas canceladas/pendientes. Seleccionar todas las columnas agrupadas para cumplir `ONLY_FULL_GROUP_BY` de MySQL.
- [x] `3.4` `GET /api/stats/summary`: KPIs del periodo (ventas completadas, ingresos en moneda base, ticket promedio —`0` si no hay ventas—), inventario actual por estado, usuarios activos y sucursales activas. Sin fechas, usar el histórico completo; incluir `base_currency` y los filtros aplicados en `meta`.
- [x] `3.5` `GET /api/stats/top-users?limit=10`: vendedores ordenados por cantidad de ventas completadas y monto total facturado; incluir `id`, nombre, apellidos, código, `sales_count` y `total_base_amount`. Los usuarios históricos siguen apareciendo aunque estén desactivados o con soft delete.
- [x] `3.6` `GET /api/stats/top-branches?limit=10`: sucursales con más ventas e ingresos; incluir `id`, nombre, código, ciudad, `sales_count` y `total_base_amount`. Las sucursales sin ventas no se inventan.
- [x] `3.7` `GET /api/stats/top-cities?limit=10`: agrupación por ciudad (a través de `branch.city`) con ventas e ingresos; incluir ciudad y, si aporta contexto, estado/país.
- [x] `3.8` `GET /api/stats/top-brands?limit=10`: marcas más vendidas por número de operaciones y monto, uniendo ventas → coches → modelos → marcas. Contar el histórico aunque el coche esté soft-deleted.
- [x] `3.9` `GET /api/stats/top-models?limit=10`: modelos más vendidos con su marca, `sales_count` y `total_base_amount`, con el mismo criterio histórico.
- [x] `3.10` `GET /api/stats/inventory`: resumen del inventario actual (sin soft deletes): total de coches, valor total, desglose por estado (`available`/`reserved`/`sold` con etiquetas en español) y por sucursal. Acepta `branch_id`; no aplica filtros de fecha porque es stock actual. Reutilizar `CarStatus::label()` o extraer la etiqueta para no duplicar la de `CarReportService`.
- [x] `3.11` `GET /api/stats/sales/trend`: serie temporal por mes (`period` `Y-m`) con `sales_count` y `total_base_amount`, filtros `start_date`/`end_date`/`branch_id`. Sin fechas, usar los últimos 12 meses; rellenar los meses sin ventas con cero para que el frontend reciba una serie continua. Usar una expresión de agrupación compatible con MySQL y SQLite (tests).
- [x] `3.12` Revisar índices con `EXPLAIN` sobre un volumen de ~3 000 ventas: candidatos `sales(status, branch_id, sale_date)` y `cars(status, branch_id)` para inventario. Añadir solo los que mejoren el plan y no dupliquen índices de FK; documentar la decisión.
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
- [ ] `4.7` Marcar todas las casillas de esta etapa en `ROADMAP.md`, registrar la memoria técnica en `MEMORY.md` y dejar un commit atómico por micro-tarea completada.

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
