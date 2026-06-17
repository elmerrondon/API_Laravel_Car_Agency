<?php

use App\Http\Controllers\Locations\CityController;
use App\Http\Controllers\Locations\CountryController;
use App\Http\Controllers\Locations\StateController;
use Illuminate\Support\Facades\Route;

// Locations
Route::post('countries/{id}/restore', [CountryController::class, 'restore']);
Route::apiResource('countries', CountryController::class);
Route::post('states/{id}/restore', [StateController::class, 'restore']);
Route::apiResource('states', StateController::class);
Route::apiResource('cities', CityController::class);