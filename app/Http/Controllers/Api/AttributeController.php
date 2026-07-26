<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AttributeController extends Controller
{
    public function __construct(protected PlatformService $platformService) {}

    /**
     * جلب سمات المتجر العامة الحية (Store Attributes)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $attributes = $this->platformService->getAttributes($user);

            return response()->json([
                'success' => true,
                'data' => $attributes,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * إنشاء سمة متجر عامة جديدة
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $attribute = $this->platformService->createAttribute($user, $request->all());

            return response()->json([
                'success' => true,
                'data' => $attribute,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * إضافة قيمة مسبقة (Preset) لسمة متجر
     */
    public function addPreset(Request $request, string $attributeId): JsonResponse
    {
        try {
            $user = $request->user();
            $preset = $this->platformService->addAttributePreset($user, $attributeId, $request->all());

            return response()->json([
                'success' => true,
                'data' => $preset,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
