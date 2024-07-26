<?php

use App\Http\Controllers\DataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Middleware untuk autentikasi user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Middleware rate limiting untuk endpoint API
Route::middleware('throttle:2000,1')->group(function () {
    Route::get('/komplain-data', [DataController::class, 'getKomplainData']);
    Route::get('/available-dates', [DataController::class, 'getAvailableDates']);
});
