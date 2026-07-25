<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StoreProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreProfileController extends Controller
{
    public function __construct(protected StoreProfileService $storeProfileService) {}

    /**
     * جلب بيانات المتجر الحية الموحدة مباشرة عبر Spatie Laravel Data
     */
    public function show(Request $request): JsonResponse
    {
        $data = $this->storeProfileService->getStoreProfile($request->user());

        return response()->json([
            'success' => true,
            'source' => 'api',
            'data' => $data,
        ]);
    }
}
