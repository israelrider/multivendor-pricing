<?php

use App\Http\Controllers\CartQuoteController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// All routes here are prefixed with /api (see bootstrap/app.php). README section 6 lists them.

Route::get('/ping', fn () => ['status' => 'ok']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/customers', [CustomerController::class, 'index']);

Route::post('/cart/quote', CartQuoteController::class);

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
