<?php

use App\Http\Controllers\Api\OAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pure REST API Web Routes & OAuth Callbacks
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'service' => 'Link SaaS Backend REST API Service',
        'status' => 'online',
        'version' => '1.0.0',
    ]);
});

// 🟢 OAuth Fallback / Web Callbacks (Matches Salla/Zid Partner Portal URLs)
Route::get('/auth/{platform}/redirect', [OAuthController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/{platform}/callback', [OAuthController::class, 'callback'])->name('auth.callback');

// Aliases for legacy web routes
Route::get('/auth/salla/callback', [OAuthController::class, 'callback'])->name('auth.salla.callback');
Route::get('/auth/zid/callback', [OAuthController::class, 'callback'])->name('auth.zid.callback');
