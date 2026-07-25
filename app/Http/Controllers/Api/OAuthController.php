<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class OAuthController extends Controller
{
    public function __construct(protected PlatformService $platformService) {}

    /**
     * توجيه التاجر لصفحة التخويل للمنصة المحددة dynamic redirection
     */
    public function redirect(string $platform, Request $request): JsonResponse|RedirectResponse
    {
        try {
            $url = $this->platformService->getAuthorizationUrl($platform);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'url' => $url,
                    'oauthUrl' => $url,
                ]);
            }

            return redirect()->away($url);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            throw $e;
        }
    }

    /**
     * استقبال التخويل من المنصة وإنشاء جلسة التاجر dynamic callback
     */
    public function callback(string $platform, Request $request): RedirectResponse
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/');

        if ($request->has('error')) {
            $errorReason = $request->query('error_description', 'تم إلغاء الإذن من المنصة');

            return redirect($frontendUrl.'/auth/callback?error='.urlencode($errorReason));
        }

        if (! $request->filled('code')) {
            return redirect($frontendUrl.'/auth/callback?error=code_missing');
        }

        try {
            $user = $this->platformService->handleCallback($platform, $request->code, $request->query('state'));
            $token = $user->createToken('spa_api_token')->plainTextToken;

            return redirect($frontendUrl.'/auth/callback?token='.urlencode($token));
        } catch (Throwable $e) {
            report($e);

            return redirect($frontendUrl.'/auth/callback?error='.urlencode($e->getMessage()));
        }
    }
}
