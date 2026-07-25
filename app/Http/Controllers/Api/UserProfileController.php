<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function __construct(protected UserProfileService $userProfileService) {}

    /**
     * جلب بيانات التاجر الحية الموحدة مباشرة عبر Spatie Laravel Data
     */
    public function show(Request $request): JsonResponse
    {
        $data = $this->userProfileService->getUserProfile($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
