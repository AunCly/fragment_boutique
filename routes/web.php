<?php

use App\Http\Controllers\ShopifyController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopifyController::class, 'index'])->name('shop.index');
Route::post('/checkout', [ShopifyController::class, 'checkout'])->name('shop.checkout');
