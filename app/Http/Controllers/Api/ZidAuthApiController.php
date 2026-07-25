<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ZidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ZidAuthApiController extends Controller
{
    public function redirect(Request $request, ZidService $zidService): JsonResponse|RedirectResponse
    {
        // حفظ هل الطلب من تطبيق الـ SPA (الفرونت إند) أم من متصفح الـ Web المباشر
        session(['auth_is_spa' => $request->wantsJson() || $request->is('v1/*') || $request->is('api/*') || $request->has('spa')]);

        $url = $zidService->getAuthorizationUrl();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'url' => $url,
                'oauthUrl' => $url,
            ]);
        }

        return redirect()->away($url);
    }

    public function callback(Request $request, ZidService $zidService): RedirectResponse
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');
        $isSpa = session('auth_is_spa', true); // Default to true if accessed via API callback
        session()->forget('auth_is_spa');

        if ($request->has('error')) {
            $errorReason = $request->query('error_description', 'تم إلغاء الإذن من زد');

            return redirect($isSpa ? ($frontendUrl.'/login?error='.urlencode($errorReason)) : route('login'))->with('error', $errorReason);
        }

        if (! $request->filled('code')) {
            return redirect($isSpa ? ($frontendUrl.'/login?error=code_missing') : route('login'))->with('error', 'لم يتم استلام رمز الفحص (code missing)');
        }

        try {
            $user = $zidService->handleCallback($request->code, $request->query('state'));

            Auth::login($user, true);

            // إذا كانت المصادقة بدأت من الفرونت إند الـ SPA -> وجهه للفرونت إند مع إرفاق التوكن في الـ URL
            if ($isSpa) {
                $token = $user->createToken('spa_api_token')->plainTextToken;

                return redirect($frontendUrl.'/auth/callback?token='.urlencode($token));
            }

            // إذا كانت المصادقة من ويب الباك إند المباشر -> وجهه لـ /dashboard الباك إند
            return redirect()->route('dashboard');
        } catch (\Throwable $e) {
            report($e);
            $errMsg = 'فشل تسجيل الدخول عبر زد: '.$e->getMessage();

            return redirect($isSpa ? ($frontendUrl.'/login?error='.urlencode($e->getMessage())) : route('login'))->with('error', $errMsg);
        }
    }
}
