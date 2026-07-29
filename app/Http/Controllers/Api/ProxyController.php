<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProxyController extends Controller
{
    public function __construct(protected PlatformService $platformService) {}

    /**
     * استقبال ومعالجة طلبات البروكسي الديناميكية لجميع المسارات والعمليات
     */
    public function handle(Request $request, ?string $path = ''): JsonResponse
    {
        $cleanPath = '/' . ltrim($path ?? '', '/');
        $method    = $request->method();

        try {
            $result = $this->platformService->proxy(
                $request->user(),
                $method,
                $path ?? '',
                $request->query(),
                // ✅ استخدام post() فقط للـ Body بدلاً من all() التي تخلط Query مع Body
                $request->isMethod('GET') ? [] : $request->post(),
            );

            // Debug log للكشف عن استجابات فارغة أو غير متوقعة
            if (($result['status'] ?? 200) >= 200 && ($result['status'] ?? 200) < 300) {
                $dataCount = count($result['body']['data'] ?? []);
                if ($dataCount === 0 && in_array($cleanPath, ['/products', '/products/', '/categories', '/categories/'])) {
                    Log::warning("[Proxy ⚠️ Empty Response] {$method} {$cleanPath}", [
                        'status'     => $result['status'],
                        'body_keys'  => array_keys($result['body'] ?? []),
                        'pagination' => $result['body']['pagination'] ?? null,
                        'raw_count'  => $result['body']['pagination']['totalCount'] ?? 'unknown',
                    ]);
                }
            }

            return response()->json($result['body'], $result['status'] ?? 200);

        } catch (Throwable $e) {
            // Log الخطأ مع السياق الكامل للتشخيص
            Log::error("[Proxy ❌ Exception] {$method} {$cleanPath}", [
                'error'     => $e->getMessage(),
                'class'     => get_class($e),
                'file'      => basename($e->getFile()) . ':' . $e->getLine(),
                'user_id'   => $request->user()?->id,
                'query'     => $request->query(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطأ في خادم البروكسي: ' . $e->getMessage(),
            ], 500);
        }
    }
}

