# 🚗 Car Agency API

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-00000F?style=for-the-badge&logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)

API RESTful en **Laravel 12** para la gestión integral de una agencia de vehículos: inventario, sucursales, usuarios, roles, ventas transaccionales y reportes PDF.

El proyecto está preparado para ejecutarse con **Docker Compose**. No necesitas instalar PHP, Composer ni MySQL en el host.

---

## 🚀 Stack tecnológico

| Componente | Tecnología | Rol |
| :--- | :--- | :--- |
| Framework | Laravel 12 | Enrutamiento, ORM Eloquent, validación y lógica HTTP. |
| Lenguaje | PHP 8.3 | Enums nativos, tipado estricto y DTOs `readonly`. |
| Base de datos | MySQL 8.0 | Inventario, ubicaciones, usuarios y transacciones. |
| Servidor web | Nginx + PHP-FPM | Stack de desarrollo dentro de Docker. |
| Autenticación | Laravel Sanctum | Tokens bearer opacos con expiración. |
| Reportes | Dompdf | Generación de reportes PDF de ventas e inventario. |
| Tests | Pest | Suite de pruebas de Feature y Unit. |
| Formato | Laravel Pint | Estilo de código PHP. |

---

## ✨ Características principales

- **Autenticación con Sanctum:** `POST /api/auth/login` es la única ruta pública. El resto exige `Authorization: Bearer <token>`.
- **RBAC de 15 permisos:** la autorización se resuelve por permisos asociados a roles, no por el nombre del rol.
- **Rate limiting en login:** 5 intentos por minuto por email + IP. El sexto devuelve `429`.
- **Revocación de tokens:** cambiar contraseña, desactivar o eliminar un usuario invalida sus sesiones abiertas.
- **Arquitectura por dominio:** `Cars`, `Locations`, `Sales`, `Users` y `Reports`.
- **Servicios y DTOs:** controladores delgados, reglas de negocio en `app/Services` y datos inmutables en `app/DTOs`.
- **Ventas transaccionales:** cálculo de impuestos, descuentos y tipos de cambio con redondeo a 2 decimales.
- **Soft deletes:** en `User` y `Car`. Las ventas no se borran: se cancelan con `SaleStatus::CANCELLED`.
- **Reportes PDF:** ventas e inventario, con filtros por fecha y sucursal.
- **Documentación OpenAPI 3.1.2:** `/docs/api` y `/docs/api.json`, con grupos, resúmenes y errores por dominio; importable en Postman.
- **Datos demo en español:** 8 sucursales, 52 coches y 3 usuarios con roles `admin`, `manager` y `vendedor`.

---

## 🐳 Inicio rápido con Docker

### Requisitos

- Docker Engine o Docker Desktop con **Compose v2**.
- Puertos **8000** y **3306** libres en el host.
- No hace falta PHP, Composer, MySQL ni Node.js.

### 1. Clonar el repositorio y crear el `.env`

```bash
git clone <URL_DEL_REPOSITORIO> API_Laravel_Car_Agency
cd API_Laravel_Car_Agency

cp .env.example .env
```

El archivo `.env.example` ya viene alineado con Docker Compose:

- `DB_HOST=mysql`
- `DB_DATABASE=api_laravel_car_agency`
- `DB_USERNAME=root`
- `DB_PASSWORD=secret`
- `SESSION_DRIVER=file`
- `APP_URL=http://localhost:8000`

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
| `mysql` | `3306` | MySQL 8.0 con la base `api_laravel_car_agency`. |

MySQL tiene un healthcheck; el contenedor `app` espera a que esté listo antes de arrancar.

### 3. Instalar dependencias y generar la clave

```bash
docker compose exec -T app composer install
docker compose exec -T app php artisan key:generate
```

- Ejecuta `composer install` en el primer arranque o cuando cambie `composer.lock`.
- Ejecuta `key:generate` solo la primera vez, o si `APP_KEY` está vacío. Volver a generarla invalida datos cifrados.
- No uses `composer setup` ni `npm` dentro de `app`: la imagen PHP no incluye Node y el frontend Vite no forma parte de este stack.

### 4. Crear o preparar la base de datos

En el primer arranque, MySQL crea automáticamente la base definida en `docker-compose.yml` (`MYSQL_DATABASE=api_laravel_car_agency`). Normalmente **no necesitas crearla a mano**.

Si quieres crearla o verificar que existe:

```bash
# Crear la base si no existe
docker compose exec -T mysql mysql -uroot -psecret -e \
  "CREATE DATABASE IF NOT EXISTS api_laravel_car_agency CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Verificar que existe
docker compose exec -T mysql mysql -uroot -psecret -e \
  "SHOW DATABASES LIKE 'api_laravel_car_agency';"
```

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
docker compose exec -T mysql mysql -uroot -psecret api_laravel_car_agency -e \
  "SELECT COUNT(*) AS branches FROM branches; SELECT COUNT(*) AS cars FROM cars; SELECT COUNT(*) AS users FROM users;"
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

### Credenciales Iniciales por Defecto

Cuando `ADMIN_EMAIL`, `ADMIN_CODE` y `ADMIN_PASSWORD` no se personalizan, el
seeder de producción crea este administrador:

| Campo | Valor por defecto |
| :--- | :--- |
| Email | `admin@agency.com` |
| Código | `USR-0001` |
| Contraseña | `secret1234` |

> **Cámbiala en el primer arranque.** `ProductionAdminSeeder` vuelve a aplicar
> `ADMIN_PASSWORD` cada vez que se ejecuta `php artisan migrate --seed` o
> `php artisan db:seed`, así que la variable de entorno es la fuente de verdad.
> Define al menos `ADMIN_EMAIL` y `ADMIN_PASSWORD` antes de desplegar.

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

Los tokens expiran a los **120 minutos**. `POST /api/auth/logout` invalida el token actual y `POST /api/auth/refresh` emite uno nuevo.

### Autoservicio de identidad

| Método y ruta | Qué hace |
| :--- | :--- |
| `GET /api/auth/me` | Devuelve el perfil, roles, sucursales y la lista plana de permisos efectivos. |
| `PUT /api/auth/password` | Cambia la contraseña propia. Exige `current_password`, `password` y `password_confirmation`; revoca los demás tokens y conserva el de la petición. |
| `POST /api/users/{user}/reset-password` | Un administrador (`manage_users`) asigna una clave temporal, marca `must_change_password=true` y revoca todos los tokens del usuario. |

Un usuario desactivado recibe `401` en cualquier ruta autenticada, aunque su token aún no haya expirado. Para dejar el sistema sin administración, la API responde `409` al borrar, desactivar o quitar el rol `admin` al último administrador activo, y `422` si un rol queda sin permisos o se intenta modificar el `code` de un usuario.

| Código | Cuándo |
| :--- | :--- |
| `200` | Operación correcta. |
| `201` | Recurso creado. |
| `204` | Recurso eliminado sin cuerpo. |
| `401` | Sin token, token inválido/expirado, credenciales incorrectas o usuario inactivo. |
| `403` | Autenticado, pero sin el permiso requerido. |
| `404` | Recurso inexistente o con soft delete. |
| `409` | Conflicto de estado o integridad: venta ya cancelada, rol con usuarios, borrado con FK o moneda base. |
| `422` | Error de validación. El detalle viene en `errors`. |
| `429` | Demasiadas peticiones: se superó el límite del *rate limiter*. Espera un minuto. |

### Rate limits

Los límites se definen en `config/rate_limits.php` y se pueden ajustar con variables de entorno:

| Limiter | Rutas | Límite | Se cuenta por |
| :--- | :--- | :--- | :--- |
| `login` | `POST /api/auth/login` | 5/min (`RATE_LIMIT_LOGIN`) | email + IP |
| `api` | Resto de rutas autenticadas | 120/min (`RATE_LIMIT_API`) | usuario (o IP sin token) |
| `reports` | `/api/reports/*` | 30/min (`RATE_LIMIT_REPORTS`) | usuario (o IP sin token) |

El reporte PDF es la ruta más cara: además del límite, corta en `REPORTS_MAX_ROWS` (500 por defecto) y responde `422` si el rango de fechas lo supera.

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
| `POST` | `/api/auth/logout` | Requiere token. |
| `POST` | `/api/auth/refresh` | Requiere token. |

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
| Auditoría | `/api/audit-logs` | `view_audit_logs` | Solo lectura |
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

### Paginación

Los endpoints de listado aceptan `page` (desde 1) y `per_page` entre **1 y 100**. El tamaño por defecto es **15**:

```bash
curl -s "http://localhost:8000/api/cars?page=2&per_page=25" \
  -H "Authorization: Bearer $TOKEN"
```

La respuesta incluye `data`, `links` (`first`, `last`, `prev`, `next`) y `meta` con `current_page`, `last_page`, `per_page` y `total`. Los enlaces conservan los parámetros de la petición (por ejemplo `per_page`) y cada listado se ordena por `id`, así que las páginas son estables. `GET /api/car-statuses` y `GET /api/permissions` devuelven la colección completa, sin paginar.

---

## 📘 Documentación OpenAPI y Postman

El contrato se genera con **Scramble** en **OpenAPI 3.1.2** a partir de las rutas, los `FormRequest` y los `Resource`. `app/OpenApi/ApiDocumentation.php` añade encima los grupos en español, los resúmenes de cada operación, la paginación, los filtros de reportes y los errores `403`, `409` y `429`.

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

La colección importada contiene las 87 peticiones de la API, con `Authorization: Bearer {{bearerToken}}` ya configurado a nivel de colección.

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

Los tests usan Pest con `RefreshDatabase` y **SQLite en memoria**, por lo que no tocan la base MySQL de desarrollo.

```bash
# Suite completa
docker compose exec -T app ./vendor/bin/pest

# Un archivo o directorio
docker compose exec -T app ./vendor/bin/pest tests/Feature/Cars

# Filtrar por nombre
docker compose exec -T app ./vendor/bin/pest --filter='login'

# Comando del proyecto (limpia configuración y ejecuta la suite)
docker compose exec -T app composer test

# Formatear con Pint
docker compose exec -T app ./vendor/bin/pint

# Comprobar formato sin modificar archivos
docker compose exec -T app ./vendor/bin/pint --test

# Análisis estático (PHPStan/Larastan nivel 5, incluye `--memory-limit=1G`)
docker compose exec -T app composer analyse

# Vulnerabilidades conocidas en dependencias PHP
docker compose exec -T app composer audit
```

Estado verificado de la suite (2026-10-28): **169 tests / 1376 aserciones**, **PHPStan 0 errores** y **Pint limpio (269 archivos)**. `phpunit.xml` fuerza SQLite `:memory:`, así que los tests no tocan MySQL.

### Reglas que fijan los tests

- El número de consultas de cada listado no crece con las filas (`tests/Feature/Performance/QueryCountTest.php`).
- Ningún N+1 pasa desapercibido: `Model::preventLazyLoading()` está activo fuera de producción.
- El 403/401/429 de cada ruta y el contrato de la documentación OpenAPI están cubiertos en `tests/Feature/Docs`.
- Los importes se comparan redondeados a 2 decimales.

---

## 🛠️ Comandos útiles

```bash
# Estado de los contenedores
docker compose ps

# Logs
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f mysql

# Shell dentro del contenedor PHP
docker compose exec -T app sh

# Rutas registradas
docker compose exec -T app php artisan route:list
docker compose exec -T app php artisan route:list --path=api

# Limpiar cachés de configuración, rutas y vistas
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:clear

# Cliente MySQL
docker compose exec -T mysql mysql -uroot -psecret api_laravel_car_agency

# Apagar contenedores (conserva la base de datos)
docker compose down

# Apagar y borrar volúmenes: elimina MySQL y también vendor/
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

mysql:
  ports:
    - "3307:3306"
```

Luego actualiza `APP_URL` en `.env` si cambias el puerto de Nginx.

---

## 🚀 Despliegue en Producción

El repositorio incluye un stack de producción separado que **no modifica** el entorno de desarrollo (`docker-compose.yml` y `docker/php/Dockerfile` siguen igual):

| Archivo | Propósito |
| :--- | :--- |
| `docker-compose.prod.yml` | PHP-FPM (`target: prod`), Nginx en el puerto 80 y MySQL 8.0 sin puerto público. Caché y colas en MySQL; sesiones en el volumen `app-storage`. |
| `docker/nginx/Dockerfile.prod` | Build multi-stage: parte de `car-agency-laravel-api:prod`, copia `public/` y sirve los estáticos con Nginx. |
| `.env.production.example` | Plantilla versionable, sin credenciales reales (marcadores `CHANGE_ME_*`). |

> ⚠️ **El orden importa.** La imagen de Nginx se construye con `COPY --from=car-agency-laravel-api:prod`, así que la imagen de `app` debe existir **antes** de levantar el stack completo. Por eso el paso 2 se ejecuta por separado.

### Paso 1: Preparar credenciales

```bash
cp .env.production.example .env.production
```

Edita `.env.production` y sustituye todos los `CHANGE_ME_*`, el `APP_URL` real y `CORS_ALLOWED_ORIGINS` (nunca uses `*` en producción). Añade también `ADMIN_EMAIL` y `ADMIN_PASSWORD` para el administrador que crea el seed. Mantén sincronizadas las variables `DB_*` con `MYSQL_*`, porque la imagen de MySQL crea el usuario con `MYSQL_USER`/`MYSQL_PASSWORD` y la app se conecta con `DB_USERNAME`/`DB_PASSWORD`. Deja `APP_KEY` vacía por ahora: se genera en el paso 2, cuando la imagen ya exista.

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

Compose construye la imagen de Nginx a partir de la del paso 2, arranca `mysql` y espera su healthcheck (`mysqladmin ping`), levanta `app` y espera su healthcheck (`fsockopen` al puerto 9000) y solo entonces inicia `nginx`, que publica `80:80`. MySQL queda aislado en la red interna `car-agency-prod`.

### Paso 4: Migrar y optimizar

```bash
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force --seed
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
```

- `migrate --force --seed` crea el esquema y también las tablas `cache`, `jobs` y `failed_jobs`; los drivers `database` de caché y colas dependen de ellas, así que este paso va antes de recibir tráfico. El `--seed` en producción solo siembra roles/permisos y el administrador definido por `ADMIN_EMAIL`/`ADMIN_PASSWORD` (añade esas variables a `.env.production`); no inserta datos demo.
- `optimize` cachea configuración, eventos, rutas y vistas compiladas (`config:cache`, `event:cache`, `route:cache`, `view:cache`) para que cada petición no reconstruya el bootstrap.

> Las cachés de `optimize` se escriben en `bootstrap/cache` dentro del contenedor, no en el volumen `app-storage`: se pierden al recrear `app`, así que vuelve a ejecutar `optimize` después de cada despliegue. Si cambias `.env.production`, ejecuta `php artisan optimize:clear` y reinicia `app`; con la configuración cacheada, los cambios de entorno no se leen.

### Verificar y detener

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production ps
curl -i http://localhost/up
curl -i http://localhost/api/cars   # 401: la API exige token Bearer

# Detener conservando los volúmenes
docker compose -f docker-compose.prod.yml --env-file .env.production down
```

> Solo Nginx publica un puerto (`80:80`); MySQL queda aislado en la red interna. El volumen `app-storage` conserva `storage/` (sesiones, logs y cachés de framework) entre despliegues. `docker compose ... down -v` borra la base de datos y el volumen de storage.

---

## ☁️ Despliegue en PaaS (Render / Railway)

Esta es la **vía alternativa** para plataformas gestionadas. La vía VPS de la sección anterior sigue funcionando igual: ninguno de sus archivos se toca. Aquí se publica una **imagen monolítica** en Docker Hub y la plataforma la ejecuta como un único contenedor, sin `docker-compose`, sin Nginx/PHP-FPM separados y sin MySQL propio.

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
- **Sin base de datos incluida**: la imagen solo contiene la aplicación; conecta a una base gestionada externa (Aiven, el plugin MySQL de Railway, etc.).

Construir y probar en local sin compose:

```bash
docker build -f docker/render/Dockerfile.render -t car-agency-laravel-api:paas .

docker run --rm -p 8080:8080 \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=/var/www/html/storage/local.sqlite \
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

El workflow solo compila y publica; los tests siguen ejecutándose en local (`composer test`, `composer analyse`, `pint --test`).

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

# Administrador inicial (lo usa ProductionAdminSeeder con `--seed`).
# Cambia la contraseña antes del primer despliegue: es la fuente de verdad.
ADMIN_EMAIL=admin@tudominio.com
ADMIN_PASSWORD=...            # mínimo 8 caracteres
# Opcionales:
# ADMIN_NAME=Administrador
# ADMIN_LAST_NAME=General
# ADMIN_CODE=USR-0001
# ADMIN_DOCUMENT_NUMBER=V-00000001

DB_CONNECTION=mysql
DB_HOST=...                   # host de Aiven u otro MySQL gestionado
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
# Si el proveedor exige TLS (Aiven), apunta a su CA:
# MYSQL_ATTR_SSL_CA=/ruta/al/ca.pem

CACHE_STORE=database
SESSION_DRIVER=file
QUEUE_CONNECTION=database
```

5. **Pre-Deploy Command**: `php artisan migrate --force --seed` (equivale a `RUN_MIGRATIONS=true` + `RUN_SEED=true`).
6. Despliega. Al terminar, `https://tu-servicio.onrender.com/up` responde `200` y `/api/cars` responde `401` sin token.

> **Administrador inicial.** En producción `DatabaseSeeder` no ejecuta los seeders demo (usan Faker, que no se instala con `composer install --no-dev`): solo siembra roles/permisos y crea el administrador con `ADMIN_EMAIL` y `ADMIN_PASSWORD`. Es idempotente (no duplica usuarios ni roles), pero `ADMIN_PASSWORD` es la fuente de verdad: cada ejecución de `--seed` vuelve a fijar esa contraseña. Si no defines `ADMIN_PASSWORD`, se usa `secret1234` y el seeder emite un aviso en los logs; cámbiala de inmediato.

En **Railway** el flujo es equivalente: *New → Deploy from Docker Image*, añade un MySQL gestionado, define las mismas variables y el comando de release `php artisan migrate --force --seed`.

> La base de datos debe ser externa y accesible desde internet. Aiven (MySQL) y el plugin de MySQL de Railway son opciones habituales: copia host, puerto, base, usuario y contraseña a las variables `DB_*`.

### 4. Actualizar la versión desplegada

1. Haz push a `main`; GitHub Actions publica `latest` y `sha-<corto>`.
2. En Render, pulsa **Manual Deploy → Deploy latest reference** (o usa un **Deploy Hook** en un paso extra del workflow). Fijar la etiqueta `sha-*` en el servicio permite rollback inmediato a una imagen anterior.

### Limitaciones del contenedor único

- **Almacenamiento efímero**: `storage/` vive dentro del contenedor, así que logs, sesiones de archivo y archivos locales se pierden en cada redespliegue. Para persistencia real usa un disco de la plataforma o un bucket S3.
- **Sesiones**: `SESSION_DRIVER=file` funciona con una sola instancia. Con varias réplicas necesitas un almacén compartido (Redis o la tabla `sessions`, que hoy no existe como migración).
- **Colas**: la imagen no arranca un worker. Si usas `QUEUE_CONNECTION=database`, crea un worker en la plataforma con la misma imagen y comando `php artisan queue:work --tries=1`.
- **Sin estado en la imagen**: la configuración llega por variables de entorno; nunca hornees `.env` ni credenciales en la imagen.

---

## 🧯 Solución de problemas

| Síntoma | Causa probable | Solución |
| :--- | :--- | :--- |
| `env file .env not found` | No existe `.env`. | `cp .env.example .env`. |
| `vendor/autoload.php` no existe | Faltan dependencias PHP. | `docker compose exec -T app composer install`. |
| Error 500 al abrir la API | `APP_KEY` vacío o dependencias sin instalar. | `key:generate` y `composer install`. |
| `SQLSTATE[HY000] [2002]` | La app no encuentra MySQL o aún no está listo. | `docker compose ps`, `docker compose logs mysql`; usa `DB_HOST=mysql`. |
| `Access denied for user 'root'` | Credenciales distintas a las del compose. | Revisa `DB_PASSWORD=secret` y `docker-compose.yml`. |
| Error de tabla `sessions` | `SESSION_DRIVER=database` sin migración de sesiones. | Usa `SESSION_DRIVER=file` en `.env`. |
| `port is already allocated` | Otro proceso usa `8000` o `3306`. | Cambia los puertos en `docker-compose.yml`. |
| `pull access denied for car-agency-laravel-api` | Se construyó Nginx antes que la imagen de `app`. | Ejecuta primero `docker compose -f docker-compose.prod.yml --env-file .env.production build app`. |
| `[entrypoint] ERROR: APP_KEY no está definida` | La imagen PaaS exige la clave por entorno. | Define `APP_KEY` (`php artisan key:generate --show`) en la plataforma. |
| `permission denied` al abrir el puerto en PaaS | `PORT < 1024` y el contenedor corre como `www-data`. | Usa un puerto `>= 1024` (Render inyecta `10000`) o arranca con `--user 0`. |
| `pull access denied` al desplegar en Render | Imagen privada o nombre mal escrito. | Haz público el repositorio de Docker Hub o configura credenciales de registro en la plataforma. |
| `config:cache` falla por valor no serializable | Un objeto vive en `config/*.php`. | Mueve la lógica a una clase y deja solo escalares o arrays en la configuración. |
| Cambios en `.env` no se aplican | Configuración cacheada. | `php artisan config:clear` (en producción, `optimize:clear`) y reinicia `app`. |
| `npm: not found` | La imagen PHP no incluye Node. | No uses `npm`; este stack no requiere Vite. |
| `429` al hacer login | Se superaron 5 intentos por minuto. | Espera un minuto o revisa el limiter `login`. |
| `403` en `/docs/api` | El entorno no es `local`/`testing` y `DOCS_ENABLED=false`. | Activa `DOCS_ENABLED=true` o consulta el documento en desarrollo. |
| `422` en `/api/reports/sales/pdf` | El rango supera `REPORTS_MAX_ROWS` (500 ventas). | Acota `from`/`to` o sube el límite junto con la memoria PHP. |
| `401` con un usuario válido | La cuenta está inactiva. | Actívala con `is_active=true` o desde `/api/users`. |
| `409` al borrar un catálogo | Está referenciado por otros registros. | Elimina o reasigna las referencias primero. |

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
- PHPStan/Larastan corre en **nivel 5 sin baseline**: si añades código, `composer analyse` debe seguir en 0 errores.
- La visibilidad de datos es **multi-sucursal a propósito**: cualquier usuario con el permiso correspondiente ve todas las sucursales. No hay filtro automático por sucursal del usuario.
- Las credenciales de MySQL (`root` / `secret`) son solo para desarrollo local.
- El stack de desarrollo vive en `docker-compose.yml`; el de producción en `docker-compose.prod.yml` (Nginx con estáticos, MySQL sin puerto público, sesiones en `app-storage` y caché/colas en MySQL). Usa `.env.production.example` como plantilla del entorno productivo.

---

_Desarrollado con Laravel 12, arquitectura por dominio y un stack Docker listo para desarrollo._
