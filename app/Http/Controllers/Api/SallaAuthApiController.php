<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SallaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SallaAuthApiController extends Controller
{
    public function redirect(Request $request, SallaService $sallaService): JsonResponse|RedirectResponse
    {
        // حفظ هل الطلب من تطبيق الـ SPA (الفرونت إند) أم من متصفح الـ Web المباشر
        session(['auth_is_spa' => $request->wantsJson() || $request->is('v1/*') || $request->is('api/*')]);

        $url = $sallaService->getAuthorizationUrl();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'url' => $url,
                'oauthUrl' => $url,
            ]);
        }

        return redirect()->away($url);
    }

    public function callback(Request $request, SallaService $sallaService): RedirectResponse
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $isSpa = session('auth_is_spa', false);
        session()->forget('auth_is_spa');

        if ($request->has('error')) {
            return redirect($isSpa ? ($frontendUrl . '/login?error=salla_denied') : route('login'));
        }

        if (! $request->filled('code')) {
            return redirect($isSpa ? ($frontendUrl . '/login?error=code_missing') : route('login'));
        }

        try {
            $user = $sallaService->handleCallback($request->code, $request->query('state'));

            Auth::login($user, true);

            // إذا كانت المصادقة بدأت من الفرونت إند الـ SPA -> وجهه لـ localhost:5173 مع التوكن
            if ($isSpa) {
                $token = $user->createToken('spa_api_token')->plainTextToken;
                return redirect($frontendUrl . '/auth/callback?token=' . $token);
            }

            // إذا كانت المصادقة من ويب الباك إند المباشر -> وجهه لـ /dashboard الباك إند
            return redirect()->route('dashboard');
        } catch (\Throwable $e) {
            report($e);
            return redirect($isSpa ? ($frontendUrl . '/login?error=auth_failed') : route('login'));
        }
    }
}
