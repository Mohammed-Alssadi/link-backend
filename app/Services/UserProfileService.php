<?php

namespace App\Services;

use App\Data\UserProfileData;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class UserProfileService
{
    /**
     * جلب بيانات التاجر الحية من منصة سلة أو زد وتحويلها فوريًا لـ UserProfileData
     */
    public function getUserProfile(User $user): UserProfileData
    {
        $token = $user->token;
        if (! $token) {
            throw new \RuntimeException('لم يتم العثور على توكن المنصة المربوطة بالحساب');
        }

        if ($token->platform === 'salla') {
            return $this->fetchSallaUserProfile($token->access_token);
        }

        if ($token->platform === 'zid') {
            return $this->fetchZidUserProfile($token->authorization_token ?? $token->access_token, $token->access_token);
        }

        throw new \InvalidArgumentException('منصة التاجر غير مدعومة');
    }

    private function fetchSallaUserProfile(string $accessToken): UserProfileData
    {
        $authBaseUrl = config('services.salla.auth_base_url', 'https://accounts.salla.sa');
        $response = Http::withToken($accessToken)
            ->get($authBaseUrl . '/oauth2/user/info');

        if (! $response->successful()) {
            throw new \RuntimeException('فشل جلب بيانات التاجر من سلة');
        }

        return UserProfileData::fromSalla($response->json());
    }

    private function fetchZidUserProfile(string $accessToken, ?string $managerToken): UserProfileData
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'X-Manager-Token' => $managerToken ?? '',
            'Accept-Language' => 'ar',
            'Accept' => 'application/json',
        ];

        $response = Http::withHeaders($headers)
            ->get('https://api.zid.sa/v1/managers/account/profile');

        if (! $response->successful()) {
            throw new \RuntimeException('فشل جلب بيانات التاجر من زد');
        }

        return UserProfileData::fromZid($response->json());
    }
}
