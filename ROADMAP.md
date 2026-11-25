# ROADMAP — Plan de pruebas integral 2026-11-24

> **Propósito:** único plan de trabajo y registro de avance. Aquí vive el plan de pruebas integral y sus resultados. La guía operativa está en `AGENTS.md`, el historial en `MEMORY.md` y el uso en `README.md`; no dupliques esos contenidos aquí.
>
> **Regla de ejecución:** cada casilla es una micro-tarea con un commit atómico (`test:`, `fix:`, `chore:`, `docs:`) y se marca `[x]` solo después de verificar en Docker con `composer test`, `composer analyse`, `pint --test` y `composer audit`. Nunca incluir `.env`.

---

## 📌 Estado actual

- **Actualizado:** 2026-11-24.
- **Estado:** plan de pruebas integral cargado. Fase 0 cerrada; fases 1-6 pendientes de ejecución.
- **Repo:** limpio antes de cargar este plan. La fase 0 sustituye el plan de QA cerrado por este ciclo.
- **Línea base de código:** commit `c029bf5`; SQLite 524 tests / 3311 aserciones, PostgreSQL 524 / 3292, PHPStan 0 errores, Pint 381 archivos, `composer audit` limpio y OpenAPI válido (único aviso conocido `no-server-example.com`).
- **Regla de orden:** el roadmap se limpia y recibe el plan antes de ejecutar la primera prueba. Las fases posteriores solo marcan casillas y añaden resultados; no reescriben el plan.

---

## Fase 0 — Limpieza del roadmap y carga del plan (primero)

- [x] **0.1 Sustituir `ROADMAP.md` por este plan.** Borrar el plan de QA cerrado (fases 1-4 y su estado) y dejar únicamente el encabezado, la regla de ejecución, el estado «plan cargado, pendiente de ejecutar» y las casillas de las fases 0-6. Criterio: `cat ROADMAP.md` contiene el plan completo; no hay fases históricas ni contenido duplicado de `AGENTS.md`/`MEMORY.md`.
- [x] **0.2 Commit del plan antes de medir.** `git add ROADMAP.md && git commit -m "docs: reemplazar el plan de QA por el plan de pruebas integral"`. Criterio: commit atómico, repo limpio, `.env` fuera del commit; es el punto de partida del ciclo.

## Fase 1 — Preparación y línea base

- [x] **1.1 Estado del entorno.** `git status --short`, `git log --oneline -1`, `docker compose ps`, `wc -l MEMORY.md`, `docker compose exec -T app php -v`. Criterio: repo limpio en el commit de la fase 0, cuatro servicios `healthy`, PHP 8.3 con `pdo_pgsql`/`pdo_sqlite`.
- [x] **1.2 Base limpia.** `docker compose exec -T app php artisan migrate:fresh --seed`. Criterio: termina sin errores; `php artisan health:check --fresh` imprime `database: ok` y `cache: ok`; `GET http://localhost:8000/up` → 200 `{"status":"ok"}`. Aviso: esto reinicia los datos demo de desarrollo.
- [x] **1.3 Configuración desplegable.** `docker compose config -q` (dev) y `docker compose -f docker-compose.prod.yml --env-file .env.production config -q` (prod); `nginx -t` en los contenedores con y sin superposición TLS. Criterio: YAML y configs válidos.

## Fase 2 — Calidad estática y contrato

- [x] **2.1 Formato y análisis.** `./vendor/bin/pint --test`, `composer analyse`, `composer audit`. Criterio: Pint sin diff, PHPStan nivel 7 con 0 errores, `audit` sin avisos.
- [ ] **2.2 Arquitectura y configuración.** `./vendor/bin/pest --filter='ArchitectureTest|CorsConfigTest|TrustedProxyConfigTest|PasswordPolicyTest|Config|ScheduleTest|IndexesTest'`. Criterio: inyección en constructores, CORS/proxy/política de claves y scheduled tasks intactos.
- [ ] **2.3 OpenAPI.** `composer docs:export` y `npx @redocly/cli lint storage/app/openapi.json`. Criterio: 3.1.2 válido, 55 paths, único aviso tolerado `no-server-example.com`. Si Node no está disponible en el host, validar solo el JSON generado y anotar la limitación.
- [ ] **2.4 Inventario de rutas.** `php artisan route:list --path=api --json` y `php artisan route:list` (incluye `/up`, `/docs/api`, `/docs/api.json`). Criterio: sin rutas duplicadas ni ambiguas; la matriz de la fase 4 cubre todas las familias listadas.

## Fase 3 — Suite automática en los dos motores

- [ ] **3.1 SQLite (rápida).** `./vendor/bin/pest > /tmp/opencode/qa-2026-11-24-sqlite.log 2>&1` en segundo plano. Criterio: 524 tests / 3311 aserciones, 0 failures. No tocar archivos hasta que termine.
- [ ] **3.2 PostgreSQL real.** `./vendor/bin/pest -c phpunit-postgres.xml > /tmp/opencode/qa-2026-11-24-pgsql.log 2>&1`. Criterio: 524 tests / 3292 aserciones, 0 failures. Antes de rerunear, comprobar que no queda un `pest` huérfano de una ejecución cortada (corrompe la base `testing`) y que `postgres` sigue en la red con alias `postgres`.
- [ ] **3.3 Contraste.** Comparar ambos logs y el tiempo de ejecución. Criterio: mismos nombres de test; cualquier divergencia (cotejo, `numeric`, índices de expresión) se investiga antes de seguir.

## Fase 4 — Prueba HTTP de endpoints (stack de desarrollo)

Helper de token (sin `jq` en el host):

```bash
curl -s http://localhost:8000/api/auth/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"admin@caragency.test","password":"password"}' \
| docker compose exec -T -i app php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["token"]??"";'
```

- [ ] **4.1 Superficie pública.** `/up` → 200; `/docs/api` y `/docs/api.json` → 200 en local; `/api/cars` sin token → 401 con cuerpo JSON; ruta inexistente → 404 JSON. Criterio: `ForceJsonResponse` aplica; `/up` responde rápido y sin sesión.
- [ ] **4.2 Identidad.** login admin demo → 200 con token `id|car_...`; `/auth/me`, `/auth/refresh`, `/auth/logout` → 200; token ya revocado → 401. Login con clave errónea → 401. Con un usuario temporal: desactivarlo → 401 con token emitido antes; `reset-password` → flag `must_change_password` y clave temporal; `PUT /auth/password` → 200 y limpia el flag. Criterio: espaciar los logins para no agotar `login` 5/min; restaurar o borrar el usuario temporal al terminar.
- [ ] **4.3 Listados y filtros.** Recorrer los 16 listados con `?per_page=2&page=1` y `page=2`: cars, sales, users, branches, brands, car-models, car-types, categories, colors, currencies, payment-methods, taxes, countries, states, cities, roles. Criterio: 200, ids ascendientes entre páginas, `links`/`meta.links` conservan `per_page` y filtros. Casos puntuales: `cars?status=available&brand_id=…&vin=…` (prefijo, `%`, `_`, `!`, sin substring), `sales?from=…&to=…&status=completed&branch_id=…`, `users?name=…`, `audit-logs?action=…&from=…&to=…`. Comprobar conjuntos exactos de ids cuando el dataset de prueba lo permita.
- [ ] **4.4 CRUD y catálogos.** Para una marca, un país/estado/ciudad y una sucursal: crear → show → update → show → delete; además borrar un registro con FK → 409. Criterio: respuestas y códigos coherentes, `audit_logs` registra `catalog.*`/`location.*`/`branch.*` con actor, IP y valores antiguos/nuevos; los recursos creados se eliminan al final.
- [ ] **4.5 Ventas e inventario.** Crear coche `available` y una venta con impuestos activos, moneda base `exchange_rate=1` y `branch_id` del coche → 201 con importes a 2 decimales y coche `sold`. Rechazos: tasa `0`, tasa ≠ 1 en base, impuesto desactivado entre validación y servicio, sucursal ajena, coche soft-deleted, sin moneda base → 422/409 correspondientes; ninguna debe crear venta ni cambiar el coche. Cancelar la venta → coche `available` y stats invalidadas. Criterio: estados y totales coherentes en BD; la venta de prueba queda como dato demo o se documenta.
- [ ] **4.6 Reportes y estadísticas.** `/api/reports/cars`, `/api/stats/*` (8 endpoints, con y sin `branch_id`/rango/`limit`) → 200; `/api/reports/sales/pdf` y `/api/reports/cars/pdf` → 200 con `Content-Type: application/pdf` y magic `%PDF-`. Criterio: totales en moneda base, ranking estable y menos filas si no hay datos; un número excesivo de filas → 422 `ReportTooLargeException`; el PDF respeta `REPORTS_MAX_ROWS=300`.
- [ ] **4.7 Auditoría.** `/api/audit-logs` con filtros y paginación; show de una fila; `/api/audit-logs/export` → CSV con BOM UTF-8, celdas con prefijos `= + - @ \t \r \n` escapadas y mismos filtros que el listado; intentos de POST/PUT/DELETE sobre la ruta → 405. Criterio: append-only, 200 con `text/csv`, sin hidratación completa.
- [ ] **4.8 Errores, CORS, proxy y cabeceras.** Verificar 403 por permiso (usuario `vendedor` contra `/api/stats/*`), 409 de guarda del último admin y de FK, 500 no esperados → 0; preflight `OPTIONS` sin token → 200/204 y sin consumir `api-anon`; cabeceras `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`; `X-Forwarded-For` falsificada no altera la IP auditada (`TRUSTED_PROXIES` vacío en local). Criterio: contrato de errores JSON y cabeceras presentes en 200/401/404.
- [ ] **4.9 Limitadores (al final de la fase).** Anónimo: 30/min `api-anon` → 429; `api/auth/login`: 5/min por email+IP y 20/min por IP; autenticado: 120/min; `reports-pdf`: 5/min encima del cupo de reportes; `audit-export`: 10/min; `/up`: 30/min. Criterio: cada 429 incluye `Retry-After`; el tráfico autenticado no consume el cupo anónimo; al terminar, esperar a que se reseten las ventanas o reiniciar `nginx` antes de otras mediciones.

## Fase 5 — Producción y PaaS (verificación de despliegue)

- [ ] **5.1 Imagen y stack de producción aislado.** `docker compose -f docker-compose.prod.yml --env-file .env.production build app` y levantar con `-p car-agency-prod-test` (volúmenes propios); `migrate --force --seed` con `ADMIN_*`, `optimize`, `health:check`. Criterio: `/up` 200, login 200, `/docs/api` 403, actores `worker`/`scheduler` activos.
- [ ] **5.2 Humo en producción.** Repetir un subconjunto representativo: login, un listado, `stats/summary`, PDF, CSV, `/up` y un 429. Criterio: mismos códigos que en desarrollo; sin credenciales de superusuario en el contenedor `app`.
- [ ] **5.3 Imagen PaaS.** `docker build -f docker/render/Dockerfile.render -t car-agency-laravel-api:paas .`; generar `APP_KEY` con `--entrypoint php`; ejecutar con variables explícitas (`CORS_ALLOWED_ORIGINS`, `SESSION_DRIVER=file`, PostgreSQL de la red aislada, `RUN_MIGRATIONS=true`, `RUN_SEED=true` si aplica). Criterio: `/up` 200, login 200, `/api/cars` anónimo 401, `/docs/api` 403; parar y retirar el contenedor de prueba.
- [ ] **5.4 Limpieza.** `docker compose -p car-agency-prod-test down -v` y borrar imágenes/etiquetas temporales si no se reutilizarán. Criterio: `docker compose ps` del stack de desarrollo sigue healthy.

## Fase 6 — Cierre y registro

- [ ] **6.1 Marcar el roadmap.** Una vez verificadas las fases 1-5, marcar `[x]` sus casillas en `ROADMAP.md` y actualizar el apartado «Estado actual» con los recuentos reales. Criterio: no queda ninguna casilla sin marcar sin explicación.
- [ ] **6.2 Memoria.** Añadir una entrada fechada con el resultado, decisiones y gotchas de ejecución; verificar `wc -l MEMORY.md` ≤ 100 (93 + 1 = 94). Si aparece un bug no obvio, documentarlo ahí y abrir una tarea nueva en el roadmap.
- [ ] **6.3 Commit y entrega.** `git status --short`, `git diff --check`, `git diff`, `git add` de los archivos tocados y commit convencional; nunca `.env`. Criterio: repo limpio; no se ejecuta `git push` ni otras operaciones prohibidas.
- [ ] **6.4 Informe final.** Adjuntar rutas de los logs, recuentos de SQLite/PostgreSQL, resultados de Pint/PHPStan/audit/OpenAPI y la matriz HTTP ejecutada. Criterio: cada hallazgo tiene explicación, evidencia y destino (corregido en el ciclo o tarea nueva).

---

## Criterio de cierre del ciclo

- Fases 1-6 marcadas en `[x]`, cada una con su verificación y su commit atómico.
- Batería verde dentro de `app`: `./vendor/bin/pest`, `./vendor/bin/pest -c phpunit-postgres.xml`, `composer analyse`, `pint --test` y `composer audit`.
- Humo HTTP en desarrollo, producción y PaaS: `/up`, login, listados, estadísticas, reportes PDF/CSV, auditoría y rate limits.
- `composer docs:export` + `npx @redocly/cli lint storage/app/openapi.json` sin errores (se tolera el aviso `no-server-example.com`).
