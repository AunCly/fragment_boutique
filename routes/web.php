<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\ShopifyController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopifyController::class, 'index'])->name('shop.index');
Route::get('/products', [ShopifyController::class, 'products'])->name('shop.products');
Route::get('/custom', [ShopifyController::class, 'custom'])->name('shop.custom');
Route::post('/checkout', [ShopifyController::class, 'checkout'])->name('shop.checkout');

Route::prefix('account')->name('account.')->group(function () {
    Route::get('/login', [AccountController::class, 'showLogin'])->name('login');
    Route::post('/login', [AccountController::class, 'login']);
    Route::get('/register', [AccountController::class, 'showRegister'])->name('register');
    Route::post('/register', [AccountController::class, 'register']);
    Route::get('/password/reset', [AccountController::class, 'showForgotPassword'])->name('password.reset');
    Route::post('/password/reset', [AccountController::class, 'sendPasswordReset']);

    Route::middleware('shopify.customer.auth')->group(function () {
        Route::post('/logout', [AccountController::class, 'logout'])->name('logout');
        Route::get('/', [AccountController::class, 'showDashboard'])->name('dashboard');
        Route::get('/orders/{orderNumber}', [AccountController::class, 'showOrder'])->name('orders.show');
        Route::get('/orders/{orderNumber}/invoice', [AccountController::class, 'downloadInvoice'])->name('orders.invoice');
        Route::get('/profile', [AccountController::class, 'showProfile'])->name('profile');
        Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
        Route::put('/profile/address', [AccountController::class, 'updateAddress'])->name('profile.address');
    });
});

Route::prefix('fragment-admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login']);

    Route::middleware('admin.auth')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('faqs', AdminFaqController::class)->except(['show']);
    });
});
