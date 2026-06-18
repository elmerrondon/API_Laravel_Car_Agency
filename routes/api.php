<?php

use App\Http\Controllers\Locations\CityController;
use App\Http\Controllers\Locations\CountryController;
use App\Http\Controllers\Locations\StateController;
use Illuminate\Support\Facades\Route;

// Locations
Route::apiResource('countries', CountryController::class);
Route::apiResource('states', StateController::class);
Route::apiResource('cities', CityController::class);