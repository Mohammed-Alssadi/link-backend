<?php

namespace App\Services\Platforms;

use App\Contracts\PlatformProvider;
use App\Data\StoreProfileData;
use App\Data\UserProfileData;
use App\Models\OauthToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class SallaProvider implements PlatformProvider
{
    public const OAUTH_URL = 'https://accounts.salla.sa/oauth2/';

    public function getAuthorizationUrl(): string
    {
        $state = str()->random(40);
        Cache::put('oauth_salla_state_'.$state, true, now()->addMinutes(15));

        $redirectUri = config('services.salla.redirect') ?: route('api.auth.callback', ['platform' => 'salla']);

        $queries = http_build_query([
            'client_id' => config('services.salla.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'offline_access',
            'state' => $state,
        ]);

        return self::OAUTH_URL.'auth?'.$queries;
    }

    public function handleCallback(string $code, ?string $state = null): User
    {
        if ($state && ! Cache::pull('oauth_salla_state_'.$state)) {
            throw new InvalidArgumentException('المعلومات الأمنية للطلب غير صالحة أو منتهية الصلاحية (Invalid State).');
        }

        $redirectUri = config('services.salla.redirect') ?: route('api.auth.callback', ['platform' => 'salla']);

        // 1. Get Access Token from Salla OAuth Endpoint
        $tokensUrl = self::OAUTH_URL.'token';
        $response = Http::asForm()->post($tokensUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.salla.client_id'),
            'client_secret' => config('services.salla.client_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        $tokens = $response->json();

        $accessToken = $tokens['access_token'] ?? null;
        $refreshToken = $tokens['refresh_token'] ?? null;
        $expiresIn = $tokens['expires_in'] ?? null;

        if (empty($accessToken)) {
            $errMsg = $tokens['error_description'] ?? ($tokens['error'] ?? 'Failed to obtain access token from Salla.');
            throw new RuntimeException('Salla OAuth error: '.$errMsg);
        }

        // 2. Fetch Store Profile details from Salla API
        $profileHttpResponse = Http::withToken($accessToken)
            ->get(self::OAUTH_URL.'user/info');

        if (! $profileHttpResponse->successful()) {
            throw new RuntimeException('Failed to fetch Salla merchant profile.');
        }

        $userProfileResponse = $profileHttpResponse->json();
        $profileData = $userProfileResponse['data'] ?? [];

        if (empty($profileData['email']) || empty($profileData['merchant']['id']) || empty($profileData['merchant']['name'])) {
            throw new RuntimeException('Salla profile is missing required real merchant details.');
        }

        $merchantId = (string) $profileData['merchant']['id'];
        $userEmail = (string) $profileData['email'];
        $userName = (string) ($profileData['name'] ?? $profileData['merchant']['name']);
        $storeName = (string) $profileData['merchant']['name'];

        // 3. Find or Create User
        $user = User::firstOrCreate(
            ['email' => $userEmail],
            ['name' => $userName]
        );

        // 4. Save/Update OAuth Token in Database
        OauthToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'platform' => 'salla',
                'merchant' => $merchantId,
                'store_name' => $storeName,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $expiresIn ? now()->addSeconds((int) $expiresIn) : null,
            ]
        );

        return $user;
    }

    public function refreshToken(OauthToken $oauthToken): OauthToken
    {
        if (empty($oauthToken->refresh_token)) {
            throw new InvalidArgumentException('Refresh token is missing for Salla.');
        }

        $tokensUrl = self::OAUTH_URL.'token';
        $response = Http::asForm()->post($tokensUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.salla.client_id'),
            'client_secret' => config('services.salla.client_secret'),
            'refresh_token' => $oauthToken->refresh_token,
        ]);

        $tokens = $response->json();

        if (empty($tokens['access_token'])) {
            throw new RuntimeException('Failed to refresh Salla access token.');
        }

        $expiresIn = $tokens['expires_in'] ?? null;

        $oauthToken->update([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $oauthToken->refresh_token,
            'expires_at' => $expiresIn ? now()->addSeconds((int) $expiresIn) : $oauthToken->expires_at,
        ]);

        return $oauthToken;
    }

    public function getUserProfile(OauthToken $oauthToken): UserProfileData
    {
        $response = $this->authClient($oauthToken)->get('/oauth2/user/info');

        if (! $response->successful()) {
            throw new RuntimeException('فشل جلب بيانات التاجر من منصة سلة');
        }

        return UserProfileData::fromSalla($response->json());
    }

    public function getStoreProfile(OauthToken $oauthToken): StoreProfileData
    {
        $response = $this->apiClient($oauthToken)->get('/store/info');

        if (! $response->successful()) {
            throw new RuntimeException('فشل جلب بيانات المتجر من منصة سلة');
        }

        $sallaData = $response->json('data') ?? [];

        return StoreProfileData::fromSalla($sallaData);
    }

    /**
     * عميل HTTP الموحد والمبسط لطلبات API سلة (HTTP Client Helper)
     */
    private function apiClient(OauthToken $token)
    {
        $apiBaseUrl = config('services.salla.api_base_url', 'https://api.salla.dev/admin/v2');

        return Http::baseUrl($apiBaseUrl)
            ->withToken($token->access_token)
            ->acceptJson();
    }

    /**
     * عميل HTTP الموحد والمبسط لطلبات التخويل والحسابات في سلة
     */
    private function authClient(OauthToken $token)
    {
        $authBaseUrl = config('services.salla.auth_base_url', 'https://accounts.salla.sa');

        return Http::baseUrl($authBaseUrl)
            ->withToken($token->access_token)
            ->acceptJson();
    }
}
