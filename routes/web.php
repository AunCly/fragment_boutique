<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Admin\FormatController as AdminFormatController;
use App\Http\Controllers\Admin\FragmentProjectController as AdminFragmentProjectController;
use App\Http\Controllers\Admin\FragmentToolController as AdminFragmentToolController;
use App\Http\Controllers\Admin\GalleryImageController as AdminGalleryImageController;
use App\Http\Controllers\Admin\PaletteController as AdminPaletteController;
use App\Http\Controllers\Admin\WoodTextureController as AdminWoodTextureController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ShopifyController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopifyController::class, 'index'])->name('shop.index');
Route::get('/collection', [ShopifyController::class, 'products'])->name('shop.products');
Route::get('/collection/{id}', [ShopifyController::class, 'product'])->name('shop.product')->where('id', '[0-9]+');
Route::get('/comment-ca-marche', [ShopifyController::class, 'howItWorks'])->name('shop.how-it-works');
Route::get('/atelier', [ShopifyController::class, 'workshop'])->name('shop.workshop');
Route::get('/galerie', [ShopifyController::class, 'gallery'])->name('shop.gallery');
Route::get('/custom', [ShopifyController::class, 'custom'])->name('shop.custom');
Route::get('/contact', [ContactController::class, 'show'])->name('shop.contact');
Route::post('/contact', [ContactController::class, 'send'])->name('shop.contact.send')->middleware('throttle:5,1');
Route::get('/cgv', [ShopifyController::class, 'terms'])->name('shop.terms');
Route::get('/mentions-legales', [ShopifyController::class, 'legalNotice'])->name('shop.legal-notice');
Route::get('/livraison-et-retours', [ShopifyController::class, 'shippingReturns'])->name('shop.shipping-returns');
Route::get('/merci', [ShopifyController::class, 'thankYou'])->name('shop.thank-you');
Route::post('/checkout', [ShopifyController::class, 'checkout'])->name('shop.checkout');

Route::prefix('account')->name('account.')->group(function () {
    Route::get('/login', [AccountController::class, 'showLogin'])->name('login');
    Route::post('/login', [AccountController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', [AccountController::class, 'showRegister'])->name('register');
    Route::post('/register', [AccountController::class, 'register'])->middleware('throttle:20,1');
    Route::get('/activate', [AccountController::class, 'showActivate'])->name('activate');
    Route::post('/activate', [AccountController::class, 'activate'])->middleware('throttle:10,1');
    Route::get('/password/reset', [AccountController::class, 'showForgotPassword'])->name('password.reset');
    Route::post('/password/reset', [AccountController::class, 'sendPasswordReset'])->middleware('throttle:20,1');

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
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:20,1');

    Route::middleware('admin.auth')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('faqs', AdminFaqController::class)->except(['show']);
        Route::resource('wood-textures', AdminWoodTextureController::class)->except(['show']);
        Route::patch('/wood-textures/{wood_texture}/archive', [AdminWoodTextureController::class, 'archive'])->name('wood-textures.archive');
        Route::resource('formats', AdminFormatController::class)->except(['show']);
        Route::patch('/formats/{format}/archive', [AdminFormatController::class, 'archive'])->name('formats.archive');
        Route::resource('palettes', AdminPaletteController::class)->except(['show']);
        Route::patch('/palettes/{palette}/archive', [AdminPaletteController::class, 'archive'])->name('palettes.archive');
        Route::post('/palettes/{palette}/duplicate', [AdminPaletteController::class, 'duplicate'])->name('palettes.duplicate');

        Route::prefix('outil')->name('tool.')->controller(AdminFragmentToolController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/convert', 'convert')->name('convert');
            Route::post('/preview', 'preview')->name('preview');
            Route::post('/ai/generate', 'generate')->name('ai.generate');
            Route::post('/ai/refine', 'refine')->name('ai.refine');
            Route::post('/ai/suggest', 'suggest')->name('ai.suggest');
            Route::post('/motifs', 'motifs')->name('motifs');
            Route::post('/render', 'render')->name('render');
            Route::post('/render/zip', 'archive')->name('archive');
            Route::get('/projets/{project}', 'show')->name('projects.show');
        });
        Route::prefix('outil/projets')->name('tool.projects.')->controller(AdminFragmentProjectController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::patch('/{project}', 'update')->name('update');
            Route::delete('/{project}', 'destroy')->name('destroy');
            Route::get('/{project}/source', 'source')->name('source');
            Route::get('/{project}/miniature', 'thumbnail')->name('thumbnail');
            Route::get('/{project}/apercu', 'preview')->name('preview');
            Route::patch('/{project}/versions/{version}', 'updateVersion')->name('versions.update')->scopeBindings();
        });
        Route::view('/maquette-outil', 'admin.tool.mockup')->name('tool-mockup');
        Route::resource('gallery-images', AdminGalleryImageController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});
