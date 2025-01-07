<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectorController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\CheckScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post("/admin-login", [AdminController::class, 'login']);
Route::post("/create-admin", [AdminController::class, 'registerAdmin']);
Route::post('/inspector-login', [InspectorController::class, 'login']);

// Routes protected by Sanctum authentication
Route::group(['middleware' => 'auth:sanctum'], function () {

    // Admin Routes
    Route::group(['prefix' => 'admin', 'middleware' => 'is_admin'], function () {
        Route::post('/create-inspector', [AdminController::class, 'createInspector']);
        Route::get('/inspectors', [AdminController::class, 'inspectors']);
        Route::delete('/delete-inspector/{inspector_id}', [AdminController::class, 'deleteInspector']);
        Route::get('/inspections', [AdminController::class, 'getInspections']);
    });

    // Inspector Routes
    Route::group(['prefix' => 'inspector', 'middleware' => 'is_inspector'], function () {
        Route::post('/add-inspection', [InspectorController::class, 'addInspection']);
        Route::delete('/delete-inspection/{inspection_id}', [InspectorController::class, 'deleteInspection']);
    });

    // Inspection Routes (accessible by both inspectors and admins)
    Route::group(['prefix' => 'inspection'], function () {
        Route::get('/inspections', [InspectionController::class, 'getInspections']);
        Route::delete('/delete-violation/{violation_id}', [InspectorController::class, 'deleteViolation']);
    })->middleware('checkScope:admin,inspector');


    // Dashboard Routes (accessible by both inspectors and admins)
    Route::group(['prefix' => 'dashboard'], function () {
        Route::get('/card-info', [DashboardController::class, 'getCardInfo']);
        Route::post('/resolve-violation/{violation_id}', [InspectorController::class, 'resolveViolation']);
    })->middleware('checkScope:admin,inspector');
});
