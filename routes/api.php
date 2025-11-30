<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SizeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Authentacation

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/logout', [AuthController::class, 'logout']);

// Public
// Route::get('products/{product}', [ProductController::class, 'show']);

// // Auth (client/seller/admin)
// Route::post('auth/register', [AuthController::class, 'register']);
// Route::post('auth/login', [AuthController::class, 'login']);

// // Protected - any authenticated user
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('user', [AuthController::class, 'me']);
//     Route::post('logout', [AuthController::class, 'logout']);
//     // Cart & checkout
//     Route::get('cart', [CartController::class, 'getCart']);
//     Route::post('cart/add', [CartController::class, 'addItem']);
//     Route::post('checkout', [CheckoutController::class, 'startCheckout']);
//     Route::post('payment/initiate', [PaymentController::class, 'initiatePayment']);
// });

// // Seller-only
// Route::middleware(['auth:sanctum', 'role:seller'])->prefix('seller')->group(function () {
//     Route::apiResource('products', ProductController::class)->except(['index', 'show']);
//     Route::apiResource('products.variants', VariantController::class)->shallow();
//     Route::get('orders', [SellerOrderController::class, 'index']);
//     Route::post('products/{product}/images', [ProductImageController::class, 'store']);
// });

Route::apiResource('colors', ColorController::class)->only(['index']);
Route::apiResource('sizes', SizeController::class)->only(['index']);
Route::apiResource('categories', CategoryController::class)->only(['index']);
Route::apiResource('products', ProductController::class)->only(['index', 'show']);
// // Admin-only
Route::middleware('auth.jwt')->group(function () {
 Route::get('/user', [AuthController::class, 'me']);
 // Route::get('dashboard', [Admin\DashboardController::class, 'index']);
 // Route::resource('categories', CategoryController::class);
 // Route::get('orders', [Admin\OrderController::class, 'index']);
 // Route::post('sellers/{seller}/approve', [Admin\SellerController::class, 'approve']);
 Route::apiResource('colors', ColorController::class)->except(['index']);
 Route::apiResource('sizes', SizeController::class)->except(['index']);
 Route::apiResource('categories', CategoryController::class)->except(['index']);
 Route::apiResource('products', ProductController::class)->except(['index', 'show']);
});
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
 // Route::get('dashboard', [Admin\DashboardController::class, 'index']);
 // Route::resource('categories', CategoryController::class);
 // Route::get('orders', [Admin\OrderController::class, 'index']);
 // Route::post('sellers/{seller}/approve', [Admin\SellerController::class, 'approve']);
});

Route::post('/cart/guest', [CartController::class, 'storeGuest']);
Route::delete('/cart/guest/{id}', [CartController::class, 'destroyGuest']);
Route::get('/cart', [CartController::class, 'index']);
Route::middleware('auth.jwt')->group(function () {
 Route::post('/cart', [CartController::class, 'store']);
 Route::delete('/cart/clear', [CartController::class, 'clear']);
 Route::delete('/cart/{id}', [CartController::class, 'destroy']);
 Route::post('/cart/sync', [CartController::class, 'syncGuestCart']);
});
