<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BakongPaymentController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
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

Route::post('auth/register', [AuthController::class,'register']);
Route::post('auth/login', [AuthController::class,'login']);

Route::apiResource('products',ProductController::class)->only(['index','show']);

Route::apiResource('categories',CategoryController::class)->only(['index','show']);

Route::post('/bakong/callback', [BakongPaymentController::class, 'callback'])
        ->name('bakong.callback');

// user + admin
Route::middleware(['auth:api'])->group(function () {

    Route::get('/user',[AuthController::class, 'profile']);
    Route::post('/user', [AuthController::class, 'updateProfile']);

    // user + admin

    Route::apiResource('cart', CartController::class)->except(['create','edit','show','destroy']);
    Route::delete('/cart/product/{productId}', [CartController::class, 'destroyByProduct']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);

    Route::apiResource('orders',OrderController::class);

   Route::post('/bakong/pay/{order}', [BakongPaymentController::class, 'create']);
    Route::get('/bakong/check/{payment}', [BakongPaymentController::class, 'check']);
});

// admin 
Route::middleware(['auth:api','role:admin'])->group(function () {
    Route::apiResource('categories',CategoryController::class)->except(['index','show']);
    Route::apiResource('products',ProductController::class)->except('index','show');
});




