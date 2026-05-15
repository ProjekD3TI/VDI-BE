<?php

use App\Http\Controllers\Api\AngkatanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GuacamoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VmController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    // =========AUTH===========
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('me', [AuthController::class, 'me']);

    Route::middleware('admin')->group(function () {
        // =========VM===========
        Route::get('vms', [VmController::class, 'index']);
        Route::post('vms', [VmController::class, 'store']);
        Route::delete('vms/{id}', [VmController::class, 'destroy']);

        // =========AG===========
        Route::get('guacamole/users', [GuacamoleController::class, 'getUsers']);
        Route::apiResource('angkatan', AngkatanController::class);
        Route::post('/users', [UserController::class, 'store']);
    });

});