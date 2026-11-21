# ROADMAP

> **Propósito:** único plan de trabajo y registro de avance del proyecto. Aquí viven objetivos, tareas y criterios de cierre. La guía operativa está en `AGENTS.md`, las lecciones en `MEMORY.md` y el uso en `README.md`; no dupliques esos contenidos aquí.
>
> **Regla de arquitectura vigente:** Laravel 12 sobre **PostgreSQL 17** (`C.UTF-8`). Sin servicios externos nuevos (SMTP, OAuth, Redis, brokers, almacenamiento gestionado) ni dependencias pesadas. La auditoría, las estadísticas, la caché y las colas siguen resolviéndose con tablas propias. El histórico de la migración MySQL→PostgreSQL está cerrado y documentado en `MEMORY.md`; no se reabre.

---

## 📌 Estado actual

- **Actualizado:** 2026-11-20.
- **Estado:** migración a PostgreSQL cerrada; plan vigente = análisis de seguridad, rendimiento y optimización de este mismo día. Ninguna tarea de este roadmap está empezada.
- **Repo:** limpio en `558400a`. Stack de desarrollo arriba (`app`, `nginx`, `postgres`, `scheduler`, todos `healthy`) y `/up` → 200.
- **Línea base de verificación:** 443 tests / 2 823 aserciones en SQLite y PostgreSQL, PHPStan nivel 7 sin errores, Pint limpio en 366 archivos, `composer audit` sin avisos, OpenAPI 3.1.2 válido (55 paths).
- **Nota de ejecución:** `composer test` agota el `process-timeout` de Composer en este entorno; para la suite completa usar `./vendor/bin/pest` o `./vendor/bin/pest -c phpunit-postgres.xml`.
- **Regla de ejecución:** cada micro-tarea es un commit atómico y solo se cierra tras verificar en Docker (`pest`, `analyse`, `pint --test`, `audit`). Prohibido `git push`, `git checkout`, `git branch`, `git merge`, `git rebase` y `git reset`; nunca se versiona `.env`.

---

## 🔎 Hallazgos de entrada

Resultado del análisis de 2026-11-20. Los IDs se conservan en las tareas para trazabilidad.

| ID | Prioridad | Hallazgo | Fase |
| :--- | :--- | :--- | :--- |
| S1 | Media | `/up` es público, no tiene límite de tasa y ejecuta ~4 consultas por petición | 1 |
| S2 | Media (validar) | `TRUSTED_PROXIES=*` en la vía PaaS permite falsificar `X-Forwarded-For` | 3 |
| S3 | Baja-media | La imagen `prod` tiene `display_errors=1` y `log_errors=0` | 1 |
| S4 | Baja | Los dumps de backup quedan con el `umask` del host (típicamente `644`) | 4 |
| S5 | Informativa | `GET /api/audit-logs/export` no tiene limitador propio | 1 |
| S6 | Informativa | `user_id` de las ventas viene del payload; revisar si debe ser `Auth::id()` | 2 |
| P1 | Media | Cancelar una venta con el coche en soft delete responde `404` | 2 |
| P2 | Media (concurrencia) | `updateCar` comprueba ventas completadas sin transacción ni bloqueo | 2 |
| P3 | Baja-media | PDF generados en el worker FPM: hasta ~216 MB y ~8 s por reporte | 5 |
| P4 | Baja | Los 16 listados solo validan `per_page`; no hay filtros indexados | 5 |
| P5 | Informativa | Los 8 endpoints de estadísticas recalculan agregados en cada petición | 5 |
| P6 | Informativa | `audit:prune` borra en una sola sentencia (bloqueos y bloat) | 4 |

**Ya cubierto y fuera de alcance:** RBAC por permisos, `active` y `password.changed`, tokens Sanctum con expiración/revocación, limitadores de login y anónimo, CORS/HSTS condicionados, auditoría append-only con redacción, rol de BD no superusuario, eager loads fijados por tests, filtros de fecha sargables, imágenes no root, OPcache y gzip selectivo.

---

## Fase 1 — Hardening HTTP y superficie pública

**Objetivo:** cerrar los puntos expuestos a anónimos o que pueden filtrar información sin tocar el contrato de la API.

- [ ] `1.1` (S1) Añadir limitador `health` en `config/rate_limits.php` y `AppServiceProvider` (30/min por IP, variable `RATE_LIMIT_HEALTH`) y aplicarlo a la ruta `/up` de `bootstrap/app.php` sin sacarla del healthcheck de Nginx/Docker.
- [ ] `1.2` (S1) Cachear el resultado de `HealthChecker::check()` 5-10 s con `Cache::remember`, manteniendo `health:check` en lectura directa (o con opción `--fresh`) para no enmascarar una recuperación. Añadir test que verifique una sola comprobación tras dos peticiones.
- [ ] `1.3` (S1) Cubrir `/up` en `RateLimitTest`: N peticiones anónimas → 200 y la siguiente → 429; documentar en `README.md` el cupo y la excepción para la IP interna del proxy si se aplica.
- [ ] `1.4` (S3) Crear un ini exclusivo del target `prod` con `display_errors=Off`, `log_errors=On`, `error_log=/dev/stderr`, `html_errors=Off` y copiarlo en `docker/php/Dockerfile`. Verificar con `docker run --rm --entrypoint php car-agency-laravel-api:prod -r '...'` que los valores cambiaron y que `dev` sigue revalidando el código.
- [ ] `1.5` (S5) Aplicar un limitador `audit-export` (o reutilizar `reports`) a `routes/api.php:130-132` y añadir test de `429` tras agotar el cupo con token de administrador.
- [ ] `1.6` Registrar en `MEMORY.md` el gotcha del limitador de `/up` (fuera del grupo `api`, `ThrottleAnonymousApi` no lo ve) y actualizar `AGENTS.md` con las variables nuevas.

**Criterio de cierre:** `/up` respondiendo 200 al healthcheck y 429 al abuso; sin `display_errors` en la imagen `prod`; exportación de auditoría limitada; suite, análisis, formato y auditoría limpios.

---

## Fase 2 — Integridad de ventas e inventario

**Objetivo:** eliminar las dos incoherencias transaccionales y cerrar la decisión de atribución de ventas.

- [ ] `2.1` (P1) `SaleService::cancelSale()`: cargar el coche con `Car::withTrashed()->whereKey(...)->lockForUpdate()->firstOrFail()` y decidir explícitamente si se restaura (`restore()`) o se omite el cambio de estado cuando está borrado lógicamente. Añadir test de regresión: vender → soft-delete del coche → cancelar → 200, venta `cancelled` y comportamiento del coche documentado.
- [ ] `2.2` (P2) Envolver `CarService::updateCar()` en `DB::transaction`, cargar el coche con `lockForUpdate()` y re-evaluar la matriz de estados y `hasCompletedSale()` **después** del bloqueo, antes de escribir. Sin cambiar respuestas ni eager loads.
- [ ] `2.3` (P2) Añadir test de concurrencia controlada (dos conexiones o transacción anidada con barrera): `updateCar` no puede sobrescribir un coche cuya venta hizo commit mientras tanto. Si el test resulta inviable con SQLite, marcar la validación definitiva en el job `postgres` del CI.
- [ ] `2.4` (S6) Decidir el origen de `user_id` en `StoreSaleRequest`/`SaleService`: (a) sobrescribir con `Auth::id()` para que la venta siempre pertenezca a quien opera, o (b) conservar el payload y documentar el caso de uso «venta en nombre de otro» con validación de rol. Aplicar la opción elegida con test y actualizar OpenAPI/README.
- [ ] `2.5` Verificar que la auditoría de ventas sigue escribiéndose **después** del commit y que los tests de `QueryCountTest` no cambian su número de consultas.

**Criterio de cierre:** cancelación con coche borrado y edición concurrente cubiertas por tests; atribución de `user_id` decidida y documentada; sin regresión en reportes ni en estadísticas.

---

## Fase 3 — Confianza en proxies y procedencia de IP

**Objetivo:** decidir con evidencia si la configuración PaaS permite falsificar la IP del cliente y endurecerla donde haga falta.

- [ ] `3.1` (S2) Verificar en la plataforma objetivo (Render/Railway) si el ingress **sobrescribe** `X-Forwarded-For` o si lo añade a la cadena. Dejar la evidencia (cabeceras observadas) en `MEMORY.md`.
- [ ] `3.2` (S2) Si no se garantiza la sobrescritura: sustituir `TRUSTED_PROXIES=*` por la IP/rango real del proxy en la documentación PaaS y ajustar el `Caddyfile`/Nginx para no reenviar una cabecera controlada por el cliente. Si se garantiza, documentar por qué `*` es seguro en ese despliegue concreto.
- [ ] `3.3` Añadir/ajustar `TrustedProxyTest`: cabecera XFF falsificada desde origen no confiable → la IP de auditoría y las claves de `login_ip`/`api-anon` usan la IP real; desde proxy confiable → se respeta la IP reenviada.
- [ ] `3.4` Revisar `README.md` y `AGENTS.md` para que las dos vías (VPS con proxy externo y PaaS) tengan una única recomendación coherente de `TRUSTED_PROXIES` y de reenvío de cabeceras.

**Criterio de cierre:** comportamiento de XFF verificado y cubierto por test; ninguna configuración publicada admita spoofing sin una justificación documentada.

---

## Fase 4 — Secretos y operación de la auditoría

**Objetivo:** reducir exposición de dumps y hacer predecible la poda de datos antiguos.

- [ ] `4.1` (S4) Añadir `umask 077` al inicio de `docker/scripts/backup-postgres.sh` y `chmod 600 "$FILE"` tras el `mv`. Verificar con `stat -c %a backups/*.dump` tras un backup real.
- [ ] `4.2` (P6) Reescribir `PruneAuditLogs::handle()` para borrar por lotes (`chunkById` con `limit`, o `DELETE ... WHERE id IN (...)` en bucle hasta que no haya filas), conservando `--days` y el mensaje de salida. Añadir test con varios cientos de filas antiseguimiento (`freezeTime`/fechas antiguas) y comprobar que no se borra nada dentro de la retención.
- [ ] `4.3` Revisar `routes/console.php`: la programación diaria a las 03:00 y `withoutOverlapping()` no cambian; verificar con `schedule:list`.
- [ ] `4.4` Registrar en `MEMORY.md` el hallazgo del `umask` y el motivo del borrado por lotes.

**Criterio de cierre:** dump con permisos `600`; poda probada con datos antiguos y recientes; `schedule:list` sin cambios.

---

## Fase 5 — Rendimiento de consultas, reportes y agregados

**Objetivo:** atacar el coste de PDFs, listados y estadísticas sin añadir servicios externos.

- [ ] `5.1` (P3) Medir con un dataset realista (3 000 ventas) el coste actual del PDF y decidir: (a) mantener descarga síncrona con límite y pool dedicado, o (b) encolar la generación con `jobs` y servir el archivo desde `storage/app`. Documentar la decisión antes de implementar; cualquier opción conserva `REPORTS_MAX_ROWS` y los limitadores `reports`/`reports-pdf`.
- [ ] `5.2` (P4) Inventariar los 16 listados y añadir filtros indexados por dominio: `status`, `branch_id`, rangos de fecha y búsqueda de texto. Validar en `postgres:17-alpine` la disponibilidad de `pg_trgm` y, si procede, crear índices de expresión `lower(...)`; actualizar FormRequest, servicios, `ApiDocumentationParameters`, `PaginationTest`, `QueryCountTest` y tests nuevos de filtro.
- [ ] `5.3` (P5) Cachear los 8 agregados de `/api/stats/*` 30-60 s con clave por combinación de filtros (`start_date`, `end_date`, `branch_id`, `limit`) e invalidar al crear/cancelar venta. Comprobar que el cambio no altera las respuestas ni el número de consultas en frío, y añadir test de acierto de caché.
- [ ] `5.4` Verificar con `EXPLAIN (ANALYZE, BUFFERS)` sobre el dataset de 3 000 filas que los nuevos filtros usan índices y no introducen `Seq Scan` donde antes no lo había.

**Criterio de cierre:** decisión de PDF aplicada y medida; filtros y caché con tests; contrato OpenAPI ampliado sin romper el existente; planes de ejecución documentados en `MEMORY.md`.

---

## Fase 6 — Verificación integral y cierre

**Objetivo:** demostrar que todo lo anterior es reproducible y que la API sigue siendo la misma.

- [ ] `6.1` Suite completa en Docker con `./vendor/bin/pest` (SQLite) y `./vendor/bin/pest -c phpunit-postgres.xml` (PostgreSQL), más `composer analyse`, `./vendor/bin/pint --test` y `composer audit`.
- [ ] `6.2` Stack de producción con volumen nuevo: `build app`, `up -d`, `migrate --force --seed`, `optimize`, `/up`, login y un PDF/CSV de reporte. Imagen PaaS: build y arranque con `APP_KEY` y PostgreSQL, `/up` y `/docs/api` según `DOCS_ENABLED`.
- [ ] `6.3` Smoke HTTP de regresión: login, `/auth/me`, estadísticas, reportes, exportación CSV, auditoría, `/up` y los casos nuevos de las fases 1-3.
- [ ] `6.4` `composer docs:export` + `npx @redocly/cli lint storage/app/openapi.json`; comprobar que los nuevos parámetros/limitadores están documentados.
- [ ] `6.5` Cierre documental: tareas marcadas, `README.md`/`AGENTS.md`/`MEMORY.md` actualizados (entrada fechada por fase) y un commit atómico por micro-tarea. `wc -l MEMORY.md` ≤ 100.
- [ ] `6.6` Decisión pendiente del análisis anterior: eliminar el volumen huérfano `api_laravel_car_agency_mysql-data` **solo con confirmación explícita del usuario**; mientras tanto, dejarlo documentado como sin uso.

**Criterio de cierre:** suites verdes en ambos motores, builds `prod` y PaaS correctos, smoke y OpenAPI válidos, documentación coherente y `git status` limpio.

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
