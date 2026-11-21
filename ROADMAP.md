# ROADMAP

> **Propósito:** único plan de trabajo y registro de avance del proyecto. Aquí viven objetivos, tareas y criterios de cierre. La guía operativa está en `AGENTS.md`, las lecciones en `MEMORY.md` y el uso en `README.md`; no dupliques esos contenidos aquí.
>
> **Regla de arquitectura vigente:** Laravel 12 sobre **PostgreSQL 17** (`C.UTF-8`). Sin servicios externos nuevos (SMTP, OAuth, Redis, brokers, almacenamiento gestionado) ni dependencias pesadas. Auditoría, estadísticas, caché y colas se resuelven con tablas propias. La migración MySQL→PostgreSQL está cerrada y su histórico vive en `MEMORY.md`.

---

## 📌 Estado actual

- **Actualizado:** 2026-11-21.
- **Estado:** plan vigente = hardening, integridad y optimización derivado del análisis del 2026-11-21. **Ninguna tarea iniciada**; cada hallazgo se volvió a verificar contra el código en esa fecha.
- **Repo:** limpio en `a29c759`. Stack de desarrollo arriba (`app`, `nginx`, `postgres`, `scheduler`, todos `healthy`) y `/up` → 200.
- **Línea base:** 443 tests / 2 823 aserciones en SQLite y PostgreSQL, PHPStan nivel 7 sin errores, Pint limpio en 366 archivos, `composer audit` sin avisos, OpenAPI 3.1.2 válido (55 paths).
- **Nota de ejecución:** `composer test` agota el `process-timeout` de Composer en este entorno; para la suite completa usar `./vendor/bin/pest` o `./vendor/bin/pest -c phpunit-postgres.xml`.
- **Regla de ejecución:** cada micro-tarea es un commit atómico y solo se cierra tras verificar en Docker (`pest`, `analyse`, `pint --test`, `audit`). Prohibido `git push`, `git checkout`, `git branch`, `git merge`, `git rebase` y `git reset`; nunca se versiona `.env`.

### Decisiones cerradas

- **S6:** el `user_id` de la venta se toma siempre de `Auth::id()`; se elimina del payload.
- **P3:** PDF síncrono con límite de filas menor (no hay worker en la vía PaaS).
- **P4:** filtros indexados diferidos a una fase opcional con gate de volumen.
- **S2:** la validación de `X-Forwarded-For` se hace sobre **Render**.
- **P1:** al cancelar una venta cuyo coche está en soft delete, la venta se cancela y el coche **no** se restaura; el borrado lógico es una decisión independiente.

---

## 🔎 Hallazgos de entrada

Resultado del análisis del 2026-11-21. Los IDs se conservan en las tareas para trazabilidad.

| ID | Prioridad | Hallazgo | Evidencia | Fase |
| :--- | :--- | :--- | :--- | :--- |
| S1 | Baja-media | `/up` es público, no tiene límite de tasa y ejecuta base + caché en cada petición | `bootstrap/app.php:39`, `app/Services/Health/HealthChecker.php` | 1 |
| S3 | Alta-media | La imagen `prod` hereda `display_errors=1` y `log_errors=0` | `docker/php/php.ini`, target `prod` de `docker/php/Dockerfile` | 1 |
| S5 | Baja | `GET /api/audit-logs/export` no tiene limitador propio | `routes/api.php:131` | 1 |
| S6 | Alta-media | El `user_id` de las ventas viene del payload y permite atribuir una venta a otro usuario | `app/Http/Requests/Sales/Sale/StoreSaleRequest.php:37`, `app/Services/Sales/SaleService.php:127` | 2 |
| P1 | Media | Cancelar una venta con el coche en soft delete responde `404` | `app/Services/Sales/SaleService.php:161` | 2 |
| P2 | Baja | `updateCar` comprueba ventas completadas sin transacción ni bloqueo | `app/Services/Cars/CarService.php:75` | 2 |
| P7 | Baja | No hay índice único parcial que garantice una sola moneda base; la validación es TOCTOU | `app/Http/Requests/Sales/Currency/StoreCurrencyRequest.php:43`, migración de `currencies` | 2 |
| S2 | Media (validar) | `TRUSTED_PROXIES=*` en la vía PaaS permite falsificar `X-Forwarded-For` | `.env.production.example:63`, documentación PaaS de `README.md` | 3 |
| S4 | Media-baja | Los dumps de backup quedan con el `umask` del host (típicamente `644`) | `docker/scripts/backup-postgres.sh` | 4 |
| P6 | Baja | `audit:prune` borra en una sola sentencia (bloqueos y bloat) | `app/Console/Commands/PruneAuditLogs.php` | 4 |
| P3 | Media | PDF generados en el worker FPM: hasta ~216 MB y ~8 s por reporte | `config/reports.php:19`, `app/Services/Reports/SaleReportService.php:89` | 5 |
| P5 | Media-baja | Los 8 endpoints de estadísticas recalculan agregados en cada petición | `app/Services/Stats/*` | 5 |
| P4 | Baja | Los 16 listados solo validan `per_page`; no hay filtros indexados | `app/Services/*`, `app/Http/Requests/*/Index*Request.php` | 6 (diferida) |

**Ya cubierto y fuera de alcance:** RBAC por permisos con middleware `permission`, `active` y `password.changed`; tokens Sanctum con expiración/revocación; limitadores de login y anónimo; CORS/HSTS condicionados; auditoría append-only con redacción; rol de BD no superusuario; eager loads fijados por `QueryCountTest`; filtros de fecha sargables; imágenes no root; OPcache; gzip selectivo; cabeceras de seguridad y CSP de la documentación.

---

## Fase 1 — Superficie pública y errores

**Objetivo:** cerrar los puntos expuestos a anónimos o que pueden filtrar información sin tocar el contrato de la API.

- [x] `1.1` (S1) Añadir el limitador `health` en `config/rate_limits.php` (30/min por IP, `RATE_LIMIT_HEALTH`) y registrarlo en `AppServiceProvider`; aplicarlo a la ruta `/up` de `bootstrap/app.php` con `throttle:health`, sin sacarla del healthcheck de Nginx/Docker.
- [x] `1.2` (S1) Cachear `HealthChecker::check()` 5-10 s. El comando `health:check` debe poder leer en directo (opción `--fresh`) para no enmascarar una recuperación. Si el store de caché falla, la comprobación debe devolver `false`, no `500`.
- [ ] `1.3` (S3) Crear un ini exclusivo del target `prod` con `display_errors=Off`, `log_errors=On`, `error_log=/dev/stderr`, `html_errors=Off` y `expose_php=Off`; copiarlo solo en la etapa `prod` de `docker/php/Dockerfile`. Verificar con `docker run --rm --entrypoint php <imagen> -i` que los valores cambiaron y que `dev` sigue revalidando el código.
- [ ] `1.4` (S5) Añadir el limitador `audit-export` (10/min por usuario, `RATE_LIMIT_AUDIT_EXPORT`) y aplicarlo a `routes/api.php:131`.
- [ ] `1.5` Tests: `RateLimitTest` para `/up` (N peticiones → 200 y la siguiente → 429) y para el export (429 tras agotar el cupo con token de administrador). Documentar los cupos en `README.md`/`AGENTS.md` y registrar en `MEMORY.md` el gotcha de `/up` fuera del grupo `api`.

**Criterio de cierre:** `/up` responde 200 al healthcheck y 429 al abuso; la imagen `prod` no muestra errores; la exportación de auditoría está limitada; suite, análisis, formato y auditoría limpios.

---

## Fase 2 — Integridad de ventas e inventario

**Objetivo:** eliminar las incoherencias transaccionales y cerrar la atribución de ventas y la unicidad de la moneda base.

- [ ] `2.1` (P1) `SaleService::cancelSale()`: cargar el coche con `Car::withTrashed()->whereKey(...)->lockForUpdate()->firstOrFail()`. Si el coche está borrado lógicamente, cancelar la venta sin cambiar su estado ni restaurarlo; si está activo, devolverlo a `available` como hoy. Test de regresión: vender → soft-delete del coche → cancelar → 200, venta `cancelled` y coche todavía `trashed`.
- [ ] `2.2` (P2) Envolver `CarService::updateCar()` en `DB::transaction`, cargar el coche con `lockForUpdate()` y re-evaluar `hasCompletedSale()` y la matriz de estados **después** del bloqueo, antes de escribir. La auditoría sigue escribiéndose después del commit. Test de concurrencia controlada; si SQLite no lo permite, validarlo en el job `postgres` del CI.
- [ ] `2.3` (P7) Migración con índice único parcial `currencies (is_base) WHERE is_base` (válido en PostgreSQL y SQLite vía `DB::statement`). Capturar la violación (`SQLSTATE 23505`) y responder `409` con una excepción de dominio. Mantener la validación amable de los FormRequest y añadir test de segunda moneda base (API `409` y escritura directa rechazada). Verificar que los datos existentes no tengan duplicados antes de crear el índice.
- [ ] `2.4` (S6) Quitar `user_id` de las reglas de `StoreSaleRequest` y del DTO `SaleData`; en `SaleService` fijar `user_id` con `Auth::id()`. Actualizar tests que enviaban `user_id`, ejemplos de OpenAPI y `README.md`. Test: aunque el payload incluya el ID de otro usuario, la venta queda a nombre del actor.
- [ ] `2.5` Verificar que la auditoría de ventas sigue escribiéndose después del commit y que `QueryCountTest` no cambia su número de consultas.

**Criterio de cierre:** cancelación con coche borrado y edición concurrente cubiertas por tests; atribución de ventas resuelta; una sola moneda base garantizada por la base; sin regresión en reportes ni estadísticas.

---

## Fase 3 — Confianza en proxies (Render)

**Objetivo:** decidir con evidencia si la configuración PaaS permite falsificar la IP del cliente y endurecerla donde haga falta.

- [ ] `3.1` (S2) Capturar en un despliegue de staging de Render la cadena `X-Forwarded-For` y compararla con la IP pública real del cliente (log temporal o cabecera de diagnóstico). Dejar la evidencia en `MEMORY.md`.
- [ ] `3.2` (S2) Si Render **sobrescribe** la cabecera, documentar por qué `TRUSTED_PROXIES=*` es seguro en ese despliegue. Si la **añade** a la cadena, fijar el rango/proxy real o confiar solo en el último salto mediante middleware; nunca dejar `*` sin justificación.
- [ ] `3.3` Añadir/ajustar `TrustedProxyTest`: XFF falsificado desde un origen no confiable → la IP de auditoría y las claves de `login_ip`/`api-anon` usan la IP real; desde un proxy confiable → se respeta la IP reenviada.
- [ ] `3.4` Revisar `README.md` y `AGENTS.md` para que VPS (proxy externo) y Render tengan una única recomendación coherente de `TRUSTED_PROXIES` y reenvío de cabeceras.

**Criterio de cierre:** comportamiento de XFF verificado y cubierto por test; ninguna configuración publicada admite spoofing sin justificación documentada.

---

## Fase 4 — Operación: backups y poda

**Objetivo:** reducir la exposición de los dumps y hacer predecible la poda de datos antiguos.

- [ ] `4.1` (S4) Añadir `umask 077` al inicio de `docker/scripts/backup-postgres.sh` y `chmod 600 "$FILE"` tras el `mv`. Verificar con `stat -c '%a %n' backups/*.dump` tras un backup real.
- [ ] `4.2` (P6) Reescribir `PruneAuditLogs::handle()` para borrar por lotes de forma portable (por ejemplo, `whereIn('id', ...->limit(1000)->pluck('id'))->delete()` en bucle hasta que no queden filas), conservando `--days` y el mensaje de salida. Test con varios cientos de filas antiguas y recientes: no se borra nada dentro de la retención.
- [ ] `4.3` Verificar `routes/console.php`: la programación diaria a las 03:00 y `withoutOverlapping()` no cambian; comprobar con `schedule:list` y registrar el hallazgo en `MEMORY.md`.

**Criterio de cierre:** dump con permisos `600`; poda probada con datos antiguos y recientes; `schedule:list` sin cambios.

---

## Fase 5 — Rendimiento de reportes y agregados

**Objetivo:** reducir el coste de los PDFs y de las estadísticas sin añadir servicios externos.

- [ ] `5.1` (P3) Medir con un dataset realista (3 000 ventas en la base `testing`) la memoria pico y el tiempo del PDF actual. Guardar la medición en `MEMORY.md` y fijar el límite con margen para no superar ~256 MB.
- [ ] `5.2` (P3) Bajar `REPORTS_MAX_ROWS` al valor medido (~250-300) en `config/reports.php` y actualizar `README.md`/`AGENTS.md`. Mantener los limitadores `reports`/`reports-pdf`.
- [ ] `5.3` (P5) Cachear los 8 agregados de `/api/stats/*` 30-60 s con clave por combinación de filtros (`start_date`, `end_date`, `branch_id`, `limit`) y una versión de caché (`stats:version`) que se incremente al crear o cancelar una venta. Test de acierto de caché y de invalidación.
- [ ] `5.4` Verificar con `EXPLAIN (ANALYZE, BUFFERS)` sobre el dataset de 3 000 filas que las consultas siguen usando los índices de estadísticas; documentar en `MEMORY.md`.

**Criterio de cierre:** PDF medido y con límite seguro; caché de estadísticas con tests de acierto e invalidación; planes de ejecución documentados.

---

## Fase 6 — Filtros indexados de listados (diferida)

**Objetivo:** preparar los listados para volúmenes mayores sin romper el contrato actual.

> **Gate:** no iniciar esta fase sin decisión explícita del usuario o evidencia de que el volumen actual lo justifica. No bloquea las fases 1-5 ni el cierre.

- [ ] `6.1` (P4) Inventariar los 16 listados y priorizar por uso real: `status`, `branch_id`, rangos de fecha y búsqueda de texto.
- [ ] `6.2` (P4) Añadir los filtros por dominio en FormRequest y servicios, manteniendo `per_page`, el orden determinista y `withQueryString()`.
- [ ] `6.3` (P4) Actualizar `ApiDocumentationParameters`, `PaginationTest`, `QueryCountTest` y añadir tests de filtro. Validar `pg_trgm` en PostgreSQL si se opta por búsqueda de texto.
- [ ] `6.4` (P4) Verificar con `EXPLAIN (ANALYZE, BUFFERS)` que los nuevos filtros usan índices y no introducen `Seq Scan` donde antes no lo había.

**Criterio de cierre:** filtros documentados y probados; contrato OpenAPI ampliado sin romper el existente; planes de ejecución documentados.

---

## Fase 7 — Verificación integral y cierre

**Objetivo:** demostrar que todo lo anterior es reproducible y que la API sigue siendo la misma.

- [ ] `7.1` Suite completa en Docker con `./vendor/bin/pest` (SQLite) y `./vendor/bin/pest -c phpunit-postgres.xml` (PostgreSQL), más `composer analyse`, `./vendor/bin/pint --test` y `composer audit`.
- [ ] `7.2` Stack de producción con volumen nuevo: `build app`, `up -d`, `migrate --force --seed`, `optimize`, `/up`, login y un PDF/CSV de reporte. Imagen PaaS: build y arranque con `APP_KEY` y PostgreSQL, `/up` y `/docs/api` según `DOCS_ENABLED`. Si hay staging de Render, smoke equivalente.
- [ ] `7.3` Smoke HTTP de regresión: login, `/auth/me`, estadísticas, reportes, exportación CSV, auditoría, `/up` y los casos nuevos de las fases 1-3.
- [ ] `7.4` `composer docs:export` + `npx @redocly/cli lint storage/app/openapi.json`; comprobar que los nuevos parámetros y limitadores están documentados.
- [ ] `7.5` Cierre documental: tareas marcadas, `README.md`/`AGENTS.md`/`MEMORY.md` actualizados (entrada fechada por fase) y un commit atómico por micro-tarea. `wc -l MEMORY.md` ≤ 100.

**Criterio de cierre:** suites verdes en ambos motores, builds `prod` y PaaS correctos, smoke y OpenAPI válidos, documentación coherente y `git status` limpio.

---

## 📦 Pendientes operativos

- **Volumen `api_laravel_car_agency_mysql-data`:** eliminar **solo con confirmación explícita del usuario**. Mientras tanto, queda documentado como sin uso.
- **CI en GitHub:** la matriz PHP 8.2/8.3/8.4 y el job de PostgreSQL los ejecuta el usuario tras el `push`; el agente no hace `push`.

---

## Verificación

```bash
# Desarrollo
docker compose config
docker compose up -d --build
docker compose exec -T app php artisan migrate:fresh --seed
docker compose exec -T app ./vendor/bin/pest
docker compose exec -T app ./vendor/bin/pest -c phpunit-postgres.xml
docker compose exec -T app composer analyse
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app composer audit
docker compose exec -T app php artisan health:check
docker compose exec -T app php artisan schedule:list
curl -i http://localhost:8000/up

# Operación
./docker/scripts/backup-postgres.sh backups
stat -c '%a %n' backups/*.dump
./docker/scripts/restore-postgres.sh backups/<archivo>.dump --confirm
docker compose logs postgres

# Producción y PaaS
docker compose -f docker-compose.prod.yml --env-file .env.production config
docker compose -f docker-compose.prod.yml --env-file .env.production build app
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
docker build -f docker/render/Dockerfile.render -t car-agency-laravel-api:paas .

# Contrato
docker compose exec -T app composer docs:export
npx @redocly/cli lint storage/app/openapi.json
```

> `docker compose`, los builds y `npx` se ejecutan en el host; el resto de comandos van dentro de `app` con `-T`. La imagen `prod` solo se construye después de `build app` porque el Dockerfile de Nginx parte de ella.

---

## Convenciones de ejecución

- **Un commit por micro-tarea**, con Conventional Commits: `fix(security):`, `fix(sales):`, `fix(cars):`, `perf(stats):`, `chore(ops):`, `docs:`, `test:`.
- Antes de cada commit: `./vendor/bin/pest`, `composer analyse`, `./vendor/bin/pint --test` y `composer audit` en Docker.
- No se commitea `.env`, `vendor/`, `node_modules/` ni artefactos de build.
- Cada fase cerrada añade una entrada fechada en `MEMORY.md` (≤ 100 líneas) y actualiza `README.md`/`AGENTS.md` solo si cambia el uso o una norma operativa.
