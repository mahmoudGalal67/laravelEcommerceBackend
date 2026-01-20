<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\PageController;
/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/logout', [AuthController::class, 'logout']);


/*
|--------------------------------------------------------------------------
| Public Data (No Auth)
|--------------------------------------------------------------------------
*/

Route::apiResource('colors', ColorController::class)->only(['index']);
Route::apiResource('sizes', SizeController::class)->only(['index']);
Route::apiResource('categories', CategoryController::class)->only(['index']);
Route::apiResource('products', ProductController::class)->only(['index', 'show']);


/*
|--------------------------------------------------------------------------
| Protected Routes (Token Required)
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt')->group(function () {

 // Authenticated user info
 Route::get('/user', [AuthController::class, 'me']);

 // Admin / CRUD except index
 Route::apiResource('colors', ColorController::class)->except(['index']);
 Route::apiResource('sizes', SizeController::class)->except(['index']);
 Route::apiResource('categories', CategoryController::class)->except(['index']);
 Route::delete('products', [ProductController::class,'destroy']);
 Route::apiResource('products', ProductController::class)->except(['index', 'show']);

 // Cart merge (when user logs in)
 Route::post('/cart/merge', [CartController::class, 'merge']);
});


/*
|--------------------------------------------------------------------------
| Cart Routes (Guest + Auth)
|--------------------------------------------------------------------------
*/

Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart', [CartController::class, 'store']);
Route::delete('/cart/{id}', [CartController::class, 'destroy']);
Route::delete('/cart/clear', [CartController::class, 'clear']);


/*
|--------------------------------------------------------------------------
| Checkout Routes ( Auth)
|--------------------------------------------------------------------------
*/
Route::middleware('auth.jwt')->group(
 function () {
  Route::post('/checkout', [CheckoutController::class, 'checkout']);
 }
);
/*


/*
|--------------------------------------------------------------------------
| Orders Routes ( Auth)
|--------------------------------------------------------------------------
*/
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
Route::middleware('auth.jwt')->group(
 function () {
  Route::get('/orders/client', [OrderController::class, 'clientIndex']);
  Route::get('/orders/client/{id}', [CheckoutController::class, 'show']);
  Route::put('/orders/client/{id}', [CheckoutController::class, 'update']);
  Route::delete('/orders/client/{id}', [CheckoutController::class, 'destroy']);

  Route::post('/stripe/payment-intent', [StripeController::class, 'createPaymentIntent']);
 }
);
/*


|--------------------------------------------------------------------------
| Admin Panel Routes (Sanctum + Role)
|--------------------------------------------------------------------------
*/


 Route::middleware(['auth.jwt', 'role:admin'])->group(function () {
Route::prefix('pages')->group(function () {
    Route::get('/', [PageController::class, 'index']);
    Route::get('{slug}', [PageController::class, 'show']);
    Route::post('/', [PageController::class, 'store']);
    Route::put('{slug}', [PageController::class, 'update']);
    Route::delete('{slug}', [PageController::class, 'destroy']);
});
});

/*
|--------------------------------------------------------------------------
| Admin and Sellers Panel Routes (Sanctum + Role)
|--------------------------------------------------------------------------
*/


 Route::middleware(['auth.jwt', 'role:seller'])->group(function () {
Route::prefix('orders')->group(function () {
     Route::get('/seller', [OrderController::class, 'sellerIndex']);
     Route::get('/{id}', [OrderController::class, 'show']);
     Route::post('/update-status/{id}', [OrderController::class, 'updateStatus']);
     Route::post('/cancel', [OrderController::class, 'cancelOrders']);
     Route::post('/cancel-seller', [OrderController::class, 'cancelSellerOrders']);
    });
});


// Users Manages
Route::middleware(['auth.jwt', 'role:admin'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::delete('users', [UserController::class,'destroy']);
});