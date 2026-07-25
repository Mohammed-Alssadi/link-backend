<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(protected PlatformService $platformService) {}

    /**
     * جلب قائمة المنتجات الحية الموحدة لجميع المنصات
     */
    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'page' => $request->query('page', 1),
            'limit' => $request->query('limit', 15),
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'status' => $request->query('status'),
        ]);

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
