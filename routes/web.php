<?php

use App\Http\Controllers\Api\SallaAuthApiController;
use App\Http\Controllers\Api\ZidAuthApiController;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('login');

Route::get('/welcome', function () {
    return view('welcome');
})->name('welcome');

// 🟢 Salla Authentication Routes (Unified API & Web Callbacks)
Route::get('/oauth/redirect', [SallaAuthApiController::class, 'redirect'])->name('oauth.redirect');
Route::get('/oauth/callback', [SallaAuthApiController::class, 'callback'])->name('oauth.callback');
Route::get('/auth/salla', [SallaAuthApiController::class, 'redirect'])->name('auth.salla.redirect');
Route::get('/auth/salla/callback', [SallaAuthApiController::class, 'callback'])->name('auth.salla.callback');

// 🟣 Zid Authentication Routes (Unified API & Web Callbacks)
Route::get('/auth/zid', [ZidAuthApiController::class, 'redirect'])->name('auth.zid.redirect');
Route::get('/auth/zid/callback', [ZidAuthApiController::class, 'callback'])->name('auth.zid.callback');

// Protected Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // 🔴 Logout Route
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    })->name('logout');
});
