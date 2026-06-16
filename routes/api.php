<?php

use App\Http\Controllers\Locations\CountryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('countries', CountryController::class);