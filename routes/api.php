<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamContactController;
use App\Http\Controllers\Api\TeamProjectController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
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

Route::post('/register-application', [LicenseController::class, 'register']);

Route::get('/roles-permissions', [RolePermissionController::class, 'index']);

// Team API routes protected by team token
Route::middleware('team.token')->prefix('team')->group(function () {
    // Team information
    Route::get('/', [TeamController::class, 'index']);
    Route::get('/settings', [TeamController::class, 'settings']);
    
    // Team contacts
    Route::resource('contacts', TeamContactController::class);
    
    // Team projects
    Route::resource('projects', TeamProjectController::class);
});
