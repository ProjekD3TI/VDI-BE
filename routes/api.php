<?php

use App\Http\Controllers\Api\AngkatanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GuacamoleController;
use App\Http\Controllers\Api\IpAddressController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VmController;
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
        Route::post('vms/start', [VmController::class, 'startVm']);
        Route::post('vms/stop', [VmController::class, 'stopVm']);

        // =========AG===========
        Route::get('guacamole/users', [GuacamoleController::class, 'getUsers']);
        Route::apiResource('angkatan', AngkatanController::class);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{id}', [UserController::class, 'getUserById']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);

        Route::get('ip_address', [IpAddressController::class, 'index']);
        Route::get('ip_address/available', [IpAddressController::class, 'available']);
    });

});