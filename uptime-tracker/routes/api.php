<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MonitorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/monitors', [MonitorController::class, 'index']);
    Route::post('/monitors', [MonitorController::class, 'store']);
    Route::post('/monitors/check', [MonitorController::class, 'checkAll']);
    Route::get('/monitors/{id}/stats', [MonitorController::class, 'stats']);
    Route::delete('/monitors/{id}', [MonitorController::class, 'destroy']);
});
