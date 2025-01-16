<?php

use App\Http\Controllers\ViolationController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectorController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post("/admin-login", [AdminController::class, 'login']);
Route::post("/create-admin", [AdminController::class, 'registerAdmin']);
Route::post('/inspector-login', [InspectorController::class, 'login']);

// password reset (public route)
Route::group(['prefix' => 'auth'], function () {
    Route::post('/change-password-request', [AuthController::class, 'changePasswordRequest']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

// Routes protected by Sanctum authentication
Route::group(['middleware' => 'auth:sanctum'], function () {

    // Admin Routes
    Route::group(['prefix' => 'admin', 'middleware' => 'is_admin'], function () {
        Route::post('/create-inspector', [AdminController::class, 'createInspector']);
        Route::get('/inspectors', [AdminController::class, 'inspectors']);
        Route::delete('/delete-inspector/{inspector_id}', [AdminController::class, 'deleteInspector']);
        Route::get('/inspections', [AdminController::class, 'getInspections']);
        Route::post('/admin-logout', [AuthController::class, 'logout']);
    });

    // Inspector Routes
    Route::group(['prefix' => 'inspector', 'middleware' => 'is_inspector'], function () {
        Route::post('/add-inspection', [InspectorController::class, 'addInspection']);
        Route::delete('/delete-inspection/{inspection_id}', [InspectorController::class, 'deleteInspection']);
        Route::get('/inspections', [InspectorController::class, 'getInspections']);

        Route::put('/update-inspection/{inspection_id}', [InspectorController::class, 'updateInspection']);

        Route::post('/inspector-logout', [AuthController::class, 'logout']);
    });

    // Inspection Routes (accessible by both inspectors and admins)
    Route::group(['prefix' => 'inspection'], function () {
        Route::get('/inspections', [InspectionController::class, 'getInspections']);
        Route::get('/inspections/{id}', [InspectionController::class, 'getInspectionById']);
        Route::delete('/delete-violation/{violation_id}', [InspectorController::class, 'deleteViolation']);
        Route::get('/violators', [InspectionController::class, 'getInspectionsWithViolations']);
        Route::post('/resolve/{id}', [ViolationController::class, 'resolve']);
        Route::get('/upcoming-dues', [InspectionController::class, 'getUpcomingDues']);
        Route::get('/overdue-violations', [InspectionController::class, 'getOverDueViolators']);
    })->middleware('auth:admin|inspector'); // Modified middleware to allow both admins and inspectors

    // Dashboard api endpoints (accessible by both inspectors and admins)
    Route::group(['prefix' => 'dashboard'], function () {
        Route::get('/card-info', [DashboardController::class, 'getCardInfo']);
        Route::post('/resolve-violation/{violation_id}', [InspectorController::class, 'resolveViolation']);
    })->middleware('auth:admin|inspector'); // Modified middleware to allow both admins and inspectors

    // Violation Routes (accessible by both inspectors and admins)
    Route::group(['prefix' => 'violation'], function () {
        Route::get('/violators', [ViolationController::class, 'getViolators']);
    })->middleware('auth:admin|inspector'); // Modified middleware to allow both admins and inspectors

});
