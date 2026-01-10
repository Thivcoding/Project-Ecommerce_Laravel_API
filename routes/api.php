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

// Public routes
Route::post('auth/register', [AuthController::class,'register']);
Route::post('auth/login', [AuthController::class,'login']);

Route::apiResource('products', ProductController::class)->only(['index','show']);
Route::apiResource('categories', CategoryController::class)->only(['index','show']);

// Bakong callback (from Bakong server)
Route::post('/bakong/callback', [BakongPaymentController::class, 'callback'])
    ->name('bakong.callback');

// Protected routes (user + admin)
Route::middleware(['auth:api'])->group(function () {

    // User profile
    Route::get('/user', [AuthController::class, 'profile']);
    Route::post('/user', [AuthController::class, 'updateProfile']);

    // Cart
    Route::apiResource('cart', CartController::class)->except(['create','edit','show','destroy']);
    Route::delete('/cart/product/{productId}', [CartController::class, 'destroyByProduct']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);

    // Orders
    Route::apiResource('orders', OrderController::class);

    // Bakong Payment routes
    Route::post('/bakong/pay/{order}', [BakongPaymentController::class, 'create']); // generate KHQR
    Route::post('/bakong/verify', [BakongPaymentController::class, 'verifyTransaction']); // verify MD5
    Route::get('/bakong/check/{payment}', [BakongPaymentController::class, 'check']); // manual check
    Route::post('/bakong/cancel/{payment}', [BakongPaymentController::class, 'cancel']); // cancel payment
});

// Admin-only routes
Route::middleware(['auth:api','role:admin'])->group(function () {
    Route::apiResource('categories', CategoryController::class)->except(['index','show']);
    Route::apiResource('products', ProductController::class)->except(['index','show']);
});




