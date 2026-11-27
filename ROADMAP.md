# ROADMAP — Incidente PaaS: EPERM al arrancar FrankenPHP (2026-11-26)

> **Propósito:** único plan de trabajo y registro de avance. Aquí vive el plan de corrección del fallo de arranque de la imagen PaaS. La guía operativa está en `AGENTS.md`, el historial en `MEMORY.md` y el uso en `README.md`; no dupliques esos contenidos aquí.
>
> **Regla de ejecución:** cada casilla es una micro-tarea con un commit atómico (`fix:`, `docs:`, `chore:`) y se marca `[x]` solo después de verificar en Docker con `composer test`, `composer analyse`, `pint --test` y `composer audit`. Nunca incluir `.env`.

---

## 📌 Estado actual

- **Actualizado:** 2026-11-26.
- **Estado:** corrección aplicada y validada en local (suite, PHPStan, Pint y `audit` en verde); pendiente solo el commit de cierre (4.3).
- **Resultado local:** `getcap /usr/local/bin/frankenphp` vacío; con `--user www-data --cap-drop=ALL` FrankenPHP arranca, `/up` → 200, `/api/cars` → 401 y `/docs/api` → 403; sin `--cap-drop` el contenedor queda `healthy` y `php artisan health:check --fresh` imprime `database: ok` y `cache: ok`.
- **Incidente:** al desplegar la imagen PaaS (`docker/render/Dockerfile.render`) en Render, el contenedor supera `APP_KEY` y `php artisan optimize`, pero el `exec "$@"` final de `docker/render/entrypoint.sh` falla con `/usr/local/bin/entrypoint: exec: line 79: frankenphp: Operation not permitted` (`Exited with status 126`).
- **Causa raíz confirmada:** el binario `/usr/local/bin/frankenphp` heredado de `dunglas/frankenphp:1-php8.3-alpine` lleva la *file capability* `cap_net_bind_service=ep` (`getcap` lo confirma). Render ejecuta el contenedor con un *bounding set* restringido que no incluye `CAP_NET_BIND_SERVICE`; cuando el kernel no puede conceder las capabilities del archivo, `execve` devuelve `EPERM` (126). En local no se reproduce porque Docker conserva esa capability por defecto. No es un problema de permisos (`755 root:root`), del `libphp` recompilado ni de `no-new-privileges`.
- **Corrección prevista:** retirar las file capabilities del binario en el build (`setcap -r`), porque la imagen escucha en `$PORT >= 1024` y no necesita puertos privilegiados.
- **Regla de orden:** este roadmap se limpia y recibe el plan antes de tocar la imagen; las fases posteriores solo marcan casillas y añaden resultados.

---

## Fase 0 — Limpieza del roadmap y registro del plan

- [x] **0.1 Sustituir `ROADMAP.md` por este plan.** Borrar el plan de pruebas integral ya cerrado (fases 0-6 y su estado) y dejar únicamente el encabezado, la regla de ejecución, el estado del incidente y las casillas de las fases 0-4. Criterio: `cat ROADMAP.md` contiene el plan completo y no queda contenido histórico ni duplicado de `AGENTS.md`/`MEMORY.md`. Resultado: plan del incidente cargado el 2026-11-26.
- [x] **0.2 Commit del plan antes de medir.** `git add ROADMAP.md && git commit -m "docs: registrar el plan de corrección del arranque PaaS"`. Criterio: commit atómico, repo limpio, `.env` fuera del commit.

## Fase 1 — Diagnóstico reproducible

- [x] **1.1 Confirmar las capabilities del binario.** `docker run --rm --entrypoint sh car-agency-laravel-api:paas -c 'ls -l /usr/local/bin/frankenphp; getcap /usr/local/bin/frankenphp'`. Criterio: binario `755 root:root` con `cap_net_bind_service=ep`; descarta un problema de permisos POSIX.
- [x] **1.2 Reproducir el `EPERM`.** `docker run --rm --user www-data --cap-drop=NET_BIND_SERVICE --entrypoint sh car-agency-laravel-api:paas -c '/usr/local/bin/frankenphp version'`. Criterio: mismo `Operation not permitted` y código 126 que en Render. Comprobar que sin `--cap-drop` arranca (`FrankenPHP v1.14.0 PHP 8.3.37`).
- [x] **1.3 Descartar las demás hipótesis.** Ejecutar el binario como `www-data` con `--security-opt no-new-privileges` y como `root`; revisar que `php`, `php artisan` y `composer` funcionan como `www-data`. Criterio: `no-new-privileges` no rompe el `exec`, `root` funciona y solo la ausencia de `CAP_NET_BIND_SERVICE` en el *bounding set* reproduce el fallo; la recompilación de `libphp` y `install-php-extensions` quedan descartadas.
- [x] **1.4 Verificar la corrección en un contenedor efímero.** `docker run --rm --user 0 --cap-drop=NET_BIND_SERVICE ... -c 'setcap -r /usr/local/bin/frankenphp && su -s /bin/sh www-data -c "/usr/local/bin/frankenphp version"'`. Criterio: tras `setcap -r`, `getcap` no imprime nada y el binario arranca sin la capability.

## Fase 2 — Corrección mínima y segura

- [x] **2.1 Retirar las file capabilities en `docker/render/Dockerfile.render`.** Añadir un `RUN` (antes de `USER www-data`, junto a los `COPY` de `Caddyfile`/`entrypoint`) que instale `libcap-utils` en un paquete virtual, ejecute `setcap -r /usr/local/bin/frankenphp`, compruebe que `getcap` queda vacío y elimine el paquete virtual. Criterio: el build falla si el binario conserva capabilities; se documenta en el comentario que `PORT >= 1024` no las necesita.
- [x] **2.2 Revisar el arranque y la documentación.** Confirmar que `entrypoint.sh` y el `CMD` no dependen de `CAP_NET_BIND_SERVICE` (el `Caddyfile` escucha en `:{$PORT:8080}`) y actualizar `README.md` (tabla de la vía PaaS y *troubleshooting*) y `AGENTS.md` (descripción de `docker/render/Dockerfile.render`) con la causa y la regla «no devolver capabilities al binario». Criterio: no queda ninguna referencia que atribuya el fallo a permisos de archivo o a `PORT < 1024`; el puerto 80 sigue requiriendo `--user 0`.
- [x] **2.3 Confirmar aislamiento de la vía VPS.** `git status --short` sobre `docker-compose.yml`, `docker-compose.prod.yml`, `docker/php/Dockerfile` y `docker/nginx/*`. Criterio: ningún archivo del stack de desarrollo o VPS cambia.

## Fase 3 — Validación local (build y arranque)

- [x] **3.1 Construir la imagen.** `docker build -f docker/render/Dockerfile.render -t car-agency-laravel-api:paas .`. Criterio: build correcto y reproducible.
- [x] **3.2 Comprobar permisos y capabilities.** `docker run --rm --entrypoint sh car-agency-laravel-api:paas -c 'ls -l /usr/local/bin/frankenphp; getcap /usr/local/bin/frankenphp'`. Criterio: `755 root:root` y sin capabilities.
- [x] **3.3 Arranque con el *bounding set* de Render.** `docker run --rm --user www-data --cap-drop=ALL -p 8080:8080 ... car-agency-laravel-api:paas` con `APP_KEY`, `CORS_ALLOWED_ORIGINS`, `SESSION_DRIVER=file`, `DB_CONNECTION=sqlite`/`DB_DATABASE` escribible, `RUN_MIGRATIONS=true` y `RUN_SEED=true`. Criterio: FrankenPHP arranca sin `EPERM`, `GET /up` → 200 `{"status":"ok"}`, `/api/cars` anónimo → 401 y `/docs/api` → 403 con `DOCS_ENABLED=false`.
- [x] **3.4 Arranque normal y healthcheck.** Repetir sin `--cap-drop=ALL` y con el `HEALTHCHECK` de la imagen. Criterio: también sirve en 8080, el contenedor queda `healthy` y `php artisan health:check --fresh` imprime `database: ok` y `cache: ok`. Parar y retirar el contenedor de prueba.

## Fase 4 — Calidad, memoria y cierre

- [x] **4.1 Suite y checks.** `composer test`, `composer analyse`, `./vendor/bin/pint --test` y `composer audit` dentro de `app`. Criterio: 0 fallos, PHPStan 0 errores, Pint sin diff y `audit` limpio; no se esperan cambios de código PHP, así que un fallo indicaría una regresión ajena a la corrección. Resultado: 524 tests/3304 aserciones, PHPStan 0, Pint 381 archivos y `audit` limpio. `audit` empezó a fallar con 5 avisos nuevos de Symfony publicados el 2026-11-25; se actualizaron parches a 7.4.24 (`http-kernel`, `http-foundation`, `mailer`, `mime`, `routing`, `var-dumper`) y `egulias/email-validator` 4.0.5.
- [x] **4.2 Memoria.** Añadir una entrada `2026-11-26` en `MEMORY.md` con la causa raíz, la corrección y la verificación; ejecutar `wc -l MEMORY.md` y condensar o archivar si se superan las 100 líneas. Criterio: entrada concisa y archivo dentro del límite.
- [x] **4.3 Commit y resumen.** `git status --short`, `git diff --check`, `git diff`, `git add` de los archivos tocados y commit convencional (`fix:` para la imagen y `docs:` para la documentación). Criterio: repo limpio, `.env` fuera y sin `git push` ni operaciones prohibidas; resumen con causa raíz, cambios y evidencias. Resultado: tres commits atómicos (`fix:`, `chore(deps):` y `docs:`) y árbol limpio.

---

## Criterio de cierre del ciclo

- El `EPERM` queda reproducido con `--cap-drop=NET_BIND_SERVICE` y ya no ocurre tras el build corregido.
- `getcap /usr/local/bin/frankenphp` no imprime nada y el binario arranca como `www-data` con `--cap-drop=ALL`.
- `GET /up` → 200 y el healthcheck de la imagen en verde en local; la vía VPS y el stack de desarrollo siguen intactos.
- `composer test`, `composer analyse`, `pint --test` y `composer audit` en verde.
- `README.md`, `AGENTS.md` y `MEMORY.md` reflejan la causa, la corrección y el límite «puerto >= 1024 / sin capabilities».
