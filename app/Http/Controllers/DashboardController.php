<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $token = $user?->token;

        return view('dashboard', [
            'user' => $user,
            'token' => $token,
            'platform' => $token?->platform ?? 'N/A',
            'storeName' => $token?->store_name ?? 'متجر غير معرف',
            'merchantId' => $token?->merchant ?? 'N/A',
        ]);
    }
}
