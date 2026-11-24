# 🚗 Car Agency API

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)

API RESTful en **Laravel 12** para la gestión integral de una agencia de vehículos: inventario, sucursales, usuarios, roles, ventas transaccionales y reportes PDF.

El proyecto está preparado para ejecutarse con **Docker Compose**. No necesitas instalar PHP, Composer ni PostgreSQL en el host.

---

## 🚀 Stack tecnológico

| Componente | Tecnología | Rol |
| :--- | :--- | :--- |
| Framework | Laravel 12 | Enrutamiento, ORM Eloquent, validación y lógica HTTP. |
| Lenguaje | PHP 8.3 | Enums nativos, tipado estricto y DTOs `readonly`. |
| Base de datos | PostgreSQL 17 | Inventario, ubicaciones, usuarios y transacciones. |
| Servidor web | Nginx + PHP-FPM | Stack de desarrollo dentro de Docker. |
| Autenticación | Laravel Sanctum | Tokens bearer opacos con expiración. |
| Reportes | Dompdf | Generación de reportes PDF de ventas e inventario. |
| Tests | Pest | Suite de pruebas de Feature. |
| Formato | Laravel Pint | Estilo de código PHP. |

---

## ✨ Características principales

- **Autenticación con Sanctum:** `POST /api/auth/login` es la única ruta pública. El resto exige `Authorization: Bearer <token>`.
- **RBAC de 15 permisos:** la autorización se resuelve por permisos asociados a roles, no por el nombre del rol.
- **Rate limiting en login:** 5 intentos por minuto por email + IP. El sexto devuelve `429`.
- **Revocación de tokens:** cambiar contraseña, desactivar o eliminar un usuario invalida sus sesiones abiertas.
- **Autoservicio de identidad:** `/auth/me`, cambio de contraseña y reset administrativo con clave temporal.
- **Política de contraseñas local:** producción exige 8 caracteres con mayúsculas, minúsculas, números y símbolos; no hay llamadas HTTP salientes (`uncompromised` desactivado).
- **Bloqueo opcional de clave temporal:** con `ENFORCE_PASSWORD_CHANGE=true`, un usuario con `must_change_password` solo puede consultar su perfil, cambiar la clave o cerrar sesión.
- **Auditoría nativa:** login, usuarios, roles, coches, sucursales, ventas, catálogos y ubicaciones con actor, IP, User-Agent y redacción de secretos.
- **Retención y exportación de auditoría:** `audit:prune` programado a diario (`AUDIT_RETENTION_DAYS=90`) y `GET /api/audit-logs/export` en CSV con los mismos filtros del listado.
- **Estadísticas y rankings:** KPIs, inventario, tendencia mensual y rankings de vendedores, sucursales, ciudades, marcas y modelos.
- **Arquitectura por dominio:** `Cars`, `Locations`, `Sales`, `Users`, `Audit`, `Stats` y `Reports`.
- **Servicios y DTOs:** controladores delgados, reglas de negocio en `app/Services` y datos inmutables en `app/DTOs`.
- **Ventas transaccionales:** cálculo de impuestos, descuentos y tipos de cambio con redondeo a 2 decimales.
- **Soft deletes:** en `User` y `Car`. Las ventas no se borran: se cancelan con `SaleStatus::CANCELLED`.
- **Reportes PDF:** ventas e inventario, con filtros por fecha y sucursal.
- **Documentación OpenAPI 3.1.2:** `/docs/api` y `/docs/api.json`, con grupos, resúmenes y errores por dominio; importable en Postman.
- **Cabeceras de seguridad:** `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer-when-downgrade`, `Permissions-Policy` restrictiva y `server_tokens off` en Nginx; CSP solo en `/docs/api` y HSTS únicamente sobre HTTPS.
- **CI de calidad:** `.github/workflows/ci.yml` ejecuta tests, PHPStan, Pint y `composer audit` en una matriz PHP 8.2/8.3/8.4 con SQLite en memoria y añade un job contra PostgreSQL 17 real con `composer test:postgres`.
- **Datos demo en español:** 8 sucursales, 52 coches y 3 usuarios con roles `admin`, `manager` y `vendedor`.

---

## 🐳 Inicio rápido con Docker

### Requisitos

- Docker Engine o Docker Desktop con **Compose v2**.
- Puertos **8000** y **5432** libres en el host.
- No hace falta PHP, Composer, PostgreSQL ni Node.js.

### 1. Clonar el repositorio y crear el `.env`

```bash
git clone <URL_DEL_REPOSITORIO> API_Laravel_Car_Agency
cd API_Laravel_Car_Agency

cp .env.example .env
```

El archivo `.env.example` ya viene alineado con Docker Compose:

- `DB_HOST=postgres`
- `DB_DATABASE=api_laravel_car_agency`
- `DB_USERNAME=car_agency`
- `DB_PASSWORD=secret`
- `DB_SSLMODE=prefer` (sin TLS dentro de la red de Compose)
- `SESSION_DRIVER=file`
- `APP_URL=http://localhost:8000`
- `AUDIT_RETENTION_DAYS=90` (retención de `audit:prune`)
- `ENFORCE_PASSWORD_CHANGE=false` (bloqueo opcional de clave temporal)
- `SALES_MIN_DATE=2000-01-01` (fecha mínima admitida en `sale_date`)

> `.env` es local y está ignorado por Git. No lo subas al repositorio.

### 2. Levantar los contenedores

```bash
docker compose up -d --build
docker compose ps
```

Servicios que se levantan:

| Servicio | Puerto host | Descripción |
| :--- | :--- | :--- |
| `nginx` | `8000` | Sirve la API en `http://localhost:8000`. |
| `app` | Interno `9000` | PHP 8.3-FPM. |
| `postgres` | `127.0.0.1:5432` | PostgreSQL 17 con la base `api_laravel_car_agency` (solo loopback). |
| `scheduler` | — | `php artisan schedule:work`: ejecuta la purga de auditoría y de tokens. |

PostgreSQL tiene un healthcheck (`pg_isready`); el contenedor `app` espera a que esté listo antes de arrancar y publica su propio estado con `php artisan health:check` (base de datos + caché). `nginx` comprueba `/up` por HTTP. `scheduler` reutiliza la imagen, el entorno y los volúmenes de `app`; no publica puertos y no necesita recibir tráfico. El puerto de PostgreSQL se publica solo en `127.0.0.1` y la aplicación conecta con el usuario `car_agency` (rol sin privilegios de administración, dueño de la base y del schema `public`); el superusuario `postgres` solo lo usan el script de inicialización y los backups.

### 3. Instalar dependencias y generar la clave

```bash
docker compose exec -T app composer install
docker compose exec -T app php artisan key:generate
```

- Ejecuta `composer install` en el primer arranque o cuando cambie `composer.lock`.
- Ejecuta `key:generate` solo la primera vez, o si `APP_KEY` está vacío. Volver a generarla invalida datos cifrados.
- No uses `composer setup` ni `npm` dentro de `app`: la imagen PHP no incluye Node y el frontend Vite no forma parte de este stack.

### 4. Crear o preparar la base de datos

En el primer arranque, la imagen de PostgreSQL crea la base definida en `docker-compose.yml` (`POSTGRES_DB=api_laravel_car_agency`) y `docker/postgres/init/01-app-role.sh` crea el rol `car_agency`, le entrega la propiedad de la base y del schema `public` y, solo en desarrollo, crea la base `testing` que usa la suite contra PostgreSQL. Normalmente **no necesitas crear nada a mano**.

Si quieres comprobar que existen:

```bash
# Bases de datos del contenedor
docker compose exec -T postgres psql -U car_agency -d postgres -c '\l'

# Rol de la aplicación
docker compose exec -T postgres psql -U car_agency -d postgres -c '\du car_agency'
```

> El script de inicialización solo corre con el volumen `pg-data` vacío. Si el volumen se creó antes de este cambio, recréalo (`docker compose down -v`) o aplica el SQL a mano.

Aplica las migraciones y carga los datos demo:

```bash
docker compose exec -T app php artisan migrate --seed
```

Para volver a un estado limpio de demo:

```bash
docker compose exec -T app php artisan migrate:fresh --seed
```

> ⚠️ `migrate:fresh` elimina todas las tablas y datos. Úsalo solo en desarrollo.
>
> `migrate --seed` está pensado para el primer arranque. Los seeders de coches crean 50 coches de factory en cada ejecución; si repites el seed sobre la misma base, usa `migrate:fresh --seed` para evitar duplicados.

Verifica el resultado:

```bash
docker compose exec -T postgres psql -U car_agency -d api_laravel_car_agency -c \
  "SELECT 'branches' AS tabla, count(*) FROM branches UNION ALL SELECT 'cars', count(*) FROM cars UNION ALL SELECT 'users', count(*) FROM users;"
```

Tras un seed limpio deberías ver **8 sucursales**, **52 coches** y **3 usuarios**.

### 5. Comprobar que la API responde

```bash
curl -i http://localhost:8000/up
```

Debe responder `200 OK`. La base de la API es:

```text
http://localhost:8000/api
```

### 6. Hacer el primer login

```bash
curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@caragency.test","password":"password"}'
```

Respuesta esperada:

```json
{
  "message": "Success Login",
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "must_change_password": false
}
```

`must_change_password` es informativo: en `true` indica que la cuenta usa una
clave temporal (alta administrativa o reset) y el frontend debería pedir el
cambio antes de continuar. La API no bloquea el login por este flag.

Si defines `ENFORCE_PASSWORD_CHANGE=true`, el middleware `password.changed`
bloquea con `403` cualquier ruta protegida mientras el flag siga activo, salvo
`GET /api/auth/me`, `PUT /api/auth/password` y `POST /api/auth/logout`. Está
desactivado por defecto para no romper clientes que aún no gestionan el cambio.

Guarda el token y úsalo en las siguientes peticiones:

```bash
TOKEN="pega-aqui-el-token"

curl -s http://localhost:8000/api/cars?per_page=5 \
  -H "Authorization: Bearer $TOKEN"
```

Si tienes `jq` instalado, puedes capturar el token automáticamente:

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@caragency.test","password":"password"}' | jq -r .token)
```

---

## 👤 Usuarios demo

Los usuarios se crean con `UserSeeder`. La contraseña es el valor de `SEED_USER_PASSWORD`; si no está definida, se usa `password`.

| Email | Rol | Permisos |
| :--- | :--- | :---: |
| `admin@caragency.test` | `admin` | 15 |
| `manager@caragency.test` | `manager` | 11 |
| `vendedor@caragency.test` | `vendedor` | 5 |

> Estas credenciales son solo para desarrollo. Cámbialas antes de desplegar.

### Administrador de producción

En producción `ADMIN_EMAIL` y `ADMIN_PASSWORD` son **obligatorias**. El seeder
falla antes de escribir nada si el email falta, no es válido o conserva un
marcador `CHANGE_ME_*`, y si la contraseña falta, es `secret1234`, conserva un
`CHANGE_ME_*` o no cumple la política fuerte: 8 caracteres con mayúsculas,
minúsculas, números y símbolos.

| Variable | Obligatoria en producción | Valor de local/testing |
| :--- | :---: | :--- |
| `ADMIN_EMAIL` | Sí | `admin@agency.com` |
| `ADMIN_PASSWORD` | Sí | `secret1234` |
| `ADMIN_CODE` | No | `USR-0001` |
| `ADMIN_NAME` | No | `Administrador` |
| `ADMIN_LAST_NAME` | No | `General` |
| `ADMIN_DOCUMENT_NUMBER` | No | `V-00000001` |

> **Cámbiala en el primer arranque.** `ProductionAdminSeeder` vuelve a aplicar
> `ADMIN_PASSWORD` cada vez que se ejecuta `php artisan migrate --seed` o
> `php artisan db:seed`, así que la variable de entorno es la fuente de verdad.
> Define `ADMIN_EMAIL` y `ADMIN_PASSWORD` en `.env.production` antes de desplegar.

Variables disponibles (todas opcionales salvo email y contraseña en producción):
`ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_CODE`, `ADMIN_NAME`,
`ADMIN_LAST_NAME` y `ADMIN_DOCUMENT_NUMBER`. Viven en `config/seeders.php` para
funcionar con `config:cache`. Los usuarios demo de la tabla anterior siguen
existiendo solo en local/testing con `DatabaseSeeder` y `SEED_USER_PASSWORD`.

---

## 🔐 Autenticación y códigos de estado

El login devuelve un token bearer. En cada petición protegida envía:

```http
Authorization: Bearer <token>
```

Los tokens expiran a los **120 minutos**. `POST /api/auth/logout` invalida el token actual y `POST /api/auth/refresh` emite uno nuevo. Por rendimiento, la API no actualiza `last_used_at` en cada petición (`SANCTUM_LAST_USED_AT=false`); actívalo solo si necesitas auditar el uso por token.

Los tokens nuevos llevan el prefijo `car_` (`SANCTUM_TOKEN_PREFIX`), así que el valor devuelto por el login se ve como `4|car_xxxxxxxx...`. El prefijo ayuda a los escáneres de secretos a detectar un token filtrado. **Trátalo como opaco**: no parsees el segmento aleatorio ni asumas que empieza por un carácter concreto. Los tokens emitidos antes del cambio siguen siendo válidos.

### Autoservicio de identidad

| Método y ruta | Qué hace |
| :--- | :--- |
| `GET /api/auth/me` | Devuelve el perfil, roles, sucursales y la lista plana de permisos efectivos. |
| `PUT /api/auth/password` | Cambia la contraseña propia. Exige `current_password`, `password` y `password_confirmation`; revoca los demás tokens y conserva el de la petición. |
| `POST /api/users/{user}/reset-password` | Un administrador (`manage_users`) asigna una clave temporal, marca `must_change_password=true` y revoca todos los tokens del usuario. |

Cualquier clave fijada por un administrador (también con `PATCH /api/users/{user}`
y `password`) es temporal: marca `must_change_password=true`, revoca los tokens
del usuario y se limpia cuando este usa `PUT /api/auth/password`.

Un usuario desactivado recibe `401` en cualquier ruta autenticada, aunque su token aún no haya expirado. Para dejar el sistema sin administración, la API responde `409` al borrar, desactivar o quitar el rol `admin` al último administrador activo, y `422` si un rol queda sin permisos o se intenta modificar el `code` de un usuario.

| Código | Cuándo |
| :--- | :--- |
| `200` | Operación correcta. |
| `201` | Recurso creado. |
| `204` | Recurso eliminado sin cuerpo. |
| `401` | Sin token, token inválido/expirado, credenciales incorrectas o usuario inactivo. |
| `403` | Autenticado, pero sin el permiso requerido. |
| `404` | Recurso inexistente o con soft delete. |
| `409` | Conflicto de estado o integridad: venta ya cancelada, rol con usuarios, borrado con FK, moneda base duplicada o ausente. |
| `422` | Error de validación (detalle en `errors`) o regla de negocio incumplida (mensaje en `message`). |
| `429` | Demasiadas peticiones: se superó el límite del *rate limiter*. Espera un minuto. |

### Rate limits

Los límites se definen en `config/rate_limits.php` y se pueden ajustar con variables de entorno:

| Limiter | Rutas | Límite | Se cuenta por |
| :--- | :--- | :--- | :--- |
| `login` | `POST /api/auth/login` | 5/min (`RATE_LIMIT_LOGIN`) | email + IP |
| `login_ip` | `POST /api/auth/login` | 20/min (`RATE_LIMIT_LOGIN_IP`) | IP: corta la rotación de emails (*password spraying*) |
| `api-anon` | Cualquier ruta `/api/*` sin token | 30/min (`RATE_LIMIT_API_ANON`) | IP |
| `api` | Resto de rutas autenticadas | 120/min (`RATE_LIMIT_API`) | usuario (o IP sin token) |
| `reports` | `/api/reports/*` | 30/min (`RATE_LIMIT_REPORTS`) | usuario (o IP sin token) |
| `reports-pdf` | `/api/reports/*/pdf` | 5/min (`RATE_LIMIT_REPORTS_PDF`) | usuario (o IP sin token) |
| `audit-export` | `GET /api/audit-logs/export` | 10/min (`RATE_LIMIT_AUDIT_EXPORT`) | usuario (o IP sin token) |
| `health` | `GET /up` | 30/min (`RATE_LIMIT_HEALTH`) | IP |

`/up` se registra fuera del grupo `api` (para no arrastrar sesión ni CSRF), así que el limitador anónimo de la API no lo cubre: necesita su propio cupo `health`. Su resultado se cachea 10 s para absorber ráfagas de healthchecks; `php artisan health:check --fresh` fuerza una lectura directa de base de datos y caché. Si el store de caché falla, `/up` responde `503`, nunca `500`.

El reporte PDF es la ruta más cara: además del límite, corta en `REPORTS_MAX_ROWS` (300 por defecto) y responde `422` si el rango de fechas lo supera.

Ejemplos:

```bash
# Logout: invalida el token actual
curl -s -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer $TOKEN"

# Refresh: invalida el token actual y devuelve uno nuevo
curl -s -X POST http://localhost:8000/api/auth/refresh \
  -H "Authorization: Bearer $TOKEN"
```

---

## 🧭 Mapa de rutas y permisos

Las rutas viven en `routes/api.php` y Laravel añade el prefijo `/api`.

### Autenticación

| Método | Endpoint | Acceso |
| :--- | :--- | :--- |
| `POST` | `/api/auth/login` | Público, con rate limit `login`. |
| `GET` | `/api/auth/me` | Requiere token. |
| `PUT` | `/api/auth/password` | Requiere token. |
| `POST` | `/api/auth/logout` | Requiere token. |
| `POST` | `/api/auth/refresh` | Requiere token. |

> Con `ENFORCE_PASSWORD_CHANGE=true` y `must_change_password=true`, solo se
> permiten `GET /auth/me`, `PUT /auth/password` y `POST /auth/logout`.

### Recursos protegidos

| Grupo | Rutas base | Lectura | Escritura |
| :--- | :--- | :--- | :--- |
| Coches | `/api/cars` | `view_cars` | `create_cars`, `update_cars`, `delete_cars` |
| Catálogos de coches | `/api/car-types`, `/api/categories`, `/api/brands`, `/api/car-models`, `/api/colors` | `view_catalogs` | `manage_catalogs` |
| Catálogos de ventas | `/api/currencies`, `/api/payment-methods`, `/api/taxes` | `view_catalogs` | `manage_catalogs` |
| Estados de coche | `/api/car-statuses` | `view_catalogs` | Solo lectura |
| Ubicaciones | `/api/countries`, `/api/states`, `/api/cities`, `/api/branches` | `view_locations` | `manage_locations` |
| Ventas | `/api/sales` | `view_sales` | `create_sales` |
| Cancelación | `/api/sales/{sale}/cancel` | — | `cancel_sales` |
| Reportes | `/api/reports/*` | `view_reports` | Solo lectura |
| Usuarios | `/api/users` | `manage_users` | `manage_users` |
| Roles | `/api/roles` | `manage_users` | `manage_users` |
| Permisos | `/api/permissions` | `manage_users` | Solo lectura |
| Auditoría | `/api/audit-logs` y `/api/audit-logs/export` | `view_audit_logs` | Solo lectura |
| Estadísticas | `/api/stats/*` | `view_statistics` | Solo lectura |

Los recursos estándar (`cars`, catálogos, ubicaciones, usuarios y roles) siguen las convenciones REST:

```text
GET    /api/recurso           Listar (paginado)
POST   /api/recurso           Crear
GET    /api/recurso/{id}      Mostrar
PUT    /api/recurso/{id}      Actualizar
PATCH  /api/recurso/{id}      Actualizar parcialmente
DELETE /api/recurso/{id}      Eliminar
```

Las ventas solo permiten `index`, `show`, `store` y `PATCH /{sale}/cancel`.
En `POST /api/sales`, `exchange_rate` debe ser mayor que `0` (con hasta dos
decimales) y no superar `99999999.99`, que es el tope de la columna
`decimal(10,2)`. La tasa expresa cuántas unidades de la moneda de pago equivalen
a una de la moneda base; una venta en la moneda base usa tasa `1`, y cualquier
otro valor para esa moneda responde `422`.
`branch_id` debe coincidir con la sucursal del coche: la venta se registra
siempre en la sucursal del vehículo y una sucursal distinta responde `422`.
El vendedor se toma siempre del token autenticado: el payload no acepta
`user_id` y cualquier valor que se envíe se ignora. `sale_date` no puede ser
futura ni anterior a `SALES_MIN_DATE` (por defecto `2000-01-01`).

En coches, el alta solo admite `status=available`; la edición manual mueve
entre `available` y `reserved`, y `sold` lo asigna únicamente la venta. El
`price` admite como máximo `99999999999999.99`, el tope de `decimal(15,2)`, tanto
al crear como al editar; superarlo responde `422`. Un coche con venta
`completed` queda bloqueado para la edición manual (`422`) hasta que la venta se
cancele.

### Paginación

Los endpoints de listado aceptan `page` (desde 1) y `per_page` entre **1 y 100**. El tamaño por defecto es **15**:

```bash
curl -s "http://localhost:8000/api/cars?page=2&per_page=25" \
  -H "Authorization: Bearer $TOKEN"
```

La respuesta incluye `data`, `links` (`first`, `last`, `prev`, `next`) y `meta` con `current_page`, `last_page`, `per_page` y `total`. Los enlaces conservan los parámetros de la petición (por ejemplo `per_page`) y cada listado se ordena por `id`, así que las páginas son estables. `GET /api/car-statuses` y `GET /api/permissions` devuelven la colección completa, sin paginar.

### Filtros de listado

Todos los filtros son opcionales, se validan en el `FormRequest` y se conservan en los enlaces de paginación.

| Endpoint | Filtros |
| :--- | :--- |
| `GET /api/cars` | `status`, `branch_id`, `brand_id`, `car_model_id`, `car_type_id`, `category_id`, `color_id`, `year_min`, `year_max`, `price_min`, `price_max`, `search` |
| `GET /api/sales` | `status`, `branch_id`, `user_id`, `payment_method_id`, `payment_currency_id`, `from`, `to` |
| `GET /api/users` | `role_id`, `branch_id`, `is_active`, `search` |
| `GET /api/roles` | `search` |
| `GET /api/brands` | `country_id`, `is_active`, `search` |
| `GET /api/car-models` | `brand_id`, `is_active`, `search` |
| `GET /api/states` | `country_id`, `is_active`, `search` |
| `GET /api/cities` | `state_id`, `is_active`, `search` |
| `GET /api/branches` | `city_id`, `is_active`, `search` |
| `GET /api/car-types`, `/categories`, `/colors`, `/currencies`, `/payment-methods`, `/taxes`, `/countries` | `is_active`, `search` (y `is_base` en monedas) |

`from`/`to` son fechas `Y-m-d` inclusivas sobre `sale_date` y deben enviarse juntos. `search` busca por **prefijo sin distinguir mayúsculas** (`?search=cor` encuentra `Corolla`): en coches sobre el VIN, en catálogos y ubicaciones sobre el nombre, y en usuarios sobre nombre, apellido, email, código y documento. Los caracteres `%`, `_` y `!` se escapan, así que no se puede hacer búsqueda por substring; esto permite usar índices btree y no necesita la extensión `pg_trgm`.

```bash
curl -s "http://localhost:8000/api/cars?status=available&branch_id=1&search=1HG&per_page=10" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 📘 Documentación OpenAPI y Postman

El contrato se genera con **Scramble** en **OpenAPI 3.1.2** a partir de las rutas, los `FormRequest` y los `Resource`. `app/OpenApi/ApiDocumentation.php` añade encima los grupos en español, los resúmenes de cada operación, la paginación, los filtros de reportes y estadísticas, y los errores `403`, `409` y `429`.

| Recurso | URL |
| :--- | :--- |
| Referencia interactiva | `http://localhost:8000/docs/api` |
| Documento JSON | `http://localhost:8000/docs/api.json` |

Las dos rutas viven fuera de `/api` y las protege el Gate `viewApiDocs`: se abren en `local` y `testing`; en cualquier otro entorno, solo con `DOCS_ENABLED=true`.

### Exportar el documento

```bash
docker compose exec -T app composer docs:export
# → storage/app/openapi.json (ignorado por Git)
```

### Importar en Postman

1. Exporta el documento con el comando anterior.
2. En Postman: **Import → File** y selecciona `storage/app/openapi.json`.
3. Rellena las variables de la colección: `baseUrl` llega con `http://localhost:8000/api` y `bearerToken` queda vacía.
4. Ejecuta `POST /auth/login` y copia el `token` de la respuesta en `bearerToken`.

La colección importada contiene todas las peticiones de la API, con `Authorization: Bearer {{bearerToken}}` ya configurado a nivel de colección.

> Si en algún momento ejecutas `php artisan scramble:cache`, el documento queda congelado en caché; usa `php artisan scramble:clear` después de cambiar rutas o validaciones.

---

## 📊 Reportes

Todos los reportes requieren el permiso `view_reports`.

| Método | Endpoint | Descripción |
| :--- | :--- | :--- |
| `GET` | `/api/reports/sales/pdf` | PDF de ventas. Acepta `from` y `to` (`Y-m-d`, juntos o ninguno) y `branch_id`. |
| `GET` | `/api/reports/cars` | Agregado JSON del inventario por tipo, marca y estado. Acepta `branch_id`. |
| `GET` | `/api/reports/cars/pdf` | El mismo agregado de inventario en PDF. Acepta `branch_id`. |

Ejemplo:

```bash
curl -s -o ventas.pdf \
  "http://localhost:8000/api/reports/sales/pdf?from=2026-10-01&to=2026-10-31&branch_id=1" \
  -H "Authorization: Bearer $TOKEN"
```

> El reporte de ventas no filtra por estado: las ventas canceladas también aparecen en el PDF. Es el comportamiento actual del proyecto.

---

## 🕵️ Auditoría

Requiere el permiso `view_audit_logs`, asignado solo al rol `admin`. La tabla
`audit_logs` es append-only: la API expone únicamente lectura y responde `405` a
cualquier intento de crear, actualizar o borrar registros.

| Método | Endpoint | Descripción |
| :--- | :--- | :--- |
| `GET` | `/api/audit-logs` | Listado paginado, del más reciente al más antiguo. |
| `GET` | `/api/audit-logs/{id}` | Detalle con actor, IP, User-Agent y valores antiguos/nuevos. |
| `GET` | `/api/audit-logs/export` | CSV completo con los mismos filtros, sin paginación. |

Filtros opcionales del listado y de la exportación:

| Parámetro | Formato | Notas |
| :--- | :--- | :--- |
| `user_id` | entero | Actor que realizó la acción. |
| `action` | `auth.login`, `user.updated`, `sale.cancelled`, etc. | Validado contra el enum `AuditAction`. |
| `auditable_type` | `user`, `role`, `car`, `branch`, `sale`, `brand`, `car_model`, `car_type`, `category`, `color`, `currency`, `payment_method`, `tax`, `country`, `state`, `city` | Recurso afectado. |
| `auditable_id` | entero | Se combina con `auditable_type`. |
| `from` / `to` | `Y-m-d` | Rango inclusivo; van juntos o ninguno. |
| `per_page` | 1-100 | 15 por defecto. |

Acciones registradas:

- Autenticación: `auth.login`, `auth.login_failed` (incluye intentos bloqueados con
  `reason=throttled`), `auth.logout` y `auth.token_refreshed`.
- Usuarios y roles: `user.created`, `user.updated`, `user.deleted`,
  `user.activated`, `user.deactivated`, `user.roles_changed`,
  `user.password_changed`, `user.password_reset`, `role.created`, `role.updated`
  y `role.deleted`.
- Negocio: `car.created`, `car.updated`, `car.deleted`, `branch.created`,
  `branch.updated`, `branch.deleted`, `sale.created` y `sale.cancelled`.
- Catálogos: `catalog.created`, `catalog.updated` y `catalog.deleted` para
  marcas, modelos, tipos, categorías, colores, monedas, métodos de pago e
  impuestos. Una actualización solo se registra si cambia algún valor.
- Ubicaciones: `location.created`, `location.updated` y `location.deleted`
  para países, estados y ciudades. Las sucursales conservan `branch.*`.

Las contraseñas y los tokens nunca se guardan: las claves sensibles se sustituyen
por `[REDACTED]`. La auditoría se escribe después del commit de la transacción y,
si falla, la operación de negocio continúa y el error queda en el log.

```bash
# Últimos logins fallidos de octubre
curl -s "http://localhost:8000/api/audit-logs?action=auth.login_failed&from=2026-10-01&to=2026-10-31&per_page=50" \
  -H "Authorization: Bearer $TOKEN"

# Historial de un usuario concreto
curl -s "http://localhost:8000/api/audit-logs?auditable_type=user&auditable_id=3" \
  -H "Authorization: Bearer $TOKEN"

# Exportar a CSV los logins fallidos de octubre
curl -s -o auditoria.csv \
  "http://localhost:8000/api/audit-logs/export?action=auth.login_failed&from=2026-10-01&to=2026-10-31" \
  -H "Authorization: Bearer $TOKEN"
```

La exportación se transmite con `StreamedResponse` y `cursor()`, así que no
carga toda la tabla en memoria. Incluye BOM UTF-8 para Excel y escapa las celdas
que empiezan por `=`, `+`, `-`, `@`, tabulador, retorno de carro o salto de línea
para evitar inyección de fórmulas. El nombre del archivo es
`auditoria-YYYYMMDD-HHMMSS.csv`.

### Retención

La tabla no crece sin límite. `AUDIT_RETENTION_DAYS` (90 por defecto) define los
días que se conservan; el comando `audit:prune` borra solo los registros
anteriores al corte, en lotes de 1000 ids para no bloquear la tabla, y está
programado a diario a las 03:00 con `withoutOverlapping()`.

El mismo contenedor `scheduler` ejecuta `sanctum:prune-expired --hours=24` a las
03:15: sin esa purga, `personal_access_tokens` acumula una fila por cada login y
renovación, incluidas las ya caducadas.

```bash
# Usa AUDIT_RETENTION_DAYS
docker compose exec -T app php artisan audit:prune

# Fuerza una retención concreta
docker compose exec -T app php artisan audit:prune --days=30

# Comprueba la programación
docker compose exec -T app php artisan schedule:list
```

---

## 📈 Estadísticas

Requieren el permiso `view_statistics` (`admin` y `manager`). Todas las métricas de
ventas usan solo ventas `completed` y el importe `total_base_amount` en la moneda
base. `start_date` y `end_date` son `Y-m-d`, inclusivos y van juntos o ninguno;
`branch_id` es un filtro opcional.

| Método | Endpoint | Devuelve | Parámetros |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/stats/summary` | KPIs del periodo, inventario y estructura activa. | `start_date`, `end_date`, `branch_id` |
| `GET` | `/api/stats/top-users` | Ranking de vendedores. | fechas, `branch_id`, `limit` |
| `GET` | `/api/stats/top-branches` | Ranking de sucursales. | fechas, `branch_id`, `limit` |
| `GET` | `/api/stats/top-cities` | Ranking de ciudades. | fechas, `branch_id`, `limit` |
| `GET` | `/api/stats/top-brands` | Ranking de marcas. | fechas, `branch_id`, `limit` |
| `GET` | `/api/stats/top-models` | Ranking de modelos. | fechas, `branch_id`, `limit` |
| `GET` | `/api/stats/inventory` | Inventario actual por estado y sucursal. | `branch_id` |
| `GET` | `/api/stats/sales/trend` | Serie mensual de ventas (12 meses por defecto). | fechas, `branch_id` |

Los rankings aceptan `limit` entre **1 y 100** (10 por defecto) y devuelven menos
filas si no hay suficientes datos. El orden es estable por número de ventas,
importe, nombre e id. Los rankings conservan usuarios y coches históricos con soft
delete; el inventario solo cuenta coches actuales.

Los ocho agregados se cachean `STATS_CACHE_TTL` segundos (45 por defecto) con una
clave por endpoint y combinación de filtros (`start_date`, `end_date`,
`branch_id`, `limit`). Crear o cancelar una venta incrementa `stats:version` y
descarta todas las entradas de golpe, sin tocar la caché de permisos; con un TTL
de `0` la caché queda desactivada. Las respuestas pueden ir hasta ese desfase por
detrás de la base de datos, de sobra para un dashboard.

```bash
# Resumen del mes en curso para una sucursal
curl -s "http://localhost:8000/api/stats/summary?start_date=2026-11-01&end_date=2026-11-30&branch_id=1" \
  -H "Authorization: Bearer $TOKEN"

# Top 5 vendedores y tendencia de los últimos 12 meses
curl -s "http://localhost:8000/api/stats/top-users?limit=5" \
  -H "Authorization: Bearer $TOKEN"

curl -s "http://localhost:8000/api/stats/sales/trend" \
  -H "Authorization: Bearer $TOKEN"
```

`summary` responde con `base_currency`, `meta`, `sales` (`completed_count`,
`total_base_amount`, `average_ticket`), `inventory`, `active_users` y
`active_branches`. Los rankings usan `{base_currency, meta, data}` y la tendencia
`{base_currency, meta, data}` con un punto por mes; los meses sin ventas se
rellenan con cero.

---

## 🧱 Arquitectura y estructura

El código está organizado por dominio de negocio, no por carpetas técnicas genéricas:

```text
app/
├── DTOs/                 # Objetos de transferencia inmutables
├── Enums/                # Enums string-backed (Cars, Sales, Users)
├── Exceptions/           # Excepciones de dominio
├── Http/
│   ├── Controllers/      # Controladores delgados por dominio
│   ├── Requests/         # Validación con FormRequest
│   └── Resources/        # Serialización JSON
├── Models/               # Modelos Eloquent por dominio
├── OpenApi/              # Metadatos de la documentación (grupos, errores)
└── Services/             # Reglas de negocio y transacciones

resources/views/reports/  # Vistas Blade para PDF
routes/api.php            # Todas las rutas de la API
```

Flujo de una petición:

```text
routes/api.php
  → FormRequest
  → DTO::fromRequest()
  → Service
  → Model / transacción
  → Resource
  → JSON
```

Puntos importantes:

- Los controladores no contienen reglas de negocio; delegan en `app/Services`.
- No hay capa repository. Los servicios usan Eloquent directamente.
- Las transacciones financieras viven en `SaleService` y usan `DB::transaction`, `lockForUpdate` y redondeo a 2 decimales.
- Los modelos usan subnamespaces de dominio: `App\Models\Cars\Car`, `App\Models\Users\User`, etc.
- Los Resources exponen relaciones solo con `whenLoaded()`; el servicio debe hacer el eager loading.

---

## 🧪 Tests y calidad

Los tests usan Pest con `RefreshDatabase` y **SQLite en memoria**, por lo que no tocan la base PostgreSQL de desarrollo.

```bash
# Suite completa (SQLite en memoria)
docker compose exec -T app ./vendor/bin/pest

# Un archivo o directorio
docker compose exec -T app ./vendor/bin/pest tests/Feature/Cars

# Filtrar por nombre
docker compose exec -T app ./vendor/bin/pest --filter='login'

# Comando del proyecto (limpia configuración y ejecuta la suite)
docker compose exec -T app composer test

# La misma suite contra PostgreSQL (base `testing` del compose)
docker compose exec -T app composer test:postgres

# Formatear con Pint
docker compose exec -T app ./vendor/bin/pint

# Comprobar formato sin modificar archivos
docker compose exec -T app ./vendor/bin/pint --test

# Análisis estático (PHPStan/Larastan nivel 7, incluye `--memory-limit=1G`)
docker compose exec -T app composer analyse

# Vulnerabilidades conocidas en dependencias PHP
docker compose exec -T app composer audit
```

Estado verificado de la suite (2026-11-22): **491 tests / 3048 aserciones** en SQLite y **491 tests / 3027 aserciones** en PostgreSQL, **PHPStan nivel 7 con 0 errores**, **Pint limpio (380 archivos)** y **`composer audit` sin avisos**. `phpunit.xml` fuerza SQLite `:memory:` (suite rápida) y `phpunit-postgres.xml` fuerza `DB_CONNECTION=pgsql` + `DB_DATABASE=testing`; host, puerto y credenciales llegan del entorno.

El flujo crítico también está cubierto en Pest (`tests/Feature/Smoke/ApiSmokeTest.php`): login, `/auth/me`, estadísticas, exportación CSV de auditoría y logout con revocación del token.

### Verificación integral

Antes de cerrar una entrega se repite esta batería. La suite usa SQLite en memoria, pero `migrate:fresh --seed` y los smoke tests sí tocan PostgreSQL:

```bash
docker compose exec -T app php artisan migrate:fresh --seed
docker compose exec -T app composer test
docker compose exec -T app composer test:postgres
docker compose exec -T app composer analyse
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app composer audit
docker compose exec -T app composer docs:export
npx @redocly/cli lint storage/app/openapi.json   # en el host: la imagen PHP no trae Node
docker compose exec -T app php artisan schedule:list
docker compose exec -T app php artisan health:check
docker compose config >/dev/null
docker compose -f docker-compose.prod.yml --env-file .env.production config >/dev/null
docker build -f docker/php/Dockerfile --target prod -t car-agency-laravel-api:prod .
docker build -f docker/render/Dockerfile.render -t car-agency-laravel-api:paas .
```

Redocly valida el contrato sin errores; el único aviso es `no-server-example.com` porque `servers` apunta a `http://localhost:8000/api`, que es lo correcto en desarrollo. Los dos builds se ejecutan en el host porque son de Docker.

El smoke HTTP real (2026-11-17) comprobó login con prefijo `car_`, `429` anónimo a los 30/min, rotación de emails frenada a los 20/min, IP real vía `X-Forwarded-For` con `TRUSTED_PROXIES`, auditoría de catálogos, venta con `exchange_rate=0` y sucursal incoherente (`422`), estado `sold` manual (`422`), coche con soft delete visible en la venta histórica y `/up` en `200`. Ese recorrido detectó que el limitador anónimo contaba también el tráfico autenticado; quedó corregido y cubierto por `RateLimitTest`.

El cierre de 2026-11-22 añadió el humo de producción real: stack `-p car-agency-prod-test` con volumen nuevo, `build app`, `migrate --force --seed`, `optimize`, `/up` y login `200`, PDF de reporte `200`, CSV de auditoría `200` con BOM UTF-8, `/docs/api` `403` y `health:check` correcto (29 tablas propiedad de `car_agency`). La imagen PaaS se construyó y arrancó por separado: `/up` y login `200`, `/docs/api` `403` y `/api/cars` anónimo `401`. El smoke de desarrollo pasó 19/19 y confirmó que una cabecera `X-Forwarded-For` falsificada se ignora si el par no es de confianza (la auditoría registró la IP de la conexión `172.18.0.1`). `composer docs:export` + Redocly validan los 55 paths; antes quedaban 43 avisos por enums booleanos duplicados y ahora solo el esperado `no-server-example.com`.

### CI de calidad

`.github/workflows/ci.yml` se ejecuta en cada `push` y `pull_request` con dos jobs. El job `quality` usa una matriz de PHP **8.2, 8.3 y 8.4** (el rango que declara `composer.json`) sobre SQLite en memoria. El job `postgres` corre en PHP 8.3 contra un servicio `postgres:17-alpine` con locale `C.UTF-8`, ejecuta `migrate:fresh --seed --force` y después `composer test:postgres`, `composer analyse`, `pint --test` y `composer audit`. La imagen de desarrollo sigue usando PHP 8.3. Es independiente del workflow de Docker Hub, que solo publica la imagen PaaS.

### Reglas que fijan los tests

- El número de consultas de cada listado no crece con las filas (`tests/Feature/Performance/QueryCountTest.php`).
- Ningún N+1 pasa desapercibido: `Model::preventLazyLoading()` está activo fuera de producción.
- El 403/401/429 de cada ruta (incluido el 401 de `/auth/login`) y el contrato de la documentación OpenAPI están cubiertos en `tests/Feature/Docs`.
- Los importes se comparan redondeados a 2 decimales.
- La política de contraseñas es local y determinista; `PasswordPolicyTest` comprueba que `uncompromised` está desactivado.
- `AuditPruneCommandTest`, `AuditLogExportTest` y `EnforcePasswordChangeTest` fijan retención, CSV y bloqueo opcional.

---

## 🛠️ Comandos útiles

```bash
# Estado de los contenedores
docker compose ps

# Logs
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f postgres

# Shell dentro del contenedor PHP
docker compose exec -T app sh

# Rutas registradas
docker compose exec -T app php artisan route:list
docker compose exec -T app php artisan route:list --path=api

# Limpiar cachés de configuración, rutas y vistas
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:clear

# Retención de auditoría y tokens, y programación
docker compose exec -T app php artisan audit:prune
docker compose exec -T app php artisan audit:prune --days=30
docker compose exec -T app php artisan sanctum:prune-expired --hours=24
docker compose exec -T app php artisan schedule:list
docker compose logs -f scheduler

# Cliente psql (rol de la aplicación)
docker compose exec -T postgres psql -U car_agency -d api_laravel_car_agency

# Apagar contenedores (conserva la base de datos)
docker compose down

# Apagar y borrar volúmenes: elimina PostgreSQL y también vendor/
docker compose down -v
```

> `docker compose down -v` es destructivo: borra la base de datos y el volumen `vendor`. Después tendrás que ejecutar `composer install` y `migrate --seed` otra vez.

### Si tu usuario no es UID/GID 1000

El target `dev` crea el usuario `app` con UID/GID 1000. Si tu usuario de WSL usa otros IDs, reconstruye la imagen:

```bash
docker compose build \
  --build-arg UID=$(id -u) \
  --build-arg GID=$(id -g) \
  app

docker compose up -d
```

### Si los puertos están ocupados

Edita `docker-compose.yml` y cambia el puerto del host:

```yaml
nginx:
  ports:
    - "8080:80"

postgres:
  ports:
    - "5433:5432"
```

Luego actualiza `APP_URL` en `.env` si cambias el puerto de Nginx.

---

## 🚀 Despliegue en Producción

El repositorio incluye un stack de producción separado que **no modifica** el entorno de desarrollo (`docker-compose.yml` y `docker/php/Dockerfile` siguen igual):

| Archivo | Propósito |
| :--- | :--- |
| `docker-compose.prod.yml` | PHP-FPM (`target: prod`), Nginx en `127.0.0.1:80` (solo loopback, espera un proxy TLS), PostgreSQL 17 sin puerto público, `worker` (`queue:work`) y `scheduler` (`schedule:work`). Caché y colas en PostgreSQL; sesiones en el volumen `app-storage`. |
| `docker-compose.tls.yml` + `docker/nginx/tls.conf` | Superposición opcional para terminar TLS dentro del stack: publica `80`/`443`, redirige HTTP a HTTPS y monta `docker/certs`. |
| `docker/nginx/Dockerfile.prod` | Build multi-stage: parte de `car-agency-laravel-api:prod`, copia `public/` y sirve los estáticos con Nginx. |
| `.env.production.example` | Plantilla versionable, sin credenciales reales (marcadores `CHANGE_ME_*`). |

> ⚠️ **El orden importa.** La imagen de Nginx se construye con `COPY --from=car-agency-laravel-api:prod`, así que la imagen de `app` debe existir **antes** de levantar el stack completo. Por eso el paso 2 se ejecuta por separado.

### Paso 1: Preparar credenciales

```bash
cp .env.production.example .env.production
```

Edita `.env.production` y sustituye todos los `CHANGE_ME_*`, el `APP_URL` real (`https://api.example.com`) y `CORS_ALLOWED_ORIGINS` (nunca uses `*` en producción: el arranque HTTP falla con un `500` explicativo si está vacío o contiene el comodín). Añade también `ADMIN_EMAIL` y `ADMIN_PASSWORD` para el administrador que crea el seed. Mantén sincronizadas las variables `DB_*` con `POSTGRES_*`: la aplicación conecta con `DB_USERNAME=car_agency`, que debe coincidir con `POSTGRES_APP_USER`, y `DB_PASSWORD` con `POSTGRES_APP_PASSWORD`. Deja `DB_SSLMODE=prefer` mientras PostgreSQL viva en la red de Compose (sin TLS); si algún día usas un PostgreSQL gestionado, súbelo a `require` o `verify-full`. Deja `APP_KEY` vacía por ahora: se genera en el paso 2, cuando la imagen ya exista.

Para TLS deja `TRUSTED_PROXIES=172.28.0.0/16` (la subnet que fija `docker-compose.prod.yml` en la red `car-agency-prod`), `HSTS_ENABLED=true` y `SESSION_SECURE_COOKIE=true`. `HSTS_INCLUDE_SUBDOMAINS` solo debe activarse si **todos** los subdominios del dominio sirven HTTPS. Los detalles y las dos vías de terminación TLS están en el paso 5.

El archivo `.env.production` está ignorado por Git y `.dockerignore` evita que se copie dentro de la imagen. Sesiones, caché y colas usan el stack existente (`SESSION_DRIVER=file` dentro de `app-storage`, `CACHE_STORE=database` y `QUEUE_CONNECTION=database`), sin dependencias extra. Los logs salen por `stderr` (`LOG_CHANNEL=stderr`) para que Docker los recoja con `docker compose logs`.

### Paso 2: Construir la imagen base (App)

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production build app
```

**Obligatorio hacerlo primero.** Este comando construye el target `prod` de `docker/php/Dockerfile` y la etiqueta `car-agency-laravel-api:prod`. Si se salta, al levantar el stack el build de Nginx falla con `pull access denied`: `docker/nginx/Dockerfile.prod` arranca con `FROM car-agency-laravel-api:prod` y solo copia `/var/www/html/public` de esa imagen, así que necesita que ya exista en el daemon local.

Con la imagen ya construida, genera la clave y pégala en `APP_KEY=` de `.env.production` (incluye el prefijo `base64:`):

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production \
  run --rm app php artisan key:generate --show
```

### Paso 3: Levantar el orquestador

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
```

Compose construye la imagen de Nginx a partir de la del paso 2, arranca `postgres` y espera su healthcheck (`pg_isready`), levanta `app` y espera a que arranque y solo entonces inicia `nginx`, que publica **solo `127.0.0.1:80`**. PostgreSQL queda aislado en la red interna `car-agency-prod`. El puerto público `80`/`443` lo atiende un proxy TLS del host (paso 5) o la superposición `docker-compose.tls.yml`.

El healthcheck de `app` combina `fsockopen` al puerto 9000 con `php artisan health:check` (base de datos + caché), y `nginx` tiene el suyo contra `/up`. `/up` responde `200 {"status":"ok"}` cuando la base y la caché responden y `503 {"status":"unavailable"}` cuando alguna falla, sin exponer el error (el detalle va al log). En el primer despliegue, antes de `migrate`, `health:check` falla porque aún no existen las tablas; por eso `nginx` usa `service_started` y no bloquea el paso 4. Después de migrar, `docker compose ps` debe mostrar ambos servicios como `healthy`.

El servicio `worker` reutiliza la misma imagen y el mismo `.env.production`, ejecuta `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` y comparte el volumen `app-storage`. No publica puertos y tiene el healthcheck desactivado. Procesa los jobs de la tabla `jobs` (`QUEUE_CONNECTION=database`); `--max-time=3600` recicla el proceso cada hora para liberar memoria y `restart: unless-stopped` lo relanza.

El servicio `scheduler` arranca con la misma imagen y el mismo `.env.production`, ejecuta `php artisan schedule:work` y comparte el volumen `app-storage`. No publica puertos y tiene el healthcheck desactivado (no sirve HTTP). Gracias a él corren a diario `audit:prune` (03:00) y `sanctum:prune-expired --hours=24` (03:15) sin depender del cron del host.

### Paso 4: Migrar y optimizar

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force --seed
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
```

> En el primer despliegue, `worker` y `scheduler` arrancan antes de que existan las tablas y reinician en bucle hasta que corre `migrate`; `restart: unless-stopped` los recupera solos en cuanto la base está lista. Si quieres evitar ese ruido en los logs, levanta primero solo `postgres` y `app`, ejecuta este paso y después `up -d` con el stack completo.

- `migrate --force --seed` crea el esquema y también las tablas `cache`, `jobs` y `failed_jobs`; los drivers `database` de caché y colas dependen de ellas, así que este paso va antes de recibir tráfico. El `--seed` en producción solo siembra roles/permisos y el administrador definido por `ADMIN_EMAIL`/`ADMIN_PASSWORD` (añade esas variables a `.env.production`); no inserta datos demo.
- `optimize` cachea configuración, eventos, rutas y vistas compiladas (`config:cache`, `event:cache`, `route:cache`, `view:cache`) para que cada petición no reconstruya el bootstrap.

### Ajuste de PHP-FPM

El pool vive en `docker/php/www.conf` y lo comparten desarrollo y producción. `pm.max_children` se calcula con la RAM del servidor o del límite del contenedor:

```text
pm.max_children = floor((RAM_MB - 512) / 75)
```

512 MB quedan para el sistema, PostgreSQL y Nginx, y 75 MB es el consumo medio de un worker de esta API. Referencias: 1 GB → 7, 2 GB → 20 (valor por defecto), 4 GB → 47, 8 GB → 102. Si cambias el valor, **recrea** los contenedores (`docker compose -f docker-compose.prod.yml --env-file .env.production up -d --force-recreate app worker scheduler`): FPM solo lee el pool al arrancar. `pm.max_requests=500` recicla cada worker para evitar fugas y `request_terminate_timeout=60s` corta peticiones que superen el `fastcgi_read_timeout` de Nginx.

> Las cachés de `optimize` se escriben en `bootstrap/cache` dentro del contenedor, no en el volumen `app-storage`: se pierden al recrear `app`, así que vuelve a ejecutar `optimize` después de cada despliegue. Si cambias `.env.production`, ejecuta `php artisan optimize:clear` y reinicia `app`; con la configuración cacheada, los cambios de entorno no se leen.

### OPcache de producción

El target `prod` copia `docker/php/opcache-prod.ini`: `opcache.enable=1`, `memory_consumption=128`, `max_accelerated_files=10000`, `validate_timestamps=0` y JIT desactivado (`jit_buffer_size=0`). El código va horneado en la imagen, así que no se revalida en cada petición; **cada despliegue debe crear un contenedor nuevo** (no basta con copiar archivos dentro de `app`), porque con `validate_timestamps=0` un cambio en disco no se vería hasta reiniciar FPM. El JIT queda apagado a propósito: la API está limitada por E/S y PostgreSQL, y el JIT añade memoria y tiempo de compilación sin mejora medible. El entorno de desarrollo no usa este archivo, así que los cambios de código siguen aplicándose al instante.

### Compresión de respuestas

Nginx comprime con gzip las respuestas `application/json`, `text/csv` y `text/plain` (`gzip_comp_level 5`, `gzip_min_length 512`). Los PDF **no** se comprimen: ya vienen comprimidos y gzip solo gastaría CPU y latencia. El JSON de la API y la exportación CSV de auditoría viajan comprimidos cuando el cliente envía `Accept-Encoding: gzip`; en PaaS el `Caddyfile` aplica `encode zstd br gzip`.

### Ajuste de PostgreSQL

El contenedor `postgres` arranca con memoria y log de consultas lentas configurables por entorno:

| Variable | Producción | Desarrollo | Criterio |
| :--- | :--- | :--- | :--- |
| `POSTGRES_SHARED_BUFFERS` | `512MB` | `256MB` | Caché de páginas: ~25 % de la RAM del servidor (2 GB → 512 MB, 4 GB → 1 GB). |
| `POSTGRES_EFFECTIVE_CACHE_SIZE` | `1536MB` | `768MB` | Estimación para el planificador: ~50-75 % de la RAM del servidor. |
| `POSTGRES_WORK_MEM` | `8MB` | `8MB` | Memoria por operación de ordenación/hash; hay pocas conexiones concurrentes. |
| `POSTGRES_LOG_MIN_DURATION_STATEMENT` | `1000` | `1000` | Milisegundos a partir de los que una consulta se registra. |

El log lento va a `stderr`, así que se lee con `docker compose logs postgres` (no hay tabla `slow_log`):

```bash
# Provoca una consulta lenta a propósito
docker compose exec -T postgres psql -U car_agency -d api_laravel_car_agency \
  -c "SELECT pg_sleep(1.3);"

# La línea aparece como "LOG: duration: 1302.350 ms statement: ..."
docker compose logs --tail=20 postgres
```

Cambiar estas variables exige recrear el contenedor (`docker compose up -d --force-recreate postgres` en desarrollo; en producción, con `-f docker-compose.prod.yml --env-file .env.production`). PostgreSQL solo las lee al arrancar.

### Paso 5: TLS y proxy inverso

El compose base **no publica HTTP plano a Internet**: Nginx escucha solo en `127.0.0.1:80`. Hay dos formas de terminar TLS; elige una.

#### Vía recomendada: proxy externo en el host

Caddy, Nginx o Traefik termina HTTPS y reenvía a `http://127.0.0.1:80`. Es la vía con renovación automática y la única que permite mantener el stack sin certificados dentro de Docker.

1. Apunta el DNS (`A`/`AAAA`) de `api.example.com` al servidor.
2. Deja en `.env.production`: `APP_URL=https://api.example.com`, `TRUSTED_PROXIES=172.28.0.0/16`, `HSTS_ENABLED=true` y `SESSION_SECURE_COOKIE=true`.
3. Levanta el stack (pasos 2-4) y comprueba que responde en loopback: `curl -i http://127.0.0.1/up`.
4. Configura el proxy. Ejemplo con **Caddy** (obtiene y renueva el certificado solo):

```caddyfile
api.example.com {
    encode zstd gzip
    reverse_proxy 127.0.0.1:80
}
```

Caddy envía `X-Forwarded-For`, `X-Forwarded-Proto` y `X-Forwarded-Host` automáticamente. Ejemplo con **Nginx + Certbot**:

```nginx
# Redirección global de HTTP a HTTPS (Certbot añade el bloque TLS al ejecutar
# `certbot --nginx -d api.example.com`).
server {
    listen 80;
    server_name api.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.example.com;

    ssl_certificate     /etc/letsencrypt/live/api.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.example.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:80;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host  $host;
        proxy_set_header X-Forwarded-Port  443;
    }
}
```

```bash
certbot --nginx -d api.example.com      # emite el certificado y edita el sitio
systemctl enable --now certbot.timer    # renovación automática
nginx -t && systemctl reload nginx
```

**Renovación:** Certbot renueva a los 60 días y recarga Nginx; Caddy lo hace sin intervención. Tras renovar, no hace falta tocar el stack de la API: el certificado vive en el proxy.

**Cutover:** baja el TTL del DNS al menos una hora antes; emite el certificado con el puerto 80 libre (o con el método DNS-01 si ya hay un servicio); arranca el stack nuevo en `127.0.0.1:80`; activa el proxy y valida `https://api.example.com/up`, el `301` desde `http://` y las cabeceras de seguridad. Mantén el despliegue anterior hasta comprobar login y reportes; después retíralo.

> El proxy debe reenviar `X-Forwarded-Proto`; sin esa cabecera Laravel no puede saber que la petición original era HTTPS y no enviará HSTS. La IP del proxy también debe entrar en `TRUSTED_PROXIES`: el compose fija la subnet `172.28.0.0/16` para la red `car-agency-prod`, que es la que debe declararse por defecto. Si el proxy corre en otro host o cambias la subnet, añade su IP o rango separado por comas; nunca `*`, porque con un comodín un cliente puede falsificar la IP de auditoría y de los limitadores mediante `X-Forwarded-For`. El proxy debe **sobrescribir** esa cabecera con la IP real; si la cadena trae varios saltos, la app descarta la cabecera y usa la IP del proxy inmediato.

#### Vía alternativa: TLS propio dentro del stack

Para VPS sin proxy o con certificados de una CA interna. La superposición publica `80`/`443`, redirige HTTP a HTTPS y monta los certificados en `/etc/nginx/certs`.

```bash
mkdir -p docker/certs
# Certificado y clave con los nombres exactos que espera docker/nginx/tls.conf
sudo cp /etc/letsencrypt/live/api.example.com/fullchain.pem docker/certs/
sudo cp /etc/letsencrypt/live/api.example.com/privkey.pem   docker/certs/
sudo chown root:root docker/certs/*.pem && sudo chmod 600 docker/certs/*.pem

docker compose -f docker-compose.prod.yml -f docker-compose.tls.yml \
  --env-file .env.production up -d
```

- `docker/certs/*.pem` está ignorado por Git; nunca se hornea en la imagen (`.dockerignore` excluye el directorio).
- `tls.conf` marca la petición como HTTPS (`HTTPS`, `REQUEST_SCHEME` y `X-Forwarded-Proto`) para que Laravel aplique HSTS y genere URLs `https://`.
- Comprueba la configuración tras copiar certificados: `docker compose -f docker-compose.prod.yml -f docker-compose.tls.yml --env-file .env.production exec -T nginx nginx -t`.
- El healthcheck heredado sondea `http://127.0.0.1/up`, pero con esta superposición el puerto 80 solo redirige a 443. La superposición lo sustituye por `wget --no-check-certificate https://127.0.0.1/up`, porque el certificado puede ser de una CA interna o estar emitido para el dominio real y no para `127.0.0.1`.
- **Renovación:** renueva con `certbot renew` (DNS-01, o `--standalone` con el stack parado) y copia de nuevo los `.pem`; recarga sin cortar el servicio con `... exec -T nginx nginx -s reload`. Si el certificado caduca, el healthcheck del proxy fallará y el navegador rechazará la conexión.

> La superposición usa `ports: !override` para sustituir el `127.0.0.1:80` del compose base (requiere Docker Compose 2.24+; este repo usa v5). Si cambias `APP_URL` o el dominio, actualiza también `server_name` en `docker/nginx/tls.conf`.

### Verificar y detener

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production ps

# Stack base: solo accesible desde el propio host (el proxy escucha delante)
curl -i http://127.0.0.1/up
curl -i http://127.0.0.1/api/cars   # 401: la API exige token Bearer

# Con TLS (proxy externo o superposición tls)
curl -i https://api.example.com/up
curl -I http://api.example.com      # 301 a https://
curl -sI https://api.example.com/up | grep -i strict-transport-security

# Detener conservando los volúmenes
docker compose -f docker-compose.prod.yml --env-file .env.production down
```

> Solo Nginx publica un puerto, y en el compose base lo hace en `127.0.0.1:80`; PostgreSQL queda aislado en la red interna. El volumen `app-storage` conserva `storage/` (sesiones, logs y cachés de framework) entre despliegues. `docker compose ... down -v` borra la base de datos y el volumen de storage.

### Backups y restauración

Dos scripts cubren el ciclo completo sobre el stack de Compose:

| Script | Qué hace |
| :--- | :--- |
| `docker/scripts/backup-postgres.sh` | `pg_dump --format=custom` (ya comprimido) y retención por días. |
| `docker/scripts/restore-postgres.sh` | Detiene `app`/`worker`/`scheduler`, elimina y recrea la base con `car_agency` como dueño, importa el dump con `pg_restore` conectado como ese mismo rol y vuelve a arrancar los servicios (`nginx -s reload` incluido). Exige `--confirm`. |

```bash
# Copia en ./backups (ignorado por Git), conservando 7 días
BACKUP_RETENTION_DAYS=7 ./docker/scripts/backup-postgres.sh backups

# Restauración (recrea la base de datos)
./docker/scripts/restore-postgres.sh backups/api_laravel_car_agency-20261119-210600.dump --confirm
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1/up
```

Los scripts leen `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_APP_USER` y `POSTGRES_APP_PASSWORD` del contenedor `postgres`, así que las credenciales no viajan en la línea de comandos del host. `COMPOSE_FILE` y `ENV_FILE` permiten apuntar al compose de desarrollo (`COMPOSE_FILE=docker-compose.yml ENV_FILE=.env`).

El dump contiene datos personales: `backup-postgres.sh` aplica `umask 077` y deja el archivo en `600`, así que solo el usuario que ejecuta el cron puede leerlo. Verifícalo con `stat -c '%a %n' backups/*.dump`.

La restauración corre como `car_agency` a propósito: si se hiciera con el superusuario, las tablas y secuencias quedarían a nombre de `postgres` y la API respondería `permission denied`. Al terminar se recarga Nginx porque `stop` + `start` puede cambiar la IP del contenedor `app` y Nginx resuelve el servicio al cargar su configuración.

Programación recomendada (diaria, fuera de la ventana de uso):

```cron
# /etc/cron.d/api-backup
15 3 * * * root cd /srv/api && BACKUP_RETENTION_DAYS=14 ./docker/scripts/backup-postgres.sh /var/backups/api >> /var/log/api-backup.log 2>&1
```

Alternativa con systemd:

```ini
# /etc/systemd/system/api-backup.service
[Unit]
Description=Backup PostgreSQL API Car Agency
[Service]
Type=oneshot
WorkingDirectory=/srv/api
Environment=BACKUP_RETENTION_DAYS=14
ExecStart=/srv/api/docker/scripts/backup-postgres.sh /var/backups/api
```

```ini
# /etc/systemd/system/api-backup.timer
[Unit]
Description=Backup diario PostgreSQL API Car Agency
[Timer]
OnCalendar=*-*-* 03:15:00
Persistent=true
[Install]
WantedBy=timers.target
```

```bash
systemctl enable --now api-backup.timer
```

**Restauración probada (desarrollo, 2026-11-19).** Se generó el dump, se insertó una fila marcadora en `countries`, se restauró y se comprobó que el marcador desaparecía, que `cars` (52), `users` (3) y `branches` (8) conservaban sus conteos, que `cars_id_seq` seguía avanzando y que `/up` respondía `200`. Guarda las copias fuera del host o en almacenamiento externo: el volumen `pg-data` no sustituye a un backup.

**PaaS (Render/Railway).** Con PostgreSQL gestionado (Render PostgreSQL, Neon, Supabase, Railway), activa las copias automáticas del proveedor y usa sus snapshots para restaurar; el contenedor monolítico no incluye PostgreSQL. Para una copia manual ejecuta `pg_dump -Fc` desde una shell del servicio con las variables `DB_*` (con `DB_SSLMODE=require` o `verify-full`) o desde tu equipo contra la base gestionada. Si la plataforma no ofrece backups, programa un job externo que haga el dump y suba el archivo a un almacenamiento duradero.

---

## ☁️ Despliegue en PaaS (Render / Railway)

Esta es la **vía alternativa** para plataformas gestionadas. La vía VPS de la sección anterior sigue funcionando igual: ninguno de sus archivos se toca. Aquí se publica una **imagen monolítica** en Docker Hub y la plataforma la ejecuta como un único contenedor, sin `docker-compose`, sin Nginx/PHP-FPM separados y sin PostgreSQL propio.

| Archivo | Propósito |
| :--- | :--- |
| `docker/render/Dockerfile.render` | Imagen única con FrankenPHP (Caddy + PHP 8.3 embebido), código, dependencias de producción y `EXPOSE 8080`. |
| `docker/render/Caddyfile` | Configuración de Caddy: escucha en `$PORT`, sirve `public/` y ejecuta PHP con `php_server`. |
| `docker/render/entrypoint.sh` | Prepara `storage/`, valida `APP_KEY`, ejecuta migraciones/seed opcionales y cachea Laravel al arrancar. |
| `.github/workflows/deploy-dockerhub.yml` | CI/CD: al hacer push a `main` construye esta imagen y la sube a Docker Hub. |

### Cómo funciona la imagen

- **Un solo proceso**: FrankenPHP arranca Caddy con PHP embebido; no hay `php-fpm` ni un Nginx aparte que coordinar.
- **Puerto dinámico**: el `Caddyfile` escucha en `:{$PORT:8080}`. Render y Railway inyectan `PORT`; en local el valor por defecto es `8080`.
- **Usuario sin privilegios**: el contenedor corre como `www-data`, así que `PORT` debe ser `>= 1024`. Si una plataforma obliga a usar el puerto `80`, arráncalo con `--user 0`.
- **Cachés en el arranque**: `entrypoint.sh` ejecuta `php artisan optimize` en cada inicio (las variables de entorno de la plataforma no existen en tiempo de build). Desactívalo con `APP_OPTIMIZE=false`.
- **Migraciones y seed**: no se ejecutan solas. Usa el Pre-Deploy Command de la plataforma (`php artisan migrate --force --seed`) o define `RUN_MIGRATIONS=true` y `RUN_SEED=true` para que los ejecute el entrypoint. El `--seed` solo crea roles/permisos y el administrador; no inserta datos demo.
- **Sin base de datos incluida**: la imagen solo contiene la aplicación; conecta a un PostgreSQL gestionado externo (Render PostgreSQL, Neon, Supabase, Railway, etc.).
- **Sesiones en archivo**: define `SESSION_DRIVER=file` (lo que ya hace `.env.production` en la VPS). El proyecto no incluye la migración de `sessions` y el valor por defecto de Laravel es `database`, así que con ese driver el login responde `500` al no existir la tabla.
- **TLS lo termina la plataforma**: el contenedor recibe HTTP por `$PORT`, así que configura `APP_URL=https://...`, `TRUSTED_PROXIES=<IP o CIDR del borde>`, `HSTS_ENABLED=true` y `SESSION_SECURE_COOKIE=true`. Sin un proxy explícito Laravel ignora `X-Forwarded-Proto`, no detecta HTTPS y no envía HSTS. **`TRUSTED_PROXIES=*` está prohibido en producción**: Laravel toma la última IP de `X-Forwarded-For` y un cliente podría falsificar la IP de auditoría y de los limitadores. La misma regla vale para la VPS; la diferencia es solo qué IP o rango se declara. En local con `docker run` sobre HTTP deja `TRUSTED_PROXIES` vacío y `HSTS_ENABLED=false`.
- **CORS obligatorio**: la imagen fija `APP_ENV=production`, así que hay que declarar `CORS_ALLOWED_ORIGINS` con orígenes explícitos (lista separada por comas; nunca `*`). El entrypoint lo valida antes de arrancar FrankenPHP y `CorsPolicy` lo vuelve a comprobar en cada petición HTTP; sin él `/up` respondería `500`. Los comandos artisan (migraciones, `optimize`) no requieren esta variable.

Construir y probar en local sin compose:

```bash
docker build -f docker/render/Dockerfile.render -t car-agency-laravel-api:paas .

docker run --rm -p 8080:8080 \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e CORS_ALLOWED_ORIGINS=http://localhost:3000 \
  -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=/var/www/html/storage/local.sqlite \
  -e SESSION_DRIVER=file \
  -e ADMIN_EMAIL=admin@agency.test \
  -e ADMIN_PASSWORD='S3gura-2026' \
  -e RUN_MIGRATIONS=true \
  -e RUN_SEED=true \
  car-agency-laravel-api:paas

curl -i http://localhost:8080/up   # 200
```

### 1. Configurar los secretos en GitHub

El workflow necesita dos secretos en **Settings → Secrets and variables → Actions → New repository secret**:

| Secreto | Valor |
| :--- | :--- |
| `DOCKER_USERNAME` | Tu usuario de Docker Hub. |
| `DOCKER_TOKEN` | Access token de Docker Hub con permiso **Read & Write** (no uses tu contraseña). |

El workflow usa esos secretos para publicar `docker.io/<DOCKER_USERNAME>/car-agency-laravel-api`.

### 2. Push a `main`: imagen automática en Docker Hub

Cada push a `main` (o una ejecución manual con `workflow_dispatch`) construye `docker/render/Dockerfile.render` para `linux/amd64` y publica dos etiquetas:

- `latest`: siempre apunta al último commit de `main`.
- `sha-<corto>`: versión inmutable del commit, útil para fijar despliegues y hacer rollback.

El workflow solo compila y publica; los tests siguen ejecutándose en local (`composer test`, `composer analyse`, `pint --test`, `composer audit`).

**Plataforma de la imagen (`linux/amd64`).** Solo se publica `linux/amd64` a propósito: Render y Railway ejecutan contenedores amd64, y añadir `linux/arm64` obligaría a emular la otra arquitectura en cada build (mucho más lento y sin uso real para estas plataformas). Si algún día despliegas en un host arm64 (por ejemplo un VPS Graviton o una Raspberry Pi), construye la imagen en ese host con `docker build -f docker/render/Dockerfile.render -t car-agency-laravel-api:paas .`; el Dockerfile es multi-arquitectura y no contiene binarios específicos de amd64. Para publicar ambas plataformas habría que cambiar `platforms` en `.github/workflows/deploy-dockerhub.yml` a `linux/amd64,linux/arm64` y aceptar el coste de emulación.

### 3. Crear el servicio en Render

1. **New → Web Service → Deploy an existing image from a registry**.
2. Imagen: `docker.io/<DOCKER_USERNAME>/car-agency-laravel-api:latest` (o una etiqueta `sha-*` para fijar versión).
3. **Health Check Path**: `/up`.
4. Añade las variables de entorno (no definas `PORT`: lo inyecta Render):

```dotenv
APP_ENV=production
APP_KEY=base64:...            # php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://tu-servicio.onrender.com
LOG_CHANNEL=stderr
CORS_ALLOWED_ORIGINS=https://app.example.com  # origen del frontend; nunca '*'

# Administrador inicial (lo usa ProductionAdminSeeder con `--seed`).
# Cambia la contraseña antes del primer despliegue: es la fuente de verdad.
ADMIN_EMAIL=admin@tudominio.com
ADMIN_PASSWORD=...            # 8+ con mayúsculas, minúsculas, números y símbolos
# Opcionales:
# ADMIN_NAME=Administrador
# ADMIN_LAST_NAME=General
# ADMIN_CODE=USR-0001
# ADMIN_DOCUMENT_NUMBER=V-00000001

DB_CONNECTION=pgsql
DB_HOST=...                   # host del PostgreSQL gestionado
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
DB_SSLMODE=require            # `verify-full` si el certificado es de confianza pública

CACHE_STORE=database
SESSION_DRIVER=file
QUEUE_CONNECTION=database
```

5. **Pre-Deploy Command**: `php artisan migrate --force --seed` (equivale a `RUN_MIGRATIONS=true` + `RUN_SEED=true`).
6. Despliega. Al terminar, `https://tu-servicio.onrender.com/up` responde `200` y `/api/cars` responde `401` sin token.

> **Administrador inicial.** En producción `DatabaseSeeder` no ejecuta los seeders demo (usan Faker, que no se instala con `composer install --no-dev`): solo siembra roles/permisos y crea el administrador con `ADMIN_EMAIL` y `ADMIN_PASSWORD`. Es idempotente (no duplica usuarios ni roles), pero `ADMIN_PASSWORD` es la fuente de verdad: cada ejecución de `--seed` vuelve a fijar esa contraseña. Si faltan las credenciales, conservan un `CHANGE_ME_*` o la clave no cumple la política fuerte, el seeder aborta antes de escribir nada para no dejar un administrador con credenciales conocidas.

En **Railway** el flujo es equivalente: *New → Deploy from Docker Image*, añade un PostgreSQL gestionado, define las mismas variables y el comando de release `php artisan migrate --force --seed`.

> La base de datos debe ser externa y accesible desde internet. Render PostgreSQL, Neon, Supabase y el plugin de PostgreSQL de Railway son opciones habituales: copia host, puerto, base, usuario y contraseña a las variables `DB_*` y ajusta `DB_SSLMODE` a lo que exija el proveedor (`require` o `verify-full`).

### 4. Actualizar la versión desplegada

1. Haz push a `main`; GitHub Actions publica `latest` y `sha-<corto>`.
2. En Render, pulsa **Manual Deploy → Deploy latest reference** (o usa un **Deploy Hook** en un paso extra del workflow). Fijar la etiqueta `sha-*` en el servicio permite rollback inmediato a una imagen anterior.

### Limitaciones del contenedor único

- **Almacenamiento efímero**: `storage/` vive dentro del contenedor, así que logs, sesiones de archivo y archivos locales se pierden en cada redespliegue. Para persistencia real usa un disco de la plataforma o un bucket S3.
- **Sesiones**: `SESSION_DRIVER=file` funciona con una sola instancia. Con varias réplicas necesitas un almacén compartido (Redis o la tabla `sessions`, que hoy no existe como migración).
- **Colas**: la imagen no arranca un worker. Si usas `QUEUE_CONNECTION=database`, crea un proceso en la plataforma con la misma imagen y comando `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` (con reinicio automático); es el equivalente PaaS del servicio `worker` del stack VPS.
- **Tareas programadas**: la imagen tampoco ejecuta `schedule:work`. Crea un Cron Job de la plataforma con la misma imagen y el comando `php artisan schedule:run` cada minuto, o un proceso aparte con `php artisan schedule:work`. Sin él no corren `audit:prune` ni `sanctum:prune-expired`, y las tablas de auditoría y tokens crecen sin límite.
- **Sin estado en la imagen**: la configuración llega por variables de entorno; nunca hornees `.env` ni credenciales en la imagen.

---

## 🧯 Solución de problemas

| Síntoma | Causa probable | Solución |
| :--- | :--- | :--- |
| `env file .env not found` | No existe `.env`. | `cp .env.example .env`. |
| `vendor/autoload.php` no existe | Faltan dependencias PHP. | `docker compose exec -T app composer install`. |
| Error 500 al abrir la API | `APP_KEY` vacío o dependencias sin instalar. | `key:generate` y `composer install`. |
| `SQLSTATE[08006]` | La app no encuentra PostgreSQL o aún no está listo. | `docker compose ps`, `docker compose logs postgres`; usa `DB_HOST=postgres` y `DB_PORT=5432`. |
| `SQLSTATE[28P01]` | Contraseña incorrecta para el rol. | Revisa `DB_PASSWORD` y que coincida con `POSTGRES_APP_PASSWORD`; si el volumen ya existía, el rol no se recrea solo. |
| `SQLSTATE[42P01]` (relation does not exist) | Faltan migraciones o se apunta a otra base. | `docker compose exec -T app php artisan migrate --seed` y comprueba `DB_DATABASE`. |
| `permission denied for schema public` | El rol no es dueño del schema o el volumen se creó con otro usuario. | Recrea el volumen (`docker compose down -v`) o ejecuta a mano el SQL de `docker/postgres/init/01-app-role.sh`. |
| `remaining connection slots are reserved` | Se agotaron las conexiones de PostgreSQL. | Reduce `pm.max_children` en `docker/php/www.conf` o sube `max_connections`; revisa procesos `worker`/`scheduler` duplicados. |
| Error de tabla `sessions` | `SESSION_DRIVER=database` sin migración de sesiones. | Usa `SESSION_DRIVER=file` en `.env`. |
| `port is already allocated` | Otro proceso usa `8000` o `5432`. | Cambia los puertos en `docker-compose.yml`. |
| `pull access denied for car-agency-laravel-api` | Se construyó Nginx antes que la imagen de `app`. | Ejecuta primero `docker compose -f docker-compose.prod.yml --env-file .env.production build app`. |
| `[entrypoint] ERROR: APP_KEY no está definida` | La imagen PaaS exige la clave por entorno. | Define `APP_KEY` (`php artisan key:generate --show`) en la plataforma. |
| `permission denied` al abrir el puerto en PaaS | `PORT < 1024` y el contenedor corre como `www-data`. | Usa un puerto `>= 1024` (Render inyecta `10000`) o arranca con `--user 0`. |
| `pull access denied` al desplegar en Render | Imagen privada o nombre mal escrito. | Haz público el repositorio de Docker Hub o configura credenciales de registro en la plataforma. |
| `config:cache` falla por valor no serializable | Un objeto vive en `config/*.php`. | Mueve la lógica a una clase y deja solo escalares o arrays en la configuración. |
| Cambios en `.env` no se aplican | Configuración cacheada. | `php artisan config:clear` (en producción, `optimize:clear`) y reinicia `app`. |
| `npm: not found` | La imagen PHP no incluye Node. | No uses `npm`; este stack no requiere Vite. |
| `429` al hacer login | Se superaron 5 intentos por minuto. | Espera un minuto o revisa el limiter `login`. |
| `403` en `/docs/api` | El entorno no es `local`/`testing` y `DOCS_ENABLED=false`. | Activa `DOCS_ENABLED=true` o consulta el documento en desarrollo. |
| `403` con «You must change your password before continuing» | `ENFORCE_PASSWORD_CHANGE=true` y la cuenta tiene `must_change_password=true`. | Cambia la clave con `PUT /api/auth/password` o desactiva el flag si el cliente aún no lo gestiona. |
| `413 Request Entity Too Large` | El cuerpo supera el límite de 8 MB compartido por Nginx (`client_max_body_size`) y PHP (`post_max_size`/`upload_max_filesize`). | Reduce el payload; si de verdad necesitas más, sube ambos límites a la vez. |
| `422` en `/api/reports/sales/pdf` | El rango supera `REPORTS_MAX_ROWS` (300 ventas). | Acota `from`/`to` o sube el límite junto con la memoria PHP. |
| `401` con un usuario válido | La cuenta está inactiva o el token expiró/fue revocado. | Actívala con `is_active=true`, vuelve a iniciar sesión o usa `/auth/refresh`. |
| `403` en `/api/audit-logs` o `/api/stats/*` | El rol no tiene `view_audit_logs` o `view_statistics`. | Usa un `admin` (auditoría) o un `admin`/`manager` (estadísticas). |
| `409` al borrar un catálogo | Está referenciado por otros registros. | Elimina o reasigna las referencias primero. |
| `409` al desactivar o borrar un usuario | Es el último administrador activo. | Crea o activa otro `admin` antes de la operación. |
| `422` en rankings | `limit` fuera de 1-100 o fechas enviadas por separado. | Usa `limit` válido y envía `start_date`/`end_date` juntos. |
| `422` al resetear una contraseña | `password` y `password_confirmation` no coinciden o no cumplen la fuerza mínima. | Corrige el payload; el reset marca `must_change_password`. |
| `405` en `/api/audit-logs` | La auditoría es append-only. | Solo se consulta con `GET`; no hay escritura pública. |
| La auditoría crece demasiado | No se ejecuta la retención programada. | Revisa el scheduler y ejecuta `audit:prune`; ajusta `AUDIT_RETENTION_DAYS`. |
| El CSV de auditoría muestra fórmulas | Un cliente abre el archivo sin respetar el escape. | El servidor prefija `'` a celdas que empiezan por `=`, `+`, `-`, `@`, tabulador, CR o LF. |

Para reiniciar desde cero:

```bash
docker compose down -v
cp .env.example .env
docker compose up -d --build
docker compose exec -T app composer install
docker compose exec -T app php artisan key:generate
docker compose exec -T app php artisan migrate --seed
```

---

## 📌 Notas del proyecto

- El plan de trabajo y las tareas pendientes viven en `ROADMAP.md`.
- Las reglas para agentes y el contexto técnico del repositorio están en `AGENTS.md`.
- `phpunit.xml` fuerza SQLite `:memory:` para los tests.
- PHPStan/Larastan corre en **nivel 7 sin baseline**: si añades código, `composer analyse` debe seguir en 0 errores.
- La visibilidad de datos es **multi-sucursal a propósito**: cualquier usuario con el permiso correspondiente ve todas las sucursales. No hay filtro automático por sucursal del usuario.
- Las credenciales de PostgreSQL (`postgres` / `secret` para el superusuario) son solo para desarrollo local y para tareas administrativas o backups; la aplicación usa el rol `car_agency` y el puerto solo escucha en `127.0.0.1`.
- El stack de desarrollo vive en `docker-compose.yml`; el de producción en `docker-compose.prod.yml` (Nginx con estáticos, PostgreSQL sin puerto público, sesiones en `app-storage` y caché/colas en PostgreSQL). Usa `.env.production.example` como plantilla del entorno productivo.
- Nginx y Caddy añaden `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer-when-downgrade` y `Permissions-Policy` a todas las respuestas, incluidos los errores (`always` en Nginx). Nginx oculta su versión con `server_tokens off` y solo `/docs/api` recibe una CSP que permite Stoplight Elements (JS/CSS de `unpkg.com` y scripts inline); el resto de la API no necesita CSP porque no sirve HTML.
- No hay llamadas HTTP salientes en tiempo de ejecución: la política de contraseñas, la auditoría y las estadísticas se resuelven en local.

---

_Desarrollado con Laravel 12, arquitectura por dominio y un stack Docker listo para desarrollo._
