# ROADMAP

> **Propósito:** único plan de trabajo y registro de avance del proyecto. Aquí viven objetivos, tareas, criterios de cierre y estado. La guía operativa está en `AGENTS.md`, las lecciones en `MEMORY.md` y el uso en `README.md`; no dupliques esos contenidos aquí.
>
> **Regla de arquitectura de esta etapa:** todo se implementa dentro de esta API Laravel 12 y su MySQL 8.0. Prohibido usar servicios externos (SMTP/correo, OAuth/Google, analítica externa) o librerías pesadas de terceros. La auditoría y las estadísticas se resuelven con tablas, servicios y consultas propias.

---

## 📌 Estado actual

- **Actualizado:** 2026-11-04.
- **Etapa en curso:** Fase 4 — Testing, calidad y documentación (Fases 1, 2 y 3 cerradas).
- **Verificación vigente:** 297 tests / 2 377 aserciones, PHPStan 0 errores, Pint limpio en 314 archivos.
- **Permisos:** 15 sembrados (`admin` 15, `manager` 11, `vendedor` 5).
- **Datos locales:** 8 sucursales, 14 ciudades, 52 coches, 3 usuarios y 3 ventas del smoke.

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

### Fase 3 — Estadísticas y rankings ✅

- Permiso `view_statistics` (`admin` 15, `manager` 11, `vendedor` 5).
- Ocho endpoints bajo `/api/stats`: `summary`, `top-users`, `top-branches`, `top-cities`, `top-brands`, `top-models`, `inventory` y `sales/trend`.
- Solo ventas `completed` y `total_base_amount`; filtros `start_date`/`end_date` inclusivos y `branch_id`; rankings con `limit` 1-100 (10 por defecto) y menos filas si no alcanzan.
- Histórico con soft deletes en usuarios y coches; inventario actual sin soft deletes; tendencia mensual con los huecos en cero.
- Índices `sales(branch_id, status, sale_date)` y `cars(branch_id, status)` elegidos con `EXPLAIN` sobre 3 000 ventas/coches.
- **Cierre:** 297 tests / 2 377 aserciones, PHPStan 0, Pint limpio, `migrate:fresh --seed` y smoke HTTP de summary, top-users y trend.
- Detalle y verificación: entrada del 2026-11-04 en `MEMORY.md`.

---

## Fase 4 — Testing, Calidad y Documentación

**Objetivo:** cerrar la etapa con la suite, el análisis estático y la documentación al día, sin regresiones.

- [x] `4.1` Repasar la cobertura Feature de todas las novedades y completar huecos: guarda del último admin, inmutabilidad de `code`, roles sin permisos, usuario inactivo, autoservicio de contraseña, reset administrativo, auditoría de logins/logouts/acciones, rankings con límite dinámico y menos datos que el límite.
- [x] `4.2` `composer analyse` en **0 errores** (PHPStan/Larastan nivel 5, sin baseline) y `pint --test` limpio en todos los archivos nuevos y modificados.
- [x] `4.3` Actualizar `AGENTS.md`: nuevos permisos y matriz 15/11/5, middleware `active`, flujo `must_change_password`, guarda del último administrador, esquema y convenciones de `audit_logs` (acciones, redacción, escritura tras commit), convenciones de estadísticas (solo `completed`, moneda base, fechas inclusivas, `limit` 10/100) y la regla de no usar servicios externos.
- [x] `4.4` Actualizar `README.md`: credenciales iniciales, `/auth/me`, cambio y reset de contraseña, tabla de rutas y permisos, sección de auditoría con filtros, sección de estadísticas con ejemplos `curl`, estados de la suite y troubleshooting de los nuevos `401/403/409/422`.
- [x] `4.5` Verificación integral en Docker: `migrate:fresh --seed` (sin ventas), suite completa, `composer analyse`, `pint --test`, `composer audit` y exportación OpenAPI (`composer docs:export` + validación con Redocly).
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
