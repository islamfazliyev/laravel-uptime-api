<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MonitorController;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
        });
    Route::get('/monitors', [MonitorController::class, 'index']);
    Route::post('/monitors', [MonitorController::class, 'store']);
    Route::post('/monitors/check', [MonitorController::class, 'checkAll']);
    Route::delete('/monitors/{id}', [MonitorController::class, 'destroy']);
});