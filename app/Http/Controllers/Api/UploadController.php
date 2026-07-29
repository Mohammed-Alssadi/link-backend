<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OauthToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UploadController extends Controller
{
    /**
     * رفع صورة منتج لمنصة زد
     * POST /api/upload/zid/products/{productId}/images
     */
    public function uploadZidProductImage(Request $request, string $productId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $token = $user->token;

        if (! $token) {
            return response()->json(['success' => false, 'message' => 'لم يتم العثور على توكن المتجر'], 401);
        }

        // ─── التحقق من وجود الملف ────────────────────────────────────────────
        if (! $request->hasFile('image')) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم إرسال أي ملف. يرجى إرسال الصورة في حقل "image"',
            ], 400);
        }

        $file = $request->file('image');

        // ─── التحقق من نوع الملف ─────────────────────────────────────────────
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
        if (! in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json([
                'success' => false,
                'message' => 'نوع الملف غير مدعوم. يُسمح فقط بـ JPEG, PNG, WEBP, GIF',
            ], 422);
        }

        // ─── التحقق من حجم الملف (10 ميجابايت) ──────────────────────────────
        if ($file->getSize() > 10 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'حجم الملف يتجاوز الحد الأقصى (10 ميجابايت)',
            ], 422);
        }

        $accessToken  = $token->authorization_token ?? $token->access_token;
        $managerToken = $token->access_token;
        $storeId      = $token->merchant;

        // ─── بناء Multipart Request لزد ─────────────────────────────────────
        $httpRequest = Http::withHeaders([
            'Authorization'   => 'Bearer '.$accessToken,
            'X-Manager-Token' => $managerToken ?? '',
            'Access-Token'    => $managerToken ?? '',
            'Store-Id'        => (string) $storeId,
            'Accept-Language' => 'ar',
            'Role'            => 'Manager',
            'Accept'          => 'application/json',
        ])->timeout(30)->attach(
            'image',
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName() ?: 'product-image.jpg'
        );

        if ($request->filled('alt_text')) {
            $httpRequest = $httpRequest->attach('alt_text', $request->input('alt_text'), 'alt_text');
        }

        $response = $httpRequest->post("https://api.zid.sa/v1/products/{$productId}/images/");

        if ($response->successful()) {
            \Log::info("[Zid Image Upload ✅] Product: {$productId}, Status: {$response->status()}");

            return response()->json([
                'success' => true,
                'data'    => $response->json(),
            ]);
        }

        \Log::error("[Zid Image Upload ❌] Status: {$response->status()}", ['body' => $response->body()]);

        return response()->json([
            'success' => false,
            'message' => 'فشل رفع الصورة في منصة زد',
            'details' => $response->json(),
        ], $response->status());
    }

    /**
     * رفع صورة منتج لمنصة سلة
     * POST /api/upload/salla/products/{productId}/images
     */
    public function uploadSallaProductImage(Request $request, string $productId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user  = $request->user();
        $token = $user->token;

        if (! $token) {
            return response()->json(['success' => false, 'message' => 'لم يتم العثور على توكن المتجر'], 401);
        }

        // ─── التحقق من وجود الملف ────────────────────────────────────────────
        if (! $request->hasFile('image')) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم إرسال أي ملف. يرجى إرسال الصورة في حقل "image"',
            ], 400);
        }

        $file = $request->file('image');

        // ─── التحقق من نوع الملف ─────────────────────────────────────────────
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
        if (! in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json([
                'success' => false,
                'message' => 'نوع الملف غير مدعوم. يُسمح فقط بـ JPEG, PNG, WEBP, GIF',
            ], 422);
        }

        // ─── التحقق من حجم الملف (10 ميجابايت) ──────────────────────────────
        if ($file->getSize() > 10 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'حجم الملف يتجاوز الحد الأقصى (10 ميجابايت)',
            ], 422);
        }

        $accessToken = $token->access_token;

        // ─── بناء Multipart Request لسلة (الحقل: photo) ─────────────────────
        $httpRequest = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
            'Accept'        => 'application/json',
        ])->timeout(30)->attach(
            'photo',  // سلة تستخدم 'photo' وليس 'image'
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName() ?: 'product-image.jpg'
        );

        // إضافة alt_text إذا أُرسل مع الطلب
        if ($request->filled('alt_text')) {
            $httpRequest = $httpRequest->attach('alt_text', $request->input('alt_text'), 'alt_text');
        }

        $response = $httpRequest->post("https://api.salla.dev/admin/v2/products/{$productId}/images");

        if ($response->successful()) {
            \Log::info("[Salla Image Upload ✅] Product: {$productId}, Status: {$response->status()}");

            return response()->json([
                'success' => true,
                'data'    => $response->json('data') ?? $response->json(),
            ]);
        }

        \Log::error("[Salla Image Upload ❌] Status: {$response->status()}", ['body' => $response->body()]);

        return response()->json([
            'success' => false,
            'message' => 'فشل رفع الصورة في منصة سلة',
            'details' => $response->json(),
        ], $response->status());
    }
}
