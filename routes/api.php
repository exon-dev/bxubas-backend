<?php

use App\Http\Controllers\InspectorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post("/admin-login", "AdminController@login");
Route::post("/create-admin", "AdminController@registerAdmin");
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
