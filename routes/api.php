<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductController::class, 'index']);
Route::post('/cart/quote', [CartController::class, 'quote']);
Route::post('/reservations', [ReservationController::class, 'store']);
Route::post('/reservations/{reservation}/release', [ReservationController::class, 'release']);
