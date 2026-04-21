<?php

use App\Http\Controllers\Api\GeographicController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Geographic API routes for hierarchical address selection (public access)
Route::prefix('geographic')->group(function () {
    Route::get('countries', [GeographicController::class, 'countries']);
    Route::get('regions/{countryCode}', [GeographicController::class, 'regions']);
    Route::get('provinces/{regionId}', [GeographicController::class, 'provinces']);
    Route::get('cities/{divisionId}', [GeographicController::class, 'cities']);
    Route::get('search', [GeographicController::class, 'search']);
    // Postal codes
    Route::get('postal-codes/{cityId}', [GeographicController::class, 'postalCodes']);
    Route::get('postal-codes-by-city', [GeographicController::class, 'postalCodesByCity']);
    Route::get('lookup/postal-code/{postalCode}', [GeographicController::class, 'lookupPostalCode']);
});

