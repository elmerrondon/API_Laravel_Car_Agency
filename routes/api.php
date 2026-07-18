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
use App\Http\Controllers\Sales\CurrencyController;
use App\Http\Controllers\Users\PermissionController;
use App\Http\Controllers\Users\RoleController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

// Locations
Route::apiResource('countries', CountryController::class);
Route::apiResource('states', StateController::class);
Route::apiResource('cities', CityController::class);
Route::apiResource('branches', BranchController::class);

// Cars
Route::get('car-statuses', [CarStatusController::class, 'index']);
Route::apiResource('car-types', CarTypeController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('brands', BrandController::class);
Route::apiResource('car-models', CarModelController::class);
Route::apiResource('colors', ColorController::class);
Route::apiResource('cars', CarController::class);

// Users 
Route::apiResource('users', UserController::class);
Route::apiResource('roles', RoleController::class);
Route::get('permissions', [PermissionController::class, 'index']);


// Sales
Route::apiResource('currencies', CurrencyController::class); 



// Auth
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
});