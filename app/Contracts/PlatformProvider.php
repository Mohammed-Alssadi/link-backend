<?php

namespace App\Contracts;

use App\Data\Products\ProductData;
use App\Data\StoreProfileData;
use App\Data\UserProfileData;
use App\Models\OauthToken;
use App\Models\User;

interface PlatformProvider
{
    /**
     * الحصول على رابط التخويل (OAuth Authorization URL)
     */
    public function getAuthorizationUrl(): string;

    /**
     * معالجة استجابة التخويل وتبادل الكود بالتوكنات (OAuth Callback)
     */
    public function handleCallback(string $code, ?string $state = null): User;

    /**
     * تجديد توكن المنصة عند اقتراب انقضاء الصلاحية
     */
    public function refreshToken(OauthToken $oauthToken): OauthToken;

    /**
     * جلب بيانات التاجر الحية وتغليفها مباشرة في UserProfileData
     */
    public function getUserProfile(OauthToken $oauthToken): UserProfileData;

    /**
     * جلب بيانات المتجر الحية وتغليفها مباشرة في StoreProfileData
     */
    public function getStoreProfile(OauthToken $oauthToken): StoreProfileData;

    /**
     * جلب قائمة المنتجات الحية وتغليفها في مصفوفة ProductData DTOs
     *
     * @return ProductData[]
     */
    public function getProducts(OauthToken $oauthToken): array;

    /**
     * جلب بيانات منتج محدد حية برقم الـ ID وتغليفها في ProductData DTO
     */
    public function getProduct(OauthToken $oauthToken, string $productId): ProductData;

    /**
     * تحديث بيانات المنتج الحية على المنصة
     */
    public function updateProduct(OauthToken $oauthToken, string $productId, array $data): ProductData;

    /**
     * حذف المنتج الحية على المنصة
     */
    public function deleteProduct(OauthToken $oauthToken, string $productId): bool;

    /**
     * جلب تصنيفات المتجر الحية
     */
    public function getCategories(OauthToken $oauthToken): array;
}
