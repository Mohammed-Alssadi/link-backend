<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pure REST API Web Routes
|--------------------------------------------------------------------------
| All merchant authentication and API operations are strictly handled
| via REST API routes in routes/api.php for the SPA Frontend.
*/

Route::get('/', function () {
    return response()->json([
        'service' => 'Link SaaS Backend REST API Service',
        'status' => 'online',
        'version' => '1.0.0',
    ]);
});
