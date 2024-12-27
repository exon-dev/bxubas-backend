<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InspectorController;
use Illuminate\Support\Facades\Route;


// authentication routes
Route::post('/admin-login', [AdminController::class, 'login']);
Route::post('/inspector-login', [InspectorController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {

    // admin routes
    Route::post('/admin-logout', [AdminController::class, 'logout']);

    //inspector routes
    Route::post('/inspector-logout', [InspectorController::class, 'logout']);
});
