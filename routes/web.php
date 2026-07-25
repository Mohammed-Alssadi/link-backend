<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Reserved for Super Admin / Admin Dashboard)
|--------------------------------------------------------------------------
| All merchant auth, Salla/Zid OAuth, and API operations are strictly
| handled in routes/api.php for the SPA Frontend.
*/

Route::get('/', function () {
    return response()->json([
        'service' => 'SaaS Backend API Service',
        'status' => 'online',
    ]);
});
