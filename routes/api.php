<?php

use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OAuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for SPA / External Clients (Pure REST API)
|--------------------------------------------------------------------------
*/

// 🟢 Public API Auth Endpoints (Dynamic OAuth for Salla, Zid, etc.)
Route::prefix('v1/auth')->group(function () {
    Route::get('/{platform}/redirect', [OAuthController::class, 'redirect'])->name('api.auth.redirect');
    Route::get('/{platform}/callback', [OAuthController::class, 'callback'])->name('api.auth.callback');

    // Backward compatibility route aliases
    Route::get('/salla/redirect', [OAuthController::class, 'redirect'])->name('api.auth.salla.redirect');
    Route::get('/salla/callback', [OAuthController::class, 'callback'])->name('api.auth.salla.callback');
    Route::get('/zid/redirect', [OAuthController::class, 'redirect'])->name('api.auth.zid.redirect');
    Route::get('/zid/callback', [OAuthController::class, 'callback'])->name('api.auth.zid.callback');
});

// 🔒 Protected Sanctum API Endpoints
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    // 🏬 Store Live Profile Endpoints
    Route::get('/store/profile', [ProfileController::class, 'store'])->name('api.store.profile');
    Route::get('/store/store-profile', [ProfileController::class, 'store'])->name('api.store.store_profile');

    // 👤 Merchant User Live Profile Endpoints
    Route::get('/user/profile', [ProfileController::class, 'user'])->name('api.user.profile');
    Route::get('/merchant/profile', [ProfileController::class, 'user'])->name('api.merchant.profile');

    // 📂 Categories Live Endpoint
    Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');

    // 🏷️ Store Attributes Live Endpoints (Zid Attributes)
    Route::get('/attributes', [AttributeController::class, 'index'])->name('api.attributes.index');
    Route::post('/attributes', [AttributeController::class, 'store'])->name('api.attributes.store');
    Route::post('/attributes/{attributeId}/presets', [AttributeController::class, 'addPreset'])->name('api.attributes.presets.store');

    // 📦 Products Live Endpoints
    Route::get('/products', [ProductController::class, 'index'])->name('api.products.index');
    Route::get('/products/{id}', [ProductController::class, 'show'])->name('api.products.show');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('api.products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('api.products.destroy');
});
