<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Cars\BrandController;
use App\Http\Controllers\Cars\CarController;
use App\Http\Controllers\Cars\CarModelController;
use App\Http\Controllers\Cars\CarStatusController;
use App\Http\Controllers\Cars\CarTypeController;
use App\Http\Controllers\Cars\CategoryController;
use App\Http\Controllers\Cars\ColorController;
use App\Http\Controllers\Locations\BranchController;
use App\Http\Controllers\Locations\CityController;
use App\Http\Controllers\Locations\CountryController;
use App\Http\Controllers\Locations\StateController;
use App\Http\Controllers\Reports\CarReportController;
use App\Http\Controllers\Reports\SaleReportController;
use App\Http\Controllers\Sales\CurrencyController;
use App\Http\Controllers\Sales\PaymentMethodController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\TaxController;
use App\Http\Controllers\Users\PermissionController;
use App\Http\Controllers\Users\RoleController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

// Autenticación: la única ruta pública es el login.
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    // Coches
    Route::middleware('permission:view_cars')->group(function () {
        Route::apiResource('cars', CarController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:create_cars')->group(function () {
        Route::apiResource('cars', CarController::class)->only(['store']);
    });
    Route::middleware('permission:update_cars')->group(function () {
        Route::apiResource('cars', CarController::class)->only(['update']);
    });
    Route::middleware('permission:delete_cars')->group(function () {
        Route::apiResource('cars', CarController::class)->only(['destroy']);
    });

    // Catálogos (coches, monedas, métodos de pago e impuestos)
    Route::middleware('permission:view_catalogs')->group(function () {
        Route::get('car-statuses', [CarStatusController::class, 'index']);
        Route::apiResource('car-types', CarTypeController::class)->only(['index', 'show']);
        Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
        Route::apiResource('brands', BrandController::class)->only(['index', 'show']);
        Route::apiResource('car-models', CarModelController::class)->only(['index', 'show']);
        Route::apiResource('colors', ColorController::class)->only(['index', 'show']);
        Route::apiResource('currencies', CurrencyController::class)->only(['index', 'show']);
        Route::apiResource('payment-methods', PaymentMethodController::class)->only(['index', 'show']);
        Route::apiResource('taxes', TaxController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:manage_catalogs')->group(function () {
        Route::apiResource('car-types', CarTypeController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('brands', BrandController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('car-models', CarModelController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('colors', ColorController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('currencies', CurrencyController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('payment-methods', PaymentMethodController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('taxes', TaxController::class)->only(['store', 'update', 'destroy']);
    });

    // Ubicaciones
    Route::middleware('permission:view_locations')->group(function () {
        Route::apiResource('countries', CountryController::class)->only(['index', 'show']);
        Route::apiResource('states', StateController::class)->only(['index', 'show']);
        Route::apiResource('cities', CityController::class)->only(['index', 'show']);
        Route::apiResource('branches', BranchController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:manage_locations')->group(function () {
        Route::apiResource('countries', CountryController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('states', StateController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('cities', CityController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('branches', BranchController::class)->only(['store', 'update', 'destroy']);
    });

    // Ventas
    Route::middleware('permission:view_sales')->group(function () {
        Route::apiResource('sales', SaleController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:create_sales')->group(function () {
        Route::apiResource('sales', SaleController::class)->only(['store']);
    });
    Route::middleware('permission:cancel_sales')->group(function () {
        Route::patch('sales/{sale}/cancel', [SaleController::class, 'cancelSale']);
    });

    // Reportes
    Route::middleware('permission:view_reports')->group(function () {
        Route::get('reports/sales/pdf', [SaleReportController::class, 'pdf']);
        Route::get('reports/cars', [CarReportController::class, 'index']);
        Route::get('reports/cars/pdf', [CarReportController::class, 'pdf']);
    });

    // Usuarios, roles y permisos
    Route::middleware('permission:manage_users')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', [PermissionController::class, 'index']);
    });
});
