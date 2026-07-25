<?php

namespace App\Contracts;

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
}
