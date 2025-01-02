<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InspectorController;
use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post("/admin-login", [AdminController::class, 'login']);
Route::post("/create-admin", [AdminController::class, 'registerAdmin']);
Route::post('/inspector-login', "InspectorController@login");


Route::group(['middleware' => 'auth:sanctum'], function () {

    // routes for the admin
    Route::group(['prefix' => 'admin'], function () {
        Route::post('/create-inspector', "AdminController@createInspector");
    });


    // routes for the inspector
    Route::group(['prefix' => 'inspector'], function () {

    });

});
