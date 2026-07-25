<?php

namespace App\Services;

use App\Data\StoreProfileData;
use App\Data\UserProfileData;
use App\Models\OauthToken;
use App\Models\User;
use App\Services\Platforms\PlatformFactory;
use RuntimeException;

class PlatformService
{
    public function __construct(protected PlatformFactory $platformFactory) {}

    /**
     * الحصول على رابط التخويل OAuth للمنصة المحددة
     */
    public function getAuthorizationUrl(string $platform): string
    {
        return $this->platformFactory->make($platform)->getAuthorizationUrl();
    }

    /**
     * معالجة استجابة التخويل OAuth وتسجيل التاجر
     */
    public function handleCallback(string $platform, string $code, ?string $state = null): User
    {
        return $this->platformFactory->make($platform)->handleCallback($code, $state);
    }

    /**
     * جلب بيانات التاجر الحية وتجديد التوكن تلقائياً عند اقتراب انتهائه
     */
    public function getUserProfile(User $user): UserProfileData
    {
        $token = $this->getValidToken($user);
        $provider = $this->platformFactory->make($token->platform);

        return $provider->getUserProfile($token);
    }

    /**
     * جلب بيانات المتجر الحية وتجديد التوكن تلقائياً عند اقتراب انتهائه
     */
    public function getStoreProfile(User $user): StoreProfileData
    {
        $token = $this->getValidToken($user);
        $provider = $this->platformFactory->make($token->platform);

        return $provider->getStoreProfile($token);
    }

    /**
     * التحقق من توكن التاجر وتجديده تلقائياً عند اقتراب الانتهاء (Auto-Refresh Strategy)
     */
    private function getValidToken(User $user): OauthToken
    {
        $token = $user->token;
        if (! $token) {
            throw new RuntimeException('لم يتم العثور على توكن المنصة المربوطة بالحساب');
        }

        if ($token->isAboutToExpire()) {
            $provider = $this->platformFactory->make($token->platform);
            $token = $provider->refreshToken($token);
        }

        return $token;
    }
}
