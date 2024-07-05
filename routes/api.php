<?php

use App\Http\Controllers\Api\RolePermissionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MessageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'auth'], function ()
{
	Route::post('login', [AuthController::class, 'login']);
	Route::post('register', [AuthController::class, 'register']);

	Route::group(['middleware' => 'auth:api'], function ()
	{
		Route::post('logout', [AuthController::class, 'logout']);
		Route::get('user', [AuthController::class, 'user']);
	});
});

Route::middleware('auth:sanctum')->group(function () {
    // Category
	Route::get('category', [CategoryController::class, 'index']);
    
	// Message
	Route::get('message', [MessageController::class, 'index']);
	Route::get('message/{id}', [MessageController::class, 'show']);

});

Route::get('/roles-permissions', [RolePermissionController::class, 'index']);