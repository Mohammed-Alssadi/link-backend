<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MerchantProfileController;
use App\Http\Controllers\Api\SallaAuthApiController;
use App\Http\Controllers\Api\StoreProfileController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\ZidAuthApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for SPA / External Clients (Pure REST API)
|--------------------------------------------------------------------------
*/

// 🟢 Public API Auth Endpoints
Route::prefix('v1/auth')->group(function () {
    Route::get('/salla/redirect', [SallaAuthApiController::class, 'redirect'])->name('api.auth.salla.redirect');
    Route::get('/salla/callback', [SallaAuthApiController::class, 'callback'])->name('api.auth.salla.callback');

    Route::get('/zid/redirect', [ZidAuthApiController::class, 'redirect'])->name('api.auth.zid.redirect');
    Route::get('/zid/callback', [ZidAuthApiController::class, 'callback'])->name('api.auth.zid.callback');
});

// 🔒 Protected Sanctum API Endpoints
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    // 🏬 Store Live Profile Endpoints
    Route::get('/store/profile', [StoreProfileController::class, 'show'])->name('api.store.profile');
    Route::get('/store/store-profile', [StoreProfileController::class, 'show'])->name('api.store.store_profile');

    // 👤 Merchant User Live Profile Endpoints
    Route::get('/user/profile', [UserProfileController::class, 'show'])->name('api.user.profile');
    Route::get('/merchant/profile', [UserProfileController::class, 'show'])->name('api.merchant.profile');
});
