# ROADMAP — Ciclo de QA 2026-11-22

> **Propósito:** único plan de trabajo y registro de avance. Aquí viven los hallazgos del QA integral y las tareas de corrección. La guía operativa está en `AGENTS.md`, el historial en `MEMORY.md` y el uso en `README.md`; no dupliques esos contenidos aquí.
>
> **Regla de ejecución:** cada casilla es una micro-tarea con un commit atómico (`feat:`, `fix:`, `refactor:`, `chore:`, `docs:`) y se marca `[x]` solo después de verificar en Docker con `composer test`, `composer analyse`, `pint --test` y `composer audit`. Nunca incluir `.env`.

---

## 📌 Estado actual

- **Actualizado:** 2026-11-23.
- **Estado:** QA integral ejecutado sobre el estado cerrado del 2026-11-22. Plan de corrección en curso: fase 1 completada, fase 2 pendiente.
- **Repo:** limpio al iniciar el QA. Este commit sustituye el roadmap anterior (plan de hardening cerrado) por el plan de QA.
- **Línea base verde:** SQLite 491 tests / 3061 aserciones, PostgreSQL 491 / 3049, PHPStan 0 errores, Pint 380 archivos, `composer audit` sin avisos, OpenAPI válido (único aviso conocido `no-server-example.com`), humo HTTP de desarrollo 20/20 códigos esperados y `/up` → 200.
- **Hallazgo P0 reproducido:** la imagen PaaS siguiendo el `docker run` del README responde `/up` → **500** si falta `CORS_ALLOWED_ORIGINS`.

## Hallazgos comprobados y descartados

No son fallos; se documentan para no reabrirlos:

- El desfase de `/api/stats/*` tras mutar coches o usuarios está declarado en `README.md:587-592` (TTL de 45 s); la invalidación solo por ventas es decisión vigente.
- El demo PaaS con SQLite funciona: `php artisan migrate` crea `storage/local.sqlite` aunque no exista (verificado en la imagen).
- El reporte de ventas incluye canceladas por decisión documentada en `README.md:473`.

---

## Fase 1 — P0: arranque y trazabilidad de IP

- [x] **1.1 PaaS sin CORS arranca en 500.** `docker/render/Dockerfile.render:27` fija `production` y `config/cors.php` decide `*`, lo que `CorsPolicy::assertAllowedOrigins()` rechaza en cada petición HTTP. `README.md:1114-1124` y `README.md:1156-1184` no incluyen `CORS_ALLOWED_ORIGINS`. Añadir la variable a ambos ejemplos y validarla en `docker/render/entrypoint.sh` antes de `exec`. Criterio: el `docker run` literal del README devuelve `/up` → 200 sin pasar la variable a mano.
- [x] **1.2 `TRUSTED_PROXIES` no coincide con la red del compose.** `.env.production.example:67`, `README.md:828,928,979` usan `172.28.0.0/16`, pero `docker-compose.prod.yml:122-124` no fija subnet (en este entorno la red dev es `172.18.0.0/16`). Fijar `ipam.config[0].subnet: 172.28.0.0/16` en la red `car-agency-prod` y corregir el texto del README que afirma que ya está incluida. Criterio: `X-Forwarded-For` y `X-Forwarded-Proto` de Nginx llegan a `ClientIpResolver` y a la auditoría.

## Fase 2 — P1: contrato OpenAPI e integridad financiera

- [x] **2.1 `POST /auth/login` no documenta 401.** `tests/Feature/Docs/OpenApiDocumentTest.php:106-108` exige lo contrario, pero `LoginSecurityTest.php:22,34` demuestra el 401 real y `README.md:730` afirma que está cubierto. Añadir 401 con esquema JSON al export y corregir test y README.
- [x] **2.2 Faltan 409 reales en OpenAPI.** `ApiDocumentationResponses::canConflict()` (`:97-104`) solo cubre cancelación de ventas y borrados con FK; `LastAdminException` responde 409 en `PUT`/`DELETE /users/{user}` (`UserAdminGuard.php:65`, `bootstrap/app.php:93`) y `MissingBaseCurrencyException` en `POST /sales` (`SaleService.php:147`). Ampliar `canConflict()` y las aserciones de `OpenApiOperationsTest.php:29-31`.
- [x] **2.3 Pago en moneda base con tasa distinta de 1.** `README.md:373-375` exige tasa `1`, pero `StoreSaleRequest.php:31,39` no valida la combinación y `SaleService.php:142` multiplica igual. Rechazar con 422 cuando `payment_currency_id` sea la base y `exchange_rate !== 1`. Criterio: test de `422` y otro de venta válida en base.
- [ ] **2.4 `car_id` de coche borrado lógicamente da 404.** `StoreSaleRequest.php:36` usa `Rule::exists('cars','id')` sin `deleted_at` y `SaleService.php:101` hace `firstOrFail()`. Añadir `whereNull('deleted_at')` a la regla o traducir el `ModelNotFound` a `CarNotAvailableException` (422).
- [ ] **2.5 Escape CSV incompleto.** `AuditQueryService.php:126` no escapa el salto de línea (`\n`), que OWASP incluye junto a `= + - @ \t \r`. Añadir `\n` al patrón y cubrir los seis prefijos más el BOM en `AuditLogExportTest.php:50-71`; corregir `README.md:1244`.
- [x] **2.6 La superposición TLS no reenvía la IP del cliente.** `docker/nginx/tls.conf:72-88` declara `X-Forwarded-Proto` pero no `X-Forwarded-For`, a diferencia de `default.conf:65`. Añadir `fastcgi_param HTTP_X_FORWARDED_FOR $remote_addr;` (una sola IP, sobrescribe la del cliente).
- [ ] **2.7 Desbordamiento numérico → 500.** `exchange_rate` (`decimal(10,2)`) y `cars.price` (`decimal(15,2)`) no tienen `max` en `StoreSaleRequest.php:31` ni en `StoreCarRequest.php:41`. Añadir `max:99999999.99` y `max:99999999999999.99` respectivamente con tests de 422.

## Fase 3 — P1/P2: acceso, operación y resiliencia

- [ ] **3.1 Tokens rechazados evaden los limitadores.** `EnsureUserIsActive` y `EnsurePasswordChangeIsEnforced` abortan antes de `throttle:api` (`routes/api.php:33`) y `AppServiceProvider.php:115-122` devuelve `Limit::none()` cuando Sanctum resolvió usuario. Un token de cuenta inactiva permite 200 peticiones sin 429. Aplicar el cupo también a ese estado y añadir test en `RateLimitTest.php`.
- [ ] **3.2 Los seeders RBAC no invalidan la caché de permisos.** `RolePermissionSeeder.php:60` y `ProductionAdminSeeder.php:91` hacen `sync()` sin `User::flushPermissionCacheForRole()`; con TTL 300 los permisos revocados siguen vigentes hasta 5 minutos. Llamar al flush tras cada sync y cubrirlo en `tests/Feature/Database/*` y `PermissionCacheTest.php`.
- [ ] **3.3 `PATCH /users/{user}` con body `{"user":...}` responde 500.** `UpdateUserRequest.php:61,65` lee `$this->user->id` (propiedad mágica que el payload puede sombrear) en vez de `$this->route('user')`. Usar el modelo de ruta y añadir un test con ese campo extra.
- [ ] **3.4 Healthcheck TLS mal dirigido.** `docker-compose.tls.yml:14-21` no sobrescribe el `wget http://127.0.0.1/up` de `docker-compose.yml:48-53`, pero `tls.conf:12-19` redirige 80→443. Sobrescribir el healthcheck para sondear `https://127.0.0.1/up`.
- [ ] **3.5 Rotación de backups recursiva.** `docker/scripts/backup-postgres.sh:61-62` usa `find` sin `-maxdepth 1` y `-mtime +N` (redondeo de un día). Añadir `-maxdepth 1` y ajustar el criterio de antigüedad al documentado.
- [ ] **3.6 Superusuario expuesto al contenedor `app`.** `docker-compose.prod.yml:22-23` inyecta todo `.env.production` (incluido `POSTGRES_PASSWORD`) en `app`/`worker`/`scheduler`. Sustituir `env_file` por una lista explícita de variables de aplicación.

## Fase 4 — P2: carrera fiscal y calidad de la suite

- [ ] **4.1 Impuestos re-leídos sin `is_active` ni control de cantidad.** `SaleService.php:122` consulta `Tax::whereIn('id', ...)` sin filtrar activos y sin comparar `count()`; entre la validación (`StoreSaleRequest.php:33`) y la lectura un impuesto puede desactivarse. Reproducir la carrera; si existe, filtrar por `is_active`, verificar que llegaron todos y responder 409 con excepción de dominio.
- [ ] **4.2 `QueryCountTest` sin valores absolutos.** `tests/Feature/Performance/QueryCountTest.php:16-44` solo compara páginas pequeña y grande: un +2 constante pasa, y quitar eager loads no cambia el resultado porque los Resources usan `whenLoaded()`. Fijar `toBe(N)` por listado y aserciones de estructura de relaciones.
- [ ] **4.3 Filtros con aserciones débiles.** `CatalogLocationListFilterTest.php:85-86` y `SaleUserListFilterTest.php:70-71` solo exigen `not->toBeEmpty()`; `CarListFilterTest.php:83-90` depende de un VIN del seeder y su prueba de `%` pasaría con la búsqueda rota. Comparar conjuntos exactos de IDs, crear coches con `%`, `_` y `!`, y comprobar que no se devuelve por substring.
- [ ] **4.4 Cobertura incompleta de rangos y caché.** `SaleUserListFilterTest.php:46-53` no prueba el límite exacto `from == to`; `StatsCacheTest.php:74-100` dice invalidar los ocho agregados pero solo consulta `/stats/summary` y no prueba caducidad por TTL. Parametrizar los ocho endpoints, añadir `travel()` y variar fechas/`limit`.
- [ ] **4.5 Ramas del seeder de producción sin cubrir.** `ProductionAdminSeeder.php:73-99`: restaurar un admin soft-deleted, re-aplicar clave/rol a un registro modificado y asociar la primera sucursal activa no tiene test. Añadir tres casos en `DatabaseSeedTest.php`.
- [ ] **4.6 Exportación CSV poco cubierta.** `AuditLogExportTest.php:43-70` no comprueba el BOM ni los prefijos aparte de `=`, y «con los filtros del listado» solo cubre `action`. Añadir BOM, seis prefijos y filtros `user_id`/`from`/`to`/`auditable_type`.
- [ ] **4.7 Wiring de limitadores sin probar.** `RateLimitTest.php:57-68` invoca el closure directamente; no hay test de que `OPTIONS` ni `api/auth/login` queden exentos de `api-anon` (`ThrottleAnonymousApi.php:28`), ni del mapeo de `RATE_LIMIT_*`. Añadir ambos.

---

## Criterio de cierre del ciclo

- Todas las casillas en `[x]`, cada una con su commit atómico y mensaje convencional.
- Batería verde dentro de `app`: `./vendor/bin/pest`, `./vendor/bin/pest -c phpunit-postgres.xml`, `composer analyse`, `pint --test`, `composer audit`.
- Humo HTTP en desarrollo, producción y PaaS: `/up`, login, listados, estadísticas, reportes PDF/CSV, auditoría y rate limits.
- `composer docs:export` + `npx @redocly/cli lint storage/app/openapi.json` sin errores (se tolera el aviso `no-server-example.com`).
