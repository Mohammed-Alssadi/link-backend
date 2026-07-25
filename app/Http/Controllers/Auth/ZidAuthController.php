<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ZidService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ZidAuthController extends Controller
{
    public function redirect(ZidService $zidService): RedirectResponse
    {
        return redirect($zidService->getAuthorizationUrl());
    }

    public function callback(Request $request, ZidService $zidService): RedirectResponse
    {
        if ($request->has('error')) {
            $msg = $request->get('error_description') ?: $request->get('error');

            return redirect('/')->with('error', 'Zid OAuth Error: '.$msg);
        }

        if (! $request->filled('code')) {
            return redirect('/')->with('error', 'Authorization code missing.');
        }

        try {
            $user = $zidService->handleCallback($request->code, $request->query('state'));

            Auth::login($user);

            return redirect()->route('dashboard');
        } catch (\Throwable $e) {
            report($e);

            return redirect('/')->with('error', 'فشلت عملية المصادقة مع منصة زد. يرجى المحاولة مرة أخرى.');
        }
    }
}
