<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProxyController extends Controller
{
    public function __construct(protected PlatformService $platformService) {}

    /**
     * استقبال ومعالجة طلبات البروكسي الديناميكية لجميع المسارات والعمليات
     */
    public function handle(Request $request, ?string $path = ''): JsonResponse
    {
        $result = $this->platformService->proxy(
            $request->user(),
            $request->method(),
            $path ?? '',
            $request->query(),
            $request->all()
        );

        return response()->json($result['body'], $result['status']);
    }
}
