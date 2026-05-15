<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ResourceController;
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products', [ProductController::class, 'index']);
Route::post('/cart/add', [CartController::class, 'addToCart']);
Route::get('/cart', [CartController::class, 'getCart']);
Route::post('/checkout', [OrderController::class, 'checkout']);
Route::post('/checkout-concurrent', [OrderController::class, 'checkoutConcurrent']);
Route::post('/limited-operation', [ResourceController::class, 'limitedOperation']);
Route::post('/checkout-async', [OrderController::class, 'checkoutAsync']);
Route::post('/process-batch', [OrderController::class, 'processBatch']);
Route::get('/simulate-load', [OrderController::class, 'simulateServerLoad']);
