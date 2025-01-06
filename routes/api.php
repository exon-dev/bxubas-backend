<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InspectorController;
use App\Http\Controllers\AdminController;
use App\Http\Middleware\CheckScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post("/admin-login", [AdminController::class, 'login']);
Route::post("/create-admin", [AdminController::class, 'registerAdmin']);
Route::post('/inspector-login', [InspectorController::class, 'login']);


Route::group(['middleware' => 'auth:sanctum'], function () {

    // routes for the admin
    Route::group(['prefix' => 'admin', 'middleware' => 'is_admin'], function () {
        Route::post('/create-inspector', [AdminController::class, 'createInspector']);
        Route::get('/inspectors', [AdminController::class, 'inspectors']);
        Route::delete('/delete-inspector/{inspector_id}', [AdminController::class, 'deleteInspector']);
    });

    // routes for the inspector
    Route::group(['prefix' => 'inspector', 'middleware' => 'is_inspector'], function () {
        Route::post('/add-inspection', [InspectorController::class, 'addInspection']);
        Route::get('/inspections', [InspectorController::class, 'getInspections']);
        Route::delete('/delete-inspection/{inspection_id}', [InspectorController::class, 'deleteInspection']);
    });
});

