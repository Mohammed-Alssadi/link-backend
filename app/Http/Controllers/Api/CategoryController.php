<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(protected PlatformService $platformService) {}

    /**
     * جلب تصنيفات المتجر الحية الموحدة
     */
    public function index(Request $request): JsonResponse
    {
        $categories = $this->platformService->getCategories($request->user());

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
