````markdown
# 🚗 Car Agency API

API RESTful desarrollada en Laravel para la gestión integral de una agencia de venta de vehículos. Este sistema centraliza el control de inventario, gestión de sucursales, control de acceso de usuarios y un motor financiero robusto para el procesamiento de ventas.

## 📋 Índice

1. [✨ Características Principales](#-características-principales)
2. [🏛️ Arquitectura y Patrones de Diseño](#️-arquitectura-y-patrones-de-diseño)
3. [⚙️ Configuración del Entorno](#️-configuración-del-entorno)
4. [🚀 Endpoints de la API](#-endpoints-de-la-api)

---

## ✨ Características Principales

- **Motor Financiero Preciso:** Cálculos transaccionales con protección contra pérdida de centavos (`Rounding Errors`), manejo de múltiples impuestos dinámicos, tasas de cambio y descuentos.
- **Autenticación:** Sistema de acceso protegido mediante tokens con _Laravel Sanctum_.
- **Integridad de Datos:** Restricciones a nivel de base de datos y validaciones estrictas para evitar inconsistencias en el inventario durante el procesamiento de ventas.

---

## 🏛️ Arquitectura y Patrones de Diseño

Este proyecto se alejó del estándar tradicional MVC básico para implementar principios de **Arquitectura Limpia (Clean Architecture)** y **Diseño Orientado a Dominios (DDD)**, garantizando que el sistema sea escalable y fácil de mantener a nivel empresarial:

- **Separación de Responsabilidades (SoC):** Los controladores se mantienen delgados ("Skinny Controllers"). Su única responsabilidad es recibir peticiones HTTP y devolver respuestas (JSON). Toda la lógica de negocio, cálculos y reglas fiscales están aisladas en la capa de **Servicios** (`Services`).
- **Enfoque por Dominios (DDD):** El código está agrupado por contextos de negocio (`Cars`, `Sales`, `Locations`, `Users`) en lugar de la agrupación técnica genérica. Esto encapsula la lógica de cada módulo de la agencia.
- **Data Transfer Objects (DTOs):** Se utilizan objetos de transferencia de datos inmutables para pasar información desde los `Controllers` hacia los `Services`. Esto desacopla la lógica interna del marco de trabajo HTTP (Requests).
- **Seguridad de Tipos Fuerte:** Uso de _Enums nativos de PHP 8_ (ej. `CarStatus`, `SaleStatus`) casteados directamente en los Modelos de Eloquent. Esto crea un muro de contención que asegura que la base de datos solo reciba estados válidos y tipados.

---

## ⚙️ Configuración del Entorno

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
````

---

## 🚀 Endpoints de la API

Todas las peticiones deben incluir el prefijo `/api/`. Las rutas marcadas como `apiResource` generan automáticamente los verbos HTTP estándar (`GET`, `POST`, `PUT/PATCH`, `DELETE`).

### 🔐 Autenticación (Auth)

| Método | Endpoint        | Descripción                                      |
| ------ | --------------- | ------------------------------------------------ |
| `POST` | `/auth/login`   | Iniciar sesión y obtener token de acceso.        |
| `POST` | `/auth/logout`  | Cerrar sesión e invalidar token (Requiere Auth). |
| `POST` | `/auth/refresh` | Refrescar token de sesión (Requiere Auth).       |

### 📍 Ubicaciones (Locations)

| Método        | Endpoint     | Descripción                                     |
| ------------- | ------------ | ----------------------------------------------- |
| `apiResource` | `/countries` | Gestión del catálogo de países.                 |
| `apiResource` | `/states`    | Gestión de estados/provincias.                  |
| `apiResource` | `/cities`    | Gestión de ciudades.                            |
| `apiResource` | `/branches`  | Gestión de sucursales operativas de la agencia. |

### 🚗 Inventario y Vehículos (Cars)

| Método        | Endpoint        | Descripción                                        |
| ------------- | --------------- | -------------------------------------------------- |
| `GET`         | `/car-statuses` | Lista de estatus disponibles para los vehículos.   |
| `apiResource` | `/car-types`    | Gestión de carrocerías (Sedan, SUV, Hatchback).    |
| `apiResource` | `/categories`   | Clasificación por categorías de gama.              |
| `apiResource` | `/brands`       | Catálogo de marcas de fabricantes.                 |
| `apiResource` | `/car-models`   | Modelos específicos asociados a las marcas.        |
| `apiResource` | `/colors`       | Catálogo de colores disponibles.                   |
| `apiResource` | `/cars`         | **Gestión principal del inventario de vehículos.** |

### 👥 Usuarios y Permisos (Users)

| Método        | Endpoint       | Descripción                                    |
| ------------- | -------------- | ---------------------------------------------- |
| `apiResource` | `/users`       | Gestión de empleados y usuarios del sistema.   |
| `apiResource` | `/roles`       | Configuración de roles de acceso.              |
| `GET`         | `/permissions` | Listado de permisos disponibles en el sistema. |

### 💰 Ventas y Finanzas (Sales)

| Método        | Endpoint               | Descripción                                                 |
| ------------- | ---------------------- | ----------------------------------------------------------- |
| `apiResource` | `/currencies`          | Gestión de monedas base y tasas de cambio.                  |
| `apiResource` | `/payment-methods`     | Catálogo de métodos de pago.                                |
| `apiResource` | `/taxes`               | Gestión de impuestos configurables (IVA, Lujo, etc).        |
| `GET`         | `/sales`               | Historial general de ventas registradas.                    |
| `POST`        | `/sales`               | **Procesar una nueva venta** (Aplica cálculos e impuestos). |
| `GET`         | `/sales/{sale}`        | Obtener el recibo y desglose detallado de una venta.        |
| `PATCH`       | `/sales/{sale}/cancel` | Cancelar venta, revertir estatus y liberar el vehículo.     |

---

_Desarrollado con arquitectura moderna en Laravel._
