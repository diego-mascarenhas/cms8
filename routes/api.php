<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\trade\TradeDataController;
use App\Http\Controllers\sys\WebhookController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApiAuthController;

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

Route::post('/auth/register', [ApiAuthController::class, 'register']);
Route::post('/auth/login', [ApiAuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request)
{
	return $request->user();
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request)
{
	return $request->user();
});

Route::post('/webhooks', [WebhookController::class, 'store']);
Route::post('/trade', [TradeDataController::class, 'store']);

// Route::group(['prefix' => 'auth'], function ()
// {
// 	Route::post('login', [AuthController::class, 'login']);
// 	Route::post('register', [AuthController::class, 'register']);

// 	Route::group(['middleware' => 'auth:api'], function ()
// 	{
// 		Route::get('logout', [AuthController::class, 'logout']);
// 		Route::get('user', [AuthController::class, 'user']);
// 	});
// });