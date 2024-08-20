<?php

use App\Http\Controllers\DataController;
use App\Http\Controllers\NewDataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pelaporan', function () {
    return view('pelaporan');
});



Route::get('/data', [DataController::class, 'index'])->name('data.index');
Route::get('/data/download', [DataController::class, 'download'])->name('data.download');
Route::get('/data/komplain', [DataController::class, 'getKomplainData'])->name('data.komplain');
Route::get('/data/view', [DataController::class, 'renderView'])->name('data.renderView');
Route::get('/new-data', [NewDataController::class, 'showComplaintData'])->name('newdata');


