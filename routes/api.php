<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OAuthController;
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
});
