<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AiForecastingController;

Route::prefix('ai')->group(function () {
    Route::get('/analyze', [AiForecastingController::class, 'analyze']);
    Route::get('/price-forecast', [AiForecastingController::class, 'forecastPrice']);
});
