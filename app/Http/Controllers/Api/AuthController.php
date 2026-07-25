<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->token;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user?->id,
                    'name' => $user?->name,
                    'email' => $user?->email,
                ],
                'store' => [
                    'platform' => $token?->platform,
                    'merchant_id' => $token?->merchant,
                    'store_name' => $token?->store_name,
                    'is_connected' => (bool) $token,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $isSecure = $request->secure() || config('app.env') === 'production' || str_starts_with((string) config('app.url'), 'https://');
        $sameSite = $isSecure ? 'None' : 'Lax';
        $cookie = cookie('access_token', '', -1, '/', null, $isSecure, true, false, $sameSite);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح',
        ])->withCookie($cookie);
    }
}
