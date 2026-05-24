<?php

use App\Http\Controllers\ShopifyController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopifyController::class, 'index'])->name('shop.index');
Route::get('/products', [ShopifyController::class, 'products'])->name('shop.products');
Route::get('/custom', [ShopifyController::class, 'custom'])->name('shop.custom');
Route::post('/checkout', [ShopifyController::class, 'checkout'])->name('shop.checkout');
