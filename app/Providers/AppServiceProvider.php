<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // لا يوجد singletons يدوية — Laravel يحل المصنع والمزودين تلقائياً عبر DI
    }

    public function boot(): void
    {
        // ─── Rate Limiting: تعريف حدود الطلبات لحماية الـ API ─────────────────

        // 2000 طلب لكل 15 دقيقة للـ API العام
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinutes(15, 2000)
                ->by($request->user()?->id ?: $request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'طلبات كثيرة جداً، يرجى المحاولة لاحقاً',
                ], 429));
        });

        // 20 طلب لكل 15 دقيقة لمسارات OAuth (حماية Brute Force)
        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinutes(15, 20)
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'محاولات كثيرة، يرجى الانتظار 15 دقيقة',
                ], 429));
        });
    }
}

