<?php

use App\Http\Controllers\DataController;
use App\Http\Controllers\NewDataController;
use App\Http\Controllers\NewUpdateController;
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
    Route::get('/new-data', [NewDataController::class, 'getComplaintData']);
    Route::get('/new-update', [NewUpdateController::class, 'getUpdateData']);
});
