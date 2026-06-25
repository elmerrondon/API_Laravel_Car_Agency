<?php

use App\Http\Controllers\Cars\BrandController;
use App\Http\Controllers\Cars\CarTypeController;
use App\Http\Controllers\Cars\CategoryController;
use App\Http\Controllers\Locations\BranchController;
use App\Http\Controllers\Locations\CityController;
use App\Http\Controllers\Locations\CountryController;
use App\Http\Controllers\Locations\StateController;
use Illuminate\Support\Facades\Route;

// Locations
Route::apiResource('countries', CountryController::class);
Route::apiResource('states', StateController::class);
Route::apiResource('cities', CityController::class);
Route::apiResource('branches', BranchController::class);

// Cars
Route::apiResource('car-types', CarTypeController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('brands', BrandController::class);