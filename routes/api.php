<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OAuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProxyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for SPA / External Clients (Pure REST API)
|--------------------------------------------------------------------------
*/

// 🟢 Public API Auth Endpoints (Dynamic OAuth for Salla, Zid, etc.)
Route::prefix('auth')->group(function () {
    Route::get('/{platform}/redirect', [OAuthController::class, 'redirect'])->name('api.auth.redirect');
    Route::get('/{platform}/callback', [OAuthController::class, 'callback'])->name('api.auth.callback');

    // Backward compatibility route aliases
    Route::get('/salla/redirect', [OAuthController::class, 'redirect'])->name('api.auth.salla.redirect');
    Route::get('/salla/callback', [OAuthController::class, 'callback'])->name('api.auth.salla.callback');
    Route::get('/zid/redirect', [OAuthController::class, 'redirect'])->name('api.auth.zid.redirect');
    Route::get('/zid/callback', [OAuthController::class, 'callback'])->name('api.auth.zid.callback');
});

// 🔒 Protected Sanctum API Endpoints (/api/proxy/*, /api/me, /api/store/profile, etc.)
Route::middleware('auth:sanctum')->group(function () {
    // 🌐 Dynamic Proxy Endpoint (Matches /api/proxy/* for Express compatibility)
    Route::any('/proxy/{path?}', [ProxyController::class, 'handle'])->where('path', '.*')->name('api.proxy');

    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    // 🏬 Store Live Profile Endpoints
    Route::get('/store/profile', [ProfileController::class, 'store'])->name('api.store.profile');
    Route::get('/store/store-profile', [ProfileController::class, 'store'])->name('api.store.store_profile');

    // 👤 Merchant User Live Profile Endpoints
    Route::get('/user/profile', [ProfileController::class, 'user'])->name('api.user.profile');
    Route::get('/merchant/profile', [ProfileController::class, 'user'])->name('api.merchant.profile');
});
