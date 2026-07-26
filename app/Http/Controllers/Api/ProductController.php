<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformService;
use App\Http\Requests\ProductFilterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(protected PlatformService $platformService) {}

    /**
     * جلب قائمة المنتجات الحية الموحدة لجميع المنصات
     */
    public function index(ProductFilterRequest $request): JsonResponse
    {
        $filters = $request->validatedFilters();

        $result = $this->platformService->getProducts($request->user(), $filters);

        if (isset($result['data'])) {
            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * جلب بيانات منتج محدد حية برقم الـ ID
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $product = $this->platformService->getProduct($request->user(), $id);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * تحديث بيانات منتج محدد حية برقم الـ ID
     */
    public function update(string $id, Request $request): JsonResponse
    {
        $product = $this->platformService->updateProduct($request->user(), $id, $request->all());

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * حذف منتج محدد حية برقم الـ ID
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $success = $this->platformService->deleteProduct($request->user(), $id);

        return response()->json([
            'success' => $success,
            'message' => 'تم حذف المنتج بنجاح',
        ]);
    }
}
