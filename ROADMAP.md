# ROADMAP

> **Propósito:** único plan de trabajo y registro de avance del proyecto. Aquí viven objetivos, tareas y criterios de cierre. La guía operativa está en `AGENTS.md`, las lecciones en `MEMORY.md` y el uso en `README.md`; no dupliques esos contenidos aquí.
>
> **Regla de arquitectura de esta etapa:** la API es Laravel 12 sobre **PostgreSQL 17** (locale `C.UTF-8`). MySQL queda fuera del proyecto: no hay imágenes, servicios, conexiones, scripts ni documentación operativa de MySQL. Prohibido usar servicios externos (SMTP/correo, OAuth/Google, analítica externa) o librerías pesadas de terceros. La auditoría y las estadísticas se resuelven con tablas, servicios y consultas propias.

---

## 📌 Estado actual

- **Actualizado:** 2026-11-18.
- **Estado:** fase 2 completada (esquema, índices de FK e índices case-insensitive). Siguiente: fase 3 (compatibilidad de código y semántica case-sensitive).
- **Punto de partida:** API completa y estable sobre MySQL (fases 1-9 cerradas; el histórico vive en `MEMORY.md` y `MEMORY-ARCHIVE.md`).
- **Verificación de referencia:** 378 tests / 2 724 aserciones en SQLite, PHPStan nivel 7 sin errores, Pint limpio en 362 archivos, `composer audit` sin avisos y OpenAPI 3.1.2 válido (55 paths).
- **Objetivo:** misma API, mismo contrato y mismo comportamiento sobre PostgreSQL 17, sin rastro de MySQL en el stack.

---

## 🎯 Decisiones adoptadas

| Tema | Decisión |
| :--- | :--- |
| Datos | No hay datos reales que migrar. La base se recrea con `migrate:fresh --seed`. Sin importador, sin `pgloader` y sin conexión `legacy`. |
| Alcance MySQL | Switch completo: se elimina de imágenes, compose, `config/database.php`, CI, scripts y documentación operativa. |
| Versión | `postgres:17-alpine` con locale `C.UTF-8`. Se evita `postgres:18` por el cambio de `PGDATA`. |
| Case sensitivity | Normalización en el borde (email en minúsculas; `document_number`, `code` y `vin` en mayúsculas) + índices únicos parciales `lower(...) WHERE deleted_at IS NULL` en usuarios y `lower(...)` en catálogos. |
| CI | Se mantiene la matriz PHP 8.2/8.3/8.4 con SQLite y se añade un job dedicado que corre `migrate:fresh --seed` y la suite completa contra PostgreSQL. |
| Rol de BD | Usuario de aplicación no superusuario `car_agency`, dueño de la base y del schema, creado por un script de inicialización. |
| Zona horaria | Sesión de PostgreSQL en UTC mediante `DB_TIMEZONE=UTC`. |
| JSON | Se mantiene el tipo `json` de Laravel (`sales.taxes`, `audit_logs.old_values/new_values`). La app no consulta dentro del JSON, así que `jsonb` queda como mejora futura. |
| Orden de texto | Se acepta el orden por bytes de `C.UTF-8`. ICU `es-ES` queda como mejora futura porque exige un volumen nuevo. |

**Riesgos aceptados**

- **Orden alfabético:** con `C.UTF-8`, `ORDER BY name` ordena por bytes (mayúsculas antes que minúsculas y acentos después de `z`). Los reportes y rankings pueden cambiar de orden respecto a MySQL.
- **Enteros sin signo:** `jobs.reserved_at/available_at/created_at` y `cache.expiration` pasan a `integer` con signo. El límite baja de 2106 a 2038; sin impacto práctico para la app.
- **`mediumText` → `text`:** sin cambio funcional.
- **Índices de FK:** PostgreSQL no crea índices implícitos para `foreignId()->constrained()`. Se cubren en la Fase 2.
- **Histórico:** `MEMORY.md` y `MEMORY-ARCHIVE.md` conservan menciones a MySQL como registro histórico; no se reescriben.

**Regla de ejecución:** cada micro-tarea equivale a un commit atómico y solo se cierra tras verificar en Docker. Prohibido `git push`, `git checkout`, `git branch`, `git merge`, `git rebase` y `git reset`; nunca se versiona `.env`.

---

## Fase 1 — Infraestructura Docker y entorno

**Objetivo:** que el stack completo arranque sobre PostgreSQL 17 sin ninguna dependencia ni variable de MySQL.

- [x] `1.1` `docker/php/Dockerfile`: reemplazar `pdo_mysql` por `pdo_pgsql`, añadir `libpq` al runtime y `postgresql-dev` a `.build-deps`; conservar `pdo_sqlite` porque la suite rápida lo usa.
- [x] `1.2` `docker/render/Dockerfile.render`: cambiar `pdo_mysql` por `pdo_pgsql` en `install-php-extensions` y actualizar los comentarios de cabecera (`DB_CONNECTION=pgsql`).
- [x] `1.3` `docker-compose.yml`: sustituir el servicio `mysql` por `postgres:17-alpine` con volumen `pg-data:/var/lib/postgresql/data`, `shm_size: 256mb`, puerto `127.0.0.1:5432:5432`, healthcheck `pg_isready -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"` y `app.depends_on.postgres: condition: service_healthy`.
- [x] `1.4` `docker-compose.yml`: definir `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_APP_USER` y `POSTGRES_APP_PASSWORD`; ajustar `DB_HOST=postgres`, `DB_PORT=5432`, `DB_USERNAME=car_agency` y `DB_PASSWORD` de `app` y `scheduler` (y de `worker` en producción).
- [x] `1.5` `docker-compose.yml`: sustituir el slow log de MySQL por `command: [postgres, -c, shared_buffers=..., -c, effective_cache_size=..., -c, work_mem=..., -c, log_min_duration_statement=...]` con variables `POSTGRES_*`.
- [x] `1.6` `docker/postgres/init/01-app-role.sh` (nuevo): crear el rol `car_agency` (`LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE`), hacerlo dueño de la base y del schema `public`, y crear la base `testing` cuando `POSTGRES_CREATE_TESTING_DB=true` (solo desarrollo).
- [x] `1.7` `docker-compose.prod.yml`: replicar el servicio `postgres` con `env_file: .env.production`, sin puertos publicados, `shared_buffers=512MB`, volumen `pg-data`, `shm_size`, healthcheck y el mismo script de inicialización.
- [x] `1.8` `.env.example`: `DB_CONNECTION=pgsql`, `DB_HOST=postgres`, `DB_PORT=5432`, `DB_SSLMODE=prefer`, `DB_TIMEZONE=UTC`, `DB_URL=` y `POSTGRES_*`; eliminar `MYSQL_*`.
- [x] `1.9` `.env.production.example`: `DB_SSLMODE=prefer` (el `postgres` del compose no habilita TLS; con un PostgreSQL gestionado se sube a `require`/`verify-full`), `POSTGRES_*` con marcadores `CHANGE_ME_*`, `DB_TIMEZONE=UTC` y nota de que `POSTGRES_APP_PASSWORD` debe coincidir con `DB_PASSWORD`; eliminar `MYSQL_*`.
- [x] `1.10` Actualizar a mano el `.env` local (ignorado por Git) a `pgsql` y comentarios de `docker/php/opcache-prod.ini` y `.gitignore` que citan MySQL.
- [x] `1.11` `grep -rni "mysql\|mariadb\|pdo_mysql" docker .env.example .env.production.example`: sin resultados operativos.

**Criterio de cierre:** `docker compose config` válido en desarrollo y producción; `docker compose up -d --build` levanta `postgres` healthy y `app` conecta; `migrate:fresh --seed` crea el esquema; `/up` responde `200`.

---

## Fase 2 — Esquema, índices y conexión

**Objetivo:** que el esquema sea equivalente al de MySQL y que las consultas conserven su rendimiento **sin** los índices implícitos de las claves foráneas.

- [x] `2.1` `config/database.php`: añadir `'timezone' => env('DB_TIMEZONE', 'UTC')` al bloque `pgsql`; eliminar los bloques `mysql`/`mariadb` y el `use Pdo\Mysql;`.
- [x] `2.2` Migración `..._add_foreign_key_indexes.php` (nueva) con los índices que PostgreSQL no genera:

  | Tabla | Columnas |
  | :--- | :--- |
  | `states` | `country_id` |
  | `cities` | `state_id` |
  | `branches` | `city_id` |
  | `brands` | `country_id` |
  | `car_models` | `brand_id` |
  | `cars` | `color_id`, `car_model_id`, `car_type_id`, `category_id` |
  | `role_user` | `user_id` |
  | `permission_role` | `role_id` |
  | `branch_user` | `user_id` |
  | `sales` | `car_id`, `user_id`, `payment_method_id`, `payment_currency_id`, `base_currency_id` |

  Ya cubiertos por índices compuestos existentes: `cars.branch_id` por `(branch_id, status)`, `sales.branch_id` por `(branch_id, sale_date)`, `audit_logs.user_id` por `(user_id, created_at)` y los lados izquierdos de `role_user`, `permission_role` y `branch_user`.

- [x] `2.3` Migración `..._add_case_insensitive_unique_indexes.php` (nueva): sustituir los `unique()` de texto por índices de expresión y añadir los parciales de usuarios.

  ```sql
  -- Usuarios: el índice (columna, deleted_at) no protege filas activas en PostgreSQL
  -- porque los NULL de deleted_at son distintos entre sí.
  CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email)) WHERE deleted_at IS NULL;
  CREATE UNIQUE INDEX users_document_number_lower_unique ON users (lower(document_number)) WHERE deleted_at IS NULL;

  -- Catálogos: nombres con unicidad case-insensitive
  CREATE UNIQUE INDEX countries_name_lower_unique ON countries (lower(name));
  CREATE UNIQUE INDEX states_name_country_lower_unique ON states (lower(name), country_id);
  CREATE UNIQUE INDEX cities_name_state_lower_unique ON cities (lower(name), state_id);
  CREATE UNIQUE INDEX branches_name_lower_unique ON branches (lower(name));
  CREATE UNIQUE INDEX brands_name_lower_unique ON brands (lower(name));
  CREATE UNIQUE INDEX car_models_name_lower_unique ON car_models (lower(name));
  CREATE UNIQUE INDEX car_types_name_lower_unique ON car_types (lower(name));
  CREATE UNIQUE INDEX categories_name_lower_unique ON categories (lower(name));
  CREATE UNIQUE INDEX colors_name_lower_unique ON colors (lower(name));
  CREATE UNIQUE INDEX payment_methods_name_lower_unique ON payment_methods (lower(name));
  CREATE UNIQUE INDEX currencies_name_lower_unique ON currencies (lower(name));
  CREATE UNIQUE INDEX taxes_name_lower_unique ON taxes (lower(name));
  CREATE UNIQUE INDEX roles_name_lower_unique ON roles (lower(name));
  CREATE UNIQUE INDEX permissions_name_lower_unique ON permissions (lower(name));
  ```

  Cada `CREATE` va precedido del `dropUnique` de la restricción plana equivalente (incluidos `users_email_deleted_at_unique` y `users_document_number_deleted_at_unique`) para no duplicar estructura. `currencies.code`, `taxes.code`, `branches.code`, `users.code` y `cars.vin` mantienen su índice plano: se normalizan en el borde.

- [x] `2.4` `tests/Feature/Database/IndexesTest.php`: añadir aserciones por nombre para los índices de FK y para los índices case-insensitive; conservar las que garantizan que `cars` no tiene índices duplicados.
- [x] `2.5` Actualizar comentarios que afirman que las FK ya indexan: `database/migrations/2026_10_20_000001_add_performance_indexes.php`, `app/Services/Reports/SaleReportService.php` y `tests/Feature/Stats/InventoryStatsTest.php` (menciona MySQL).
- [x] `2.6` `migrate:fresh --seed` limpio en PostgreSQL y comprobación de `Schema::hasIndex` en un `tinker` puntual.
- [x] `2.7` Validar con dataset realista (3 000 ventas/coches) y `EXPLAIN (ANALYZE, BUFFERS)` que se usan `sales(branch_id, status, sale_date)`, `cars(branch_id, status)` y los nuevos índices de FK; sin `Seq Scan` en reportes y estadísticas. Con el seed pequeño el planner elige `Seq Scan` y no prueba nada.

**Criterio de cierre:** `migrate:fresh --seed` sin errores, `IndexesTest` verde y los `EXPLAIN` muestran los índices previstos.

---

## Fase 3 — Compatibilidad de código y semántica case-sensitive

**Objetivo:** eliminar la única función SQL específica de MySQL y recuperar la semántica case-insensitive que aportaba `utf8mb4_unicode_ci`.

- [x] `3.1` `app/Services/Stats/StatsQuery.php`: añadir `pgsql => "to_char(sale_date, 'YYYY-MM')"` a `periodExpression()`; conservar SQLite y eliminar la rama MySQL.
- [x] `3.2` `app/Rules/CaseInsensitiveUnique.php` (nuevo): regla invocable reutilizable con tabla, columna, `ignoreId`, condiciones extra (para `states`/`cities`) y `withoutTrashed`; compara con `whereRaw('lower(columna) = ?', [mb_strtolower($valor)])` y falla con el mensaje de `validation.unique`.
- [x] `3.3` `StoreUserRequest`/`UpdateUserRequest`: normalizar `email` a minúsculas y `document_number` a mayúsculas en `prepareForValidation()`; aplicar `CaseInsensitiveUnique` con `withoutTrashed()` (y `ignore()` en update). `users.code` se normaliza a mayúsculas y conserva la regla de inmutabilidad.
- [x] `3.4` `LoginAuthRequest`: normalizar `email` a minúsculas para que `Auth::attempt` encuentre la fila guardada.
- [x] `3.5` `StoreCarRequest`/`UpdateCarRequest`: normalizar `vin` a mayúsculas antes de validar y guardar. Añadir un mutador `vin` en `Car` (mayúsculas y `trim`) como defensa para factories, seeders y escrituras fuera de los FormRequest.
- [x] `3.6` Catálogos de coches: `Brand`, `CarModel`, `CarType`, `Category` y `Color` usan `CaseInsensitiveUnique` sobre `name` en Store/Update (el alta de duplicados con otra capitalización responde `422`).
- [x] `3.7` Catálogos de ventas: `Currency` (`name` y `code`), `Tax` (`name` y `code`) y `PaymentMethod` (`name`); normalizar `code` a mayúsculas y usar la regla en `name`.
- [ ] `3.8` Ubicaciones y usuarios: `Country`, `Branch` y `Role` por `name`; `State` por `name` + `country_id` y `City` por `name` + `state_id` con condiciones extra.
- [ ] `3.9` Tests: login con email en mayúsculas, alta duplicada con distinta capitalización (catálogo, usuario y `role`), VIN/códigos normalizados y `422` en los duplicados.
- [ ] `3.10` Revisar seeders, factories y `ProductionAdminSeeder`: `CarFactory` genera VIN con `bothify('???##?##?######??')` y puede producir minúsculas, así que debe pasar por el mutador del modelo. Añadir mutadores equivalentes para `User.email` (minúsculas) y `User.code`/`document_number` (mayúsculas).

**Criterio de cierre:** suite SQLite verde, `migrate:fresh --seed` en PostgreSQL y los tests de case sensitivity pasan; la tendencia mensual de estadísticas funciona en PostgreSQL.

---

## Fase 4 — Tests y CI

**Objetivo:** que PostgreSQL se valide de verdad en cada push, no solo con la suite rápida de SQLite.

- [ ] `4.1` `phpunit-postgres.xml` (nuevo): copia de `phpunit.xml` con `DB_CONNECTION=pgsql`, `DB_DATABASE=testing` y `DB_URL=""`; `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` se toman del entorno (`.env` en Docker, variables del job en Actions).
- [ ] `4.2` `composer.json`: script `test:postgres` que limpie configuración y ejecute `php artisan test --configuration=phpunit-postgres.xml`.
- [ ] `4.3` `tests/TestCase.php`: confirmar que `PHPUNIT_ENV_KEYS` basta (ya incluye `DB_CONNECTION`, `DB_DATABASE` y `DB_URL`); no añadir `DB_HOST` salvo que la ejecución en Docker lo exija.
- [ ] `4.4` `.github/workflows/ci.yml`: añadir `pdo_pgsql` a `setup-php`, retirar `pdo_mysql` y crear el job `postgres` (PHP 8.3) con `services.postgres` (`postgres:17-alpine`, base `testing`, healthcheck `pg_isready`), `DB_HOST=127.0.0.1`, `migrate:fresh --seed`, `composer test:postgres`, `composer analyse`, `pint --test` y `composer audit`.
- [ ] `4.5` Actualizar el comentario de cabecera de `ci.yml`: ya no es un workflow "sin servicios externos".
- [ ] `4.6` Ejecutar en Docker `composer test` (SQLite) y `composer test:postgres` contra la base `testing`; corregir divergencias (tipos `numeric` devueltos como string, diferencias de redondeo, `RefreshDatabase`).
- [ ] `4.7` Revisar `deploy-dockerhub.yml`: no cambia de lógica, pero el build debe incluir `pdo_pgsql`; validar la imagen PaaS con un `docker build` local.

**Criterio de cierre:** suite verde en SQLite y en PostgreSQL, `composer analyse`, `pint --test` y `composer audit` limpios; el YAML del CI es válido.

---

## Fase 5 — Operación: backup y restauración

**Objetivo:** sustituir el runbook de MySQL por uno de PostgreSQL probado de ida y vuelta.

- [ ] `5.1` `docker/scripts/backup-postgres.sh` (nuevo): `pg_dump -Fc` dentro del contenedor `postgres` con `PGPASSWORD` desde `POSTGRES_PASSWORD`, retención por `BACKUP_RETENTION_DAYS` y `COMPOSE_FILE`/`ENV_FILE` configurables.
- [ ] `5.2` `docker/scripts/restore-postgres.sh` (nuevo): parar `app`, `worker` y `scheduler`, `dropdb --if-exists --force`, `createdb -O car_agency`, `pg_restore --no-owner --no-privileges --exit-on-error`, reiniciar y exigir `--confirm`.
- [ ] `5.3` Eliminar `docker/scripts/backup-mysql.sh` y `restore-mysql.sh`, y actualizar el `.gitignore` si el comentario de `/backups` cita MySQL.
- [ ] `5.4` Probar el ciclo completo en desarrollo: dump → fila marcadora → restore → marcador ausente y conteos intactos.
- [ ] `5.5` Verificar que `log_min_duration_statement` escribe en `docker compose logs postgres` con una consulta lenta provocada.

**Criterio de cierre:** backup y restauración probados; las consultas lentas son visibles en los logs del contenedor.

---

## Fase 6 — Documentación

**Objetivo:** que ninguna guía operativa mencione MySQL ni comandos que ya no existen.

- [ ] `6.1` `README.md`: badge y stack, inicio rápido (la base la crea `POSTGRES_DB`), puertos y healthchecks, comandos `psql`, tests (SQLite + PostgreSQL), despliegue, "Ajuste de PostgreSQL" (sustituye "Ajuste de MySQL"), backups, PaaS con PostgreSQL gestionado y `DB_SSLMODE`, troubleshooting (`SQLSTATE[08006]`, `28P01`, `42P01`, `remaining connection slots`, `permission denied for schema public`) y notas finales.
- [ ] `6.2` `AGENTS.md`: entorno sin MySQL local, extensiones `pdo_pgsql`, servicios `postgres` en ambos compose, script de rol de app, scripts de backup/restore, CI con job PostgreSQL, `to_char` en estadísticas, autosuficiencia "API + PostgreSQL", índices de FK y semántica case-sensitive.
- [ ] `6.3` `ROADMAP.md`: marcar las tareas al cerrarlas y dejar el estado actualizado.
- [ ] `6.4` `MEMORY.md`: entrada fechada con las decisiones no obvias (FK sin índice, `C.UTF-8`, índices parciales, rol dueño del schema, `to_char`); verificar `wc -l MEMORY.md` ≤ 100.
- [ ] `6.5` `grep -rni "mysql\|mariadb" --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=storage --exclude-dir=.git --exclude-dir=.agents .`: solo pueden quedar `MEMORY.md`/`MEMORY-ARCHIVE.md` como histórico.

**Criterio de cierre:** documentación operativa 100 % PostgreSQL y sin referencias obsoletas.

---

## Fase 7 — Verificación integral y cierre

**Objetivo:** demostrar que la migración es reproducible desde cero y que el contrato de la API no cambió.

- [ ] `7.1` Verificación completa en Docker (ver bloque de comandos).
- [ ] `7.2` Stack de producción con volumen nuevo: `build app`, `up -d`, `migrate --force --seed`, `optimize`, `/up` y login.
- [ ] `7.3` Imagen PaaS: `docker build -f docker/render/Dockerfile.render` y arranque contra PostgreSQL (o SQLite si no hay instancia accesible).
- [ ] `7.4` Smoke HTTP: login, `/auth/me`, estadísticas, reportes JSON/PDF, export CSV, auditoría y `/up`.
- [ ] `7.5` `composer docs:export` + Redocly: el contrato OpenAPI sigue siendo válido (la migración no cambia rutas ni respuestas).
- [ ] `7.6` Cierre: tareas marcadas, `MEMORY.md` actualizado y un commit atómico por micro-tarea.

**Criterio de cierre:** suite verde en SQLite y PostgreSQL, `migrate:fresh --seed` limpio, índices usados en `EXPLAIN`, backup/restore probado, ambos compose válidos, builds `prod` y PaaS correctos, `/up` 200, login 200, reportes/CSV/PDF funcionando y documentación actualizada.

---

## Verificación

```bash
# Desarrollo
docker compose config
docker compose up -d --build
docker compose exec -T app php artisan migrate:fresh --seed
docker compose exec -T app composer test
docker compose exec -T app composer test:postgres
docker compose exec -T app composer analyse
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app composer audit
docker compose exec -T app php artisan health:check
docker compose exec -T app php artisan schedule:list
docker compose exec -T postgres psql -U car_agency -d api_laravel_car_agency -c '\dt'
curl -i http://localhost:8000/up

# Operación
./docker/scripts/backup-postgres.sh backups
./docker/scripts/restore-postgres.sh backups/<archivo>.dump --confirm
docker compose logs postgres

# Producción y PaaS
docker compose -f docker-compose.prod.yml --env-file .env.production config
docker compose -f docker-compose.prod.yml --env-file .env.production build app
docker build -f docker/render/Dockerfile.render -t car-agency-laravel-api:paas .

# Contrato
docker compose exec -T app composer docs:export
npx @redocly/cli lint storage/app/openapi.json
```

> La suite rápida sigue usando SQLite `:memory:`; PostgreSQL se valida con `test:postgres`, con `migrate:fresh --seed` y con los smoke tests HTTP.
>
> Los comandos `docker compose config`, los builds y `npx` se ejecutan en el host porque son de Docker o Node; el resto va dentro de `app` con `-T`.
