<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SallaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SallaAuthController extends Controller
{
    public function redirect(SallaService $sallaService): RedirectResponse
    {
        return redirect($sallaService->getAuthorizationUrl());
    }

    public function callback(Request $request, SallaService $sallaService): RedirectResponse
    {
        if ($request->has('error')) {
            $msg = $request->get('error_description') ?: $request->get('error');
            return redirect('/')->with('error', 'Salla OAuth Error: ' . $msg);
        }

        if (! $request->filled('code')) {
            return redirect('/')->with('error', 'Authorization code missing.');
        }

        try {
            $user = $sallaService->handleCallback($request->code, $request->query('state'));

            Auth::login($user);

            return redirect()->route('dashboard');
        } catch (\Throwable $e) {
            report($e);
            return redirect('/')->with('error', 'فشلت عملية المصادقة مع منصة سلة. يرجى المحاولة مرة أخرى.');
        }
    }
}
