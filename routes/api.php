<?php

use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\trade\TradeDataController;
use App\Http\Controllers\sys\WebhookController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthMobileController;
use App\Http\Controllers\Api\StructuredVoiceController;

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

Route::post('/mobile/register', [AuthMobileController::class, 'register']);
Route::post('/mobile/login', [AuthMobileController::class, 'login']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request)
{
	return $request->user();
});

Route::group(['prefix' => 'auth'], function ()
{
	Route::post('login', [AuthController::class, 'login']);
	Route::post('register', [AuthController::class, 'register']);

	Route::group(['middleware' => 'auth:api'], function ()
	{
		Route::get('logout', [AuthController::class, 'logout']);
		Route::get('user', [AuthController::class, 'user']);
	});
});

Route::post('/webhooks', [WebhookController::class, 'store']);
Route::post('/trade', [TradeDataController::class, 'store']);

Route::get('categories', [CategoryController::class, 'index']);

Route::post('/process-order', [StructuredVoiceController::class, 'processOrder']);
