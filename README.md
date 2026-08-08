# 🚗 Car Agency API

![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-00000F?style=for-the-badge&logo=mysql&logoColor=white)

Este proyecto es una **API RESTful** robusta desarrollada en **Laravel** para la gestión integral de una agencia de venta de vehículos. Está diseñado con un enfoque en **Arquitectura Limpia**, **Diseño Orientado a Dominios (DDD)** y un motor financiero transaccional preciso.

---

## 🚀 Stack Tecnológico Principal

| Componente        | Tecnología  | Rol Principal                                                         |
| :---------------- | :---------- | :-------------------------------------------------------------------- |
| **Framework**     | **Laravel** | Estructura base, enrutamiento, ORM (Eloquent) y lógica del backend.   |
| **Lenguaje**      | **PHP 8**   | Uso de características modernas como Enums nativos y tipado estricto. |
| **Base de Datos** | **MySQL**   | Almacenamiento relacional de inventario, sucursales y transacciones.  |
| **Seguridad**     | **Sanctum** | Autenticación mediante tokens y protección de endpoints de la API.    |

---

## 💡 Arquitectura y Características Implementadas

### 1. Seguridad y Control de Acceso (RBAC) 🛡️

- **Autenticación con Laravel Sanctum:** El sistema emite tokens de acceso seguros (similares a JWT) tras el inicio de sesión. Estos tokens autentican cada petición HTTP de forma _stateless_, garantizando que solo los usuarios verificados interactúen con la API.
- **Gestión de Roles y Permisos (Tablas Pivote):** Implementación de un sistema de control de acceso relacional avanzado. Utiliza **tablas pivote** (ej. `role_user`, `permission_role`) para establecer relaciones de muchos a muchos. Esto permite que los usuarios tengan roles específicos (Admin, Vendedor) y que la API restrinja dinámicamente el acceso a ciertos endpoints según los permisos exactos asociados a ese rol.

### 2. Arquitectura Limpia y Patrones de Diseño 🏛️

- **Separación de Responsabilidades (SoC):** Implementación del patrón _Service-Repository_. Los controladores se mantienen delgados ("Skinny Controllers") y toda la lógica de negocio compleja se aísla en clases especializadas `Service`.
- **Enfoque por Dominios (DDD):** El código está estructurado por contextos de negocio independientes (`Cars`, `Sales`, `Locations`, `Users`) en lugar de la agrupación técnica genérica.
- **Data Transfer Objects (DTOs):** Se utilizan objetos de transferencia de datos inmutables para pasar información desde los `Controllers` hacia los `Services`, desacoplando la lógica interna del marco HTTP.

### 3. Motor Financiero y de Ventas 💰

- **Cálculos Transaccionales Precisos:** Lógica matemática blindada contra la pérdida de centavos (`Rounding Errors`) durante las operaciones de venta y manejo de tipos de cambio de moneda.
- **Seguridad de Tipos Fuerte:** Uso de _Enums nativos de PHP 8_ (ej. `CarStatus`, `SaleStatus`) casteados directamente en los Modelos de Eloquent para garantizar la integridad absoluta de la base de datos.

---

## ⚙️ Configuración y Ejecución Local

Para levantar el entorno local, asegúrate de tener PHP 8.x instalado, duplica el archivo `.env.example`, renómbralo a `.env` y configura tus credenciales de base de datos.

```env
APP_NAME="Car Agency"
APP_ENV=local
APP_KEY=base64:D2bWY6UZ1BBDphDcVDG7A4QjLHgq9Dhm/S00pPPqZEw=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Base de Datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=car_agency
DB_USERNAME=root
DB_PASSWORD=

# Logs y Caché Local
LOG_CHANNEL=stack
LOG_LEVEL=debug
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=file
```

```

---

## 🌐 Endpoints Principales de la API

> **⚠️ Nota de enrutamiento:** Las rutas marcadas como `apiResource` generan automáticamente los endpoints estándar REST: `GET` (Listar/Mostrar), `POST` (Crear), `PUT/PATCH` (Actualizar) y `DELETE` (Eliminar).

### 🔐 Autenticación y Usuarios (Auth & Users)

| Método        | Endpoint            | Descripción                                      |
| ------------- | ------------------- | ------------------------------------------------ |
| `POST`        | `/api/auth/login`   | Iniciar sesión y obtener token de acceso.        |
| `POST`        | `/api/auth/logout`  | Cerrar sesión e invalidar token (Requiere Auth). |
| `POST`        | `/api/auth/refresh` | Refrescar token de sesión (Requiere Auth).       |
| `apiResource` | `/api/users`        | Gestión de empleados y usuarios del sistema.     |
| `apiResource` | `/api/roles`        | Configuración de roles de acceso.                |
| `GET`         | `/api/permissions`  | Listado de permisos disponibles en el sistema.   |

### 📍 Ubicaciones (Locations)

| Método        | Endpoint         | Descripción                                     |
| ------------- | ---------------- | ----------------------------------------------- |
| `apiResource` | `/api/countries` | Gestión del catálogo de países.                 |
| `apiResource` | `/api/states`    | Gestión de estados/provincias.                  |
| `apiResource` | `/api/cities`    | Gestión de ciudades.                            |
| `apiResource` | `/api/branches`  | Gestión de sucursales operativas de la agencia. |

### 🚗 Inventario y Vehículos (Cars)

| Método        | Endpoint            | Descripción                                        |
| ------------- | ------------------- | -------------------------------------------------- |
| `GET`         | `/api/car-statuses` | Lista de estatus disponibles para los vehículos.   |
| `apiResource` | `/api/cars`         | **Gestión principal del inventario de vehículos.** |
| `apiResource` | `/api/brands`       | Catálogo de marcas de fabricantes.                 |
| `apiResource` | `/api/car-models`   | Modelos específicos asociados a las marcas.        |
| `apiResource` | `/api/car-types`    | Gestión de carrocerías (Sedan, SUV, Hatchback).    |
| `apiResource` | `/api/categories`   | Clasificación por categorías de gama.              |
| `apiResource` | `/api/colors`       | Catálogo de colores disponibles.                   |

### 💰 Ventas y Finanzas (Sales)

| Método        | Endpoint                   | Descripción                                                 |
| ------------- | -------------------------- | ----------------------------------------------------------- |
| `GET`         | `/api/sales`               | Historial general de ventas registradas.                    |
| `POST`        | `/api/sales`               | **Procesar una nueva venta** (Aplica cálculos e impuestos). |
| `GET`         | `/api/sales/{sale}`        | Obtener el recibo y desglose detallado de una venta.        |
| `PATCH`       | `/api/sales/{sale}/cancel` | Cancelar venta, revertir estatus y liberar el vehículo.     |
| `apiResource` | `/api/currencies`          | Gestión de monedas base y tasas de cambio.                  |
| `apiResource` | `/api/payment-methods`     | Catálogo de métodos de pago.                                |
| `apiResource` | `/api/taxes`               | Gestión de impuestos configurables (IVA, Lujo, etc).        |

---

_Desarrollado con arquitectura moderna en Laravel._
```
