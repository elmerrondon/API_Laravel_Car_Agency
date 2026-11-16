# ROADMAP

> **Propósito:** único plan de trabajo y registro de avance del proyecto. Aquí viven objetivos, tareas, criterios de cierre y estado. La guía operativa está en `AGENTS.md`, las lecciones en `MEMORY.md` y el uso en `README.md`; no dupliques esos contenidos aquí.
>
> **Regla de arquitectura de esta etapa:** todo se implementa dentro de esta API Laravel 12 y su MySQL 8.0. Prohibido usar servicios externos (SMTP/correo, OAuth/Google, analítica externa) o librerías pesadas de terceros. La auditoría y las estadísticas se resuelven con tablas, servicios y consultas propias.

---

## 📌 Estado actual

- **Actualizado:** 2026-11-07.
- **Etapa en curso:** Fase 6 — P0 de blindaje (plan derivado de la revisión integral del 2026-11-07). Fases 1-5 cerradas y Fase 6 completa (`6.1`–`6.6`). Siguiente: Fase 7.
- **Plan vigente:** Fases 6-9 de este documento. Las fases 6-8 son de ejecución y la 9 es de cierre y verificación integral.
- **Verificación vigente:** 344 tests / 2 531 aserciones, PHPStan 0 errores, Pint limpio en 345 archivos.
- **Permisos:** 15 sembrados (`admin` 15, `manager` 11, `vendedor` 5).
- **Datos locales:** 8 sucursales, 14 ciudades, 52 coches, 3 usuarios y sin ventas tras `migrate:fresh --seed`.

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
- [x] `4.6` Smoke HTTP real: login del administrador, `/auth/me`, cambio de clave, reset administrativo, consulta de auditoría de login/logout, `stats/summary`, `top-users?limit=5`, `top-branches`, `top-cities`, `top-brands`, `inventory` y `sales/trend`; comprobar `403` de un vendedor en auditoría/estadísticas y `401` de un usuario desactivado.
- [x] `4.7` Marcar todas las casillas de esta etapa en `ROADMAP.md`, registrar la memoria técnica en `MEMORY.md` y dejar un commit atómico por micro-tarea completada.

**Criterio de cierre:** suite verde, PHPStan 0, Pint limpio, documentación actualizada y smoke HTTP completo contra Nginx/MySQL.

---

## Fase 5 — Endurecimiento operativo, seguridad y CI

**Objetivo:** cerrar la etapa evolutiva sin dependencias externas en tiempo de ejecución, con retención de auditoría, exportación CSV, enforcement opcional de contraseña temporal, cabeceras de seguridad y CI de calidad.

- [x] `5.1` Extraer `App\Support\PasswordPolicy` y eliminar `->uncompromised()`: producción usa 8 caracteres, mayúsculas, minúsculas, números y símbolos; local/testing mantiene la regla relajada. Test explícito de la regla fuerte y de la ausencia de la comprobación externa.
- [x] `5.2` Añadir `config/audit.php` (`AUDIT_RETENTION_DAYS=90`), el comando `audit:prune {--days=}` y la programación diaria en `routes/console.php`; test de borrado de antiguos y conservación de recientes.
- [x] `5.3` Añadir `GET /api/audit-logs/export` con `StreamedResponse` nativo, reutilizando los filtros del listado y escapando celdas de CSV; documentación OpenAPI como `text/csv` binario.
- [x] `5.4` Añadir el middleware `password.changed` controlado por `ENFORCE_PASSWORD_CHANGE` (desactivado por defecto): solo permite `/auth/me`, `PUT /auth/password` y `POST /auth/logout` cuando el flag está activo; test de las rutas permitidas y bloqueadas.
- [x] `5.5` Añadir cabeceras `X-Content-Type-Options`, `X-Frame-Options` y `Referrer-Policy` en Nginx y Caddy.
- [x] `5.6` Crear `.github/workflows/ci.yml` con PHP 8.3, SQLite en memoria, `composer test`, `composer analyse`, `pint --test` y `composer audit`, independiente del workflow de Docker Hub.
- [x] `5.7` Integrar el smoke test en Pest (`tests/Feature/Smoke/ApiSmokeTest.php`), dejar la base local determinista con `migrate:fresh --seed` y verificar la suite completa.
- [x] `5.8` Actualizar `ROADMAP.md`, `AGENTS.md`, `MEMORY.md` y `README.md`, y exportar el OpenAPI actualizado (`composer docs:export` + Redocly).

**Criterio de cierre:** suite verde, PHPStan 0, Pint limpio, `composer audit` sin avisos, OpenAPI válido y sin llamadas HTTP salientes en tiempo de ejecución.

---

## Decisiones adoptadas para las fases 6-9

Plan derivado de la revisión integral del 2026-11-07 (informe externo no versionado). Cada tarea cita el hallazgo que resuelve: `S-*` seguridad, `L-*` lógica de negocio, `P-*` rendimiento, `Q-*` calidad y `I-*` infraestructura.

| Tema | Decisión |
|---|---|
| TLS en producción (`S-02`) | Terminación en proxy externo (Nginx/Traefik/Caddy del host o balanceador) y Nginx del stack publicado solo en loopback. Se documenta además un overlay opcional con TLS propio para stacks autónomos. |
| Sucursal de la venta (`L-02`) | `branch_id` se deriva siempre de `car.branch_id`; si el payload envía otra sucursal, `422`. |
| Estados del coche (`L-03`) | Alta solo como `available`; actualización manual solo `available ↔ reserved`; `sold` lo pone únicamente `SaleService`. |
| Coche con soft delete en ventas (`L-04`) | `Sale::car()` usa `withTrashed()` para conservar el histórico. |
| Auditoría de catálogos (`S-06`) | Crear y borrar siempre; actualizar solo si cambian campos relevantes. Los servicios de solo lectura quedan fuera. |
| Colas (`I-04`) | Servicio `worker` con `queue:work` en el stack de producción. |
| Matriz CI (`Q-06`) | PHP 8.2/8.3/8.4 + Dependabot. ARM64 se documenta; el build multiarch queda como opción explícita. |
| Healthcheck (`I-05`) | `/up` comprueba base de datos y caché sin filtrar detalles; los healthchecks de Docker usan un comando dedicado. |
| Nivel PHPStan (`Q-02`) | Subida progresiva 5 → 6 → 7, sin baseline salvo justificación registrada en `MEMORY.md`. |

**Regla de ejecución:** cada micro-tarea equivale a un commit atómico y solo se cierra tras verificar en Docker `composer test`, `composer analyse`, `pint --test` y `composer audit`. Si la tarea cambia el contrato de la API, se añade `composer docs:export` y validación con Redocly. Prohibido `git push`, `git checkout`, `git branch`, `git merge`, `git rebase` y `git reset`; nunca se versiona `.env`.

---

## Fase 6 — P0: blindaje antes de exponer a Internet

**Objetivo:** que ningún despliegue hecho con la plantilla de producción nazca con credenciales por defecto, HTTP plano, límites eludibles o retención inactiva.

- [x] `6.1` Exigir un administrador seguro en producción (`S-01`): añadir `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME`, `ADMIN_LAST_NAME`, `ADMIN_CODE` y `ADMIN_DOCUMENT_NUMBER` a `.env.production.example`; eliminar el fallback `secret1234` en `ProductionAdminSeeder`; abortar con mensaje claro si la clave falta, es el valor por defecto, empieza por `CHANGE_ME_` o no cumple la política de producción. Conservar el fallback solo en local/testing. Tests: el seeder falla con clave insegura y crea el administrador con una clave fuerte.
- [x] `6.2` Terminar TLS en producción y dejar de servir HTTP plano (`S-02`): documentar el proxy externo, publicar el Nginx del stack solo en `127.0.0.1:80` y redirigir `80 → 443` en el proxy; añadir `TRUSTED_PROXIES` y HSTS cuando la petición llegue por HTTPS. Incluir una vía alternativa con TLS propio en el compose. Documentar certificados, renovación y cutover. Añadir a `.env.production.example` y al runbook las variables y pasos.
- [x] `6.3` Configurar proxies de confianza (`S-05`): registrar `trustProxies` en `bootstrap/app.php` leyendo `TRUSTED_PROXIES` (lista o `*`) y las cabeceras `X-Forwarded-*`; añadir la variable a `.env.example` y `.env.production.example`. Test con `X-Forwarded-For` que compruebe que `$request->ip()` y la auditoría usan la IP real.
- [x] `6.4` Limitar el tráfico anónimo antes de `auth:sanctum` (`S-03`): middleware que aplique un límite por IP a peticiones sin token válido (`RATE_LIMIT_API_ANON`, 30/min por defecto) y mantenga el límite por usuario después de autenticar; los tokens inválidos también se limitan por IP. Tests: sin token y con el límite en 1 la segunda petición da `429`; el límite por usuario sigue funcionando.
- [x] `6.5` Acotar el *password spraying* y la amplificación de auditoría (`S-04`): limitador de login con dos ventanas (email+IP 5/min y por IP 20/min) y auditoría de fallos agrupada por ventana para no insertar una fila por intento ni por cada `429`. Tests: rotar emails desde una IP acaba en `429` y el número de filas de auditoría de un ataque está acotado.
- [x] `6.6` Ejecutar el scheduler y programar la purga de tokens (`S-07`): añadir el servicio `scheduler` (`php artisan schedule:work`) a `docker-compose.yml` y `docker-compose.prod.yml`; programar `sanctum:prune-expired --hours=24` junto a `audit:prune` en `routes/console.php`; documentar el cron equivalente para PaaS. `php artisan schedule:list` debe mostrar ambas tareas.

**Criterio de cierre:** los seis P0 verificados con pruebas dinámicas (429 anónimo, 429 por rotación de emails, IP real detrás de proxy, seeder que falla con clave insegura, scheduler activo y TLS/HSTS en producción), suite verde, PHPStan 0, Pint limpio y `composer audit` sin avisos.

---

## Fase 7 — P1: invariantes de negocio y operación

**Objetivo:** cerrar los huecos de auditoría, las inconsistencias financieras y los fallos operativos que no bloquean el despliegue pero degradan la trazabilidad y la fiabilidad.

- [x] `7.1` Infraestructura de auditoría de catálogos (`S-06`): nuevos casos en `AuditAction` (`catalog.*`, `location.*`) y alias en el morph map para `Brand`, `CarModel`, `CarType`, `Category`, `Color`, `Currency`, `PaymentMethod`, `Tax`, `Country`, `State` y `City`. Test de arquitectura que detecte servicios con `create`/`update`/`delete` que no usen `AuditService`, con lista blanca explícita para servicios de solo lectura.
- [x] `7.2` Auditar catálogos (`S-06`): `BrandService`, `CarModelService`, `CarTypeService`, `CategoryService`, `ColorService`, `CurrencyService`, `PaymentMethodService` y `TaxService`. Crear y borrar siempre; actualizar solo si cambian campos relevantes. Tests de `Brand` y `Tax` que verifiquen actor, IP, valores antiguos/nuevos y alias correcto.
- [x] `7.3` Auditar ubicaciones (`S-06`): `CountryService`, `StateService` y `CityService` con el mismo patrón que `BranchService`. Tests de CRUD auditado.
- [x] `7.4` Rechazar tipos de cambio no positivos (`L-01`): `exchange_rate` con `gt:0` además de `decimal:0,2`; test que rechace `0` y negativos y documente que la moneda base usa tasa `1`.
- [x] `7.5` Alinear la sucursal de la venta con la del coche (`L-02`): derivar `branch_id` de `$car->branch_id` dentro de la transacción; si el payload envía otra, `422` con excepción de dominio; ajustar `SaleData`, `StoreSaleRequest`, tests y documentación. Los reportes por sucursal deben seguir siendo coherentes.
- [ ] `7.6` Restringir las transiciones de estado del coche (`L-03`): alta solo como `available`; actualización manual solo `available ↔ reserved`; `sold` reservado a `SaleService`; bloqueo de cambios sobre coches con venta `completed`; excepción de transición con `422`. Tests de la matriz de estados y de que la cancelación sigue liberando el coche.
- [ ] `7.7` Conservar coches con soft delete en el histórico de ventas (`L-04`): `Sale::car()` con `withTrashed()` y revisión de `SaleReportService` y recursos que cargan `car`. Test: venta + soft delete del coche → detalle y reporte siguen mostrando marca y modelo.
- [ ] `7.8` Manejar la ausencia de moneda base (`L-05`): validar `Currency::firstWhere('is_base', true)` y lanzar una excepción de dominio (`422`/`409`) antes de crear la venta. Test con base de datos sin moneda base: la ruta financiera no produce `500`.
- [ ] `7.9` Acotar `sale_date` (`L-06`): `before_or_equal:now` y una cota inferior razonable y configurable. Tests de fecha futura y demasiado antigua.
- [ ] `7.10` Obligar a cambiar la clave fijada por un administrador (`S-08`): en `UserService::updateUser`, si llega `password`, marcar `must_change_password = true`; el autoservicio `PUT /auth/password` lo limpia. Actualizar auditoría y tests.
- [ ] `7.11` Descubrir paquetes en tiempo de build (`I-01`): ejecutar `php artisan package:discover` en la etapa de build de producción para no hacerlo en cada arranque.
- [ ] `7.12` Unificar el tamaño máximo de cuerpo (`I-02`): alinear `client_max_body_size` de Nginx con `post_max_size`/`upload_max_filesize` de PHP (`8m`) y documentarlo.
- [ ] `7.13` Runbook de backups y restauración (`I-03`): procedimiento `mysqldump`, retención, programación con cron/systemd, restauración probada y alternativa gestionada en PaaS. Añadir script de ejemplo y verificar una restauración en staging.
- [ ] `7.14` Procesar colas en producción (`I-04`): añadir el servicio `worker` (`php artisan queue:work`) al compose de producción, con reinicio y límites; documentar el equivalente en PaaS. Si se opta por no encolar, fijar `QUEUE_CONNECTION=sync` y justificarlo.

**Criterio de cierre:** todo servicio de escritura audita, la ruta de ventas rechaza datos incoherentes sin `500`, el histórico sobrevive a soft deletes, el build no descubre paquetes en runtime y el stack de producción ejecuta scheduler y worker.

---

## Fase 8 — P2: rendimiento, hardening y calidad

**Objetivo:** reducir coste por petición, endurecer cabeceras y superficies expuestas, y elevar el listón de calidad sin cambiar el contrato funcional.

- [ ] `8.1` Evitar la escritura de `last_used_at` en cada petición (`P-01`): `config/sanctum.php` con `last_used_at => env('SANCTUM_LAST_USED_AT', false)` y documentación del trade-off.
- [ ] `8.2` Ajustar el pool de PHP-FPM (`P-02`): `docker/php/www.conf` con `pm.max_children` calculado por RAM, `pm.max_requests=500` y `request_terminate_timeout`; documentar la fórmula y el reinicio tras despliegue.
- [ ] `8.3` Activar OPcache de producción (`P-03`): `opcache.validate_timestamps=0`, `memory_consumption=128`, `max_accelerated_files=10000` y JIT desactivado para este perfil.
- [ ] `8.4` Comprimir respuestas (`P-04`): `gzip on` en Nginx con `application/json`, `text/csv` y `text/plain`; nunca comprimir PDF.
- [ ] `8.5` Ajustar MySQL (`P-05`): `innodb_buffer_pool_size` configurable por entorno (25-50 % de la RAM), `slow_query_log` y `long_query_time`; documentar el cálculo.
- [ ] `8.6` Limitar la generación de PDF por separado (`P-06`): limitador `reports-pdf` (5/min) manteniendo `reports` en 30/min para JSON; configuración y tests.
- [ ] `8.7` Completar cabeceras de seguridad (`S-11`): `server_tokens off`, `Permissions-Policy`, CSP para `/docs/api` y HSTS solo bajo HTTPS; replicar en Caddy y verificar que se aplican también en errores.
- [ ] `8.8` Cerrar el MySQL de desarrollo (`S-09`): publicar solo en `127.0.0.1:3306`, eliminar `MYSQL_ROOT_HOST: "%"` y crear un usuario de aplicación no root.
- [ ] `8.9` Rechazar CORS comodín en producción (`S-10`): fallar el arranque si `CORS_ALLOWED_ORIGINS` es `*` o está vacío en producción; mantener `*` en local/testing y añadir test de configuración.
- [ ] `8.10` Prefijar los tokens de Sanctum (`S-12`): `SANCTUM_TOKEN_PREFIX=car_` por defecto en `.env.example` y `.env.production.example`, test de token nuevo con prefijo y nota de impacto para clientes.
- [ ] `8.11` Profundizar el healthcheck (`I-05`): `/up` con comprobación de base de datos y caché sin filtrar detalles y comando `health:check` para los healthchecks de Docker; actualizar app, Nginx y PaaS. `/up` responde `503` si la base falla.
- [ ] `8.12` Eliminar los tests de ejemplo (`Q-01`): borrar `tests/Feature/ExampleTest.php` y `tests/Unit/ExampleTest.php` conservando el smoke test real.
- [ ] `8.13` Subir PHPStan a nivel 6 (`Q-02`): corregir los errores y actualizar `phpstan.neon`; `composer analyse` en 0 errores.
- [ ] `8.14` Subir PHPStan a nivel 7 (`Q-02`): corregir los errores o justificar un baseline mínimo documentado en `MEMORY.md`. Nivel 8 queda como objetivo posterior.
- [ ] `8.15` Mover la carga de relaciones de rol al servicio (`Q-03`): `RoleController::show` deja de hacer `$role->load('permissions')` y pasa a `RoleService::getRole()`.
- [ ] `8.16` Corregir el typo de monedas (`Q-04`): renombrar `getAllPaginatedCurrecies` a `getAllPaginatedCurrencies` y actualizar llamadas y tests.
- [ ] `8.17` Unificar el nombre del token (`Q-05`): constante `TOKEN_NAME` en `AuthService` usada por login y refresh, con test.
- [ ] `8.18` Ampliar CI y dependencias (`Q-06`): matriz PHP 8.2/8.3/8.4 en `ci.yml`, `.github/dependabot.yml` para Composer y GitHub Actions, y decisión documentada sobre `linux/arm64` en la imagen PaaS.

**Criterio de cierre:** JSON y CSV comprimidos, FPM y OPcache con valores explícitos, PDF acotado por su propio límite, cabeceras completas, CORS cerrado en producción, healthcheck que detecta caída de base de datos, PHPStan nivel 7 en 0 errores y CI verde en las tres versiones de PHP.

---

## Fase 9 — Cierre y verificación integral

**Objetivo:** demostrar que todas las acciones del plan están implementadas, documentadas y reproducibles desde cero.

- [ ] `9.1` Verificación completa en Docker: `migrate:fresh --seed`, suite, `composer analyse`, `pint --test`, `composer audit`, `composer docs:export` + Redocly, `schedule:list`, `docker compose config` de desarrollo y producción, build de producción y build de la imagen PaaS.
- [ ] `9.2` Smoke HTTP real de los P0/P1: login, `429` anónimo, login rotando emails, IP real tras proxy, auditoría de catálogos, venta con `exchange_rate=0`, venta con sucursal incoherente, estado `sold` manual, coche borrado en venta histórica y `/up`. Cada hallazgo debe tener una comprobación observable.
- [ ] `9.3` Documentación y cierre: marcar las tareas en `ROADMAP.md`, actualizar `AGENTS.md` (límites, proxies, TLS, scheduler, worker, auditoría de catálogos, reglas de venta y estados, prefijo de token, healthcheck), `README.md` (operación, backups, TLS, respuestas `429`) y `MEMORY.md` (entradas fechadas, máximo 100 líneas).

**Criterio de cierre:** suite verde, PHPStan en el nivel acordado, Pint limpio, `composer audit` sin avisos, OpenAPI válido, ambos stacks levantan, scheduler y worker activos y los seis P0 verificados con pruebas dinámicas.

### Trazabilidad de hallazgos

| Hallazgo | Tarea | Hallazgo | Tarea |
|---|---|---|---|
| S-01 | 6.1 | L-01 | 7.4 |
| S-02 | 6.2 | L-02 | 7.5 |
| S-03 | 6.4 | L-03 | 7.6 |
| S-04 | 6.5 | L-04 | 7.7 |
| S-05 | 6.3 | L-05 | 7.8 |
| S-06 | 7.1-7.3 | L-06 | 7.9 |
| S-07 | 6.6 | L-07 | Sin acción: alcance multi-sucursal intencional |
| S-08 | 7.10 | P-01 | 8.1 |
| S-09 | 8.8 | P-02 | 8.2 |
| S-10 | 8.9 | P-03 | 8.3 |
| S-11 | 8.7 | P-04 | 8.4 |
| S-12 | 8.10 | P-05 | 8.5 |
| I-01 | 7.11 | P-06 | 8.6 |
| I-02 | 7.12 | P-07 | Sin acción: conservar lo que ya funciona |
| I-03 | 7.13 | Q-01 | 8.12 |
| I-04 | 7.14 | Q-02 | 8.13-8.14 |
| I-05 | 8.11 | Q-03 | 8.15 |
| I-06 | Sin acción: mantener `LOG_CHANNEL=stderr` | Q-04 | 8.16 |
|  |  | Q-05 | 8.17 |
|  |  | Q-06 | 8.18 |
|  |  | Q-07 | Verificación recurrente en 9.1 |

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
docker compose exec -T app php artisan schedule:list
docker compose exec -T app php artisan health:check
docker compose config
docker compose -f docker-compose.prod.yml --env-file .env.production config
npx @redocly/cli lint storage/app/openapi.json
```

> La suite usa SQLite `:memory:`; MySQL solo se toca en `migrate:fresh --seed` y en los smoke tests HTTP.
>
> Los comandos `docker compose config`, el build de producción y el build PaaS se ejecutan en el host porque son de Docker; el resto va dentro de `app` con `-T`.
