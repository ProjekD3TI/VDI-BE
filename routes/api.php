<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VmController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    // =========AUTH===========
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('me', [AuthController::class, 'me']);

    // =========VM===========

    Route::get('vms', [VmController::class, 'index']);
});