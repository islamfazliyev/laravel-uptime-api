<?php

use App\Http\Controllers\MonitorController;
use Illuminate\Support\Facades\Route;

Route::get('/monitors', [MonitorController::class, 'index']);
Route::post('/monitors', [MonitorController::class, 'store']);
Route::post('/monitors/check', [App\Http\Controllers\MonitorController::class, 'checkAll']);
Route::delete('/monitors/{id}', [App\Http\Controllers\MonitorController::class, 'destroy']);