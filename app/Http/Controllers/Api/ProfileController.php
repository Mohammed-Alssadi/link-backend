<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(protected PlatformService $platformService) {}

    /**
     * جلب بيانات التاجر الحية الموحدة لجميع المنصات
     */
    public function user(Request $request): JsonResponse
    {
        $data = $this->platformService->getUserProfile($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * جلب بيانات المتجر الحية الموحدة لجميع المنصات
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->platformService->getStoreProfile($request->user());

        return response()->json([
            'success' => true,
            'source' => 'api',
            'data' => $data,
        ]);
    }
}
