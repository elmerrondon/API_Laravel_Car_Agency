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
- **RBAC de 13 permisos:** la autorización se resuelve por permisos asociados a roles, no por el nombre del rol.
- **Rate limiting en login:** 5 intentos por minuto por email + IP. El sexto devuelve `429`.
- **Revocación de tokens:** cambiar contraseña, desactivar o eliminar un usuario invalida sus sesiones abiertas.
- **Arquitectura por dominio:** `Cars`, `Locations`, `Sales`, `Users` y `Reports`.
- **Servicios y DTOs:** controladores delgados, reglas de negocio en `app/Services` y datos inmutables en `app/DTOs`.
- **Ventas transaccionales:** cálculo de impuestos, descuentos y tipos de cambio con redondeo a 2 decimales.
- **Soft deletes:** en `User` y `Car`. Las ventas no se borran: se cancelan con `SaleStatus::CANCELLED`.
- **Reportes PDF:** ventas e inventario, con filtros por fecha y sucursal.
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
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

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
| `admin@caragency.test` | `admin` | 13 |
| `manager@caragency.test` | `manager` | 10 |
| `vendedor@caragency.test` | `vendedor` | 5 |

> Estas credenciales son solo para desarrollo. Cámbialas antes de desplegar.

---

## 🔐 Autenticación y códigos de estado

El login devuelve un token bearer. En cada petición protegida envía:

```http
Authorization: Bearer <token>
```

Los tokens expiran a los **120 minutos**. `POST /api/auth/logout` invalida el token actual y `POST /api/auth/refresh` emite uno nuevo.

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
| `429` | Demasiados intentos de login. Espera un minuto. |

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

Los endpoints de listado aceptan `per_page` entre **1 y 100**. El valor por defecto es **15**:

```bash
curl -s "http://localhost:8000/api/cars?per_page=25" \
  -H "Authorization: Bearer $TOKEN"
```

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
```

Estado verificado de la suite: **63 tests / 260 aserciones**.

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
| Cambios en `.env` no se aplican | Configuración cacheada. | `php artisan config:clear` y reinicia `app`. |
| `npm: not found` | La imagen PHP no incluye Node. | No uses `npm`; este stack no requiere Vite. |
| `429` al hacer login | Se superaron 5 intentos por minuto. | Espera un minuto o revisa el limiter `login`. |
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
- Las credenciales de MySQL (`root` / `secret`) son solo para desarrollo local.
- El stack Docker es de desarrollo; el `Dockerfile` incluye un target `prod`, pero no hay un `docker-compose.prod.yml` en el repositorio.

---

_Desarrollado con Laravel 12, arquitectura por dominio y un stack Docker listo para desarrollo._
