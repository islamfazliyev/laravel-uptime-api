<?php

use App\Http\Controllers\MonitorController;
use Illuminate\Support\Facades\Route;

Route::get('/monitors', [MonitorController::class, 'index']);
Route::post('/monitors', [MonitorController::class, 'store']);