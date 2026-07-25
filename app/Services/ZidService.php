<?php

namespace App\Services;

use App\Models\OauthToken;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ZidService
{
    public const OAUTH_URL = 'https://oauth.zid.sa/';

    public function getAuthorizationUrl(): string
    {
        $state = str()->random(40);
        Cache::put('oauth_zid_state_' . $state, true, now()->addMinutes(15));
        session(['oauth_zid_state' => $state]);

        $redirectUri = config('services.zid.redirect') ?: route('auth.zid.callback');

        $queries = http_build_query([
            'client_id' => config('services.zid.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
        ]);

        return self::OAUTH_URL . 'oauth/authorize?' . $queries;
    }

    public function handleCallback(string $code, ?string $state = null): User
    {
        if ($state) {
            $cacheValid = Cache::pull('oauth_zid_state_' . $state);
            $savedState = session('oauth_zid_state');
            session()->forget('oauth_zid_state');

            if (! $cacheValid && ($savedState && ! hash_equals($savedState, $state))) {
                throw new \InvalidArgumentException('المعلومات الأمنية للطلب غير صالحة أو منتهية الصلاحية (Invalid State).');
            }
        }

        $redirectUri = config('services.zid.redirect') ?: route('auth.zid.callback');

        // 1. Get OAuth Tokens from Zid
        $tokensUrl = self::OAUTH_URL . 'oauth/token';
        $response = Http::asForm()->post($tokensUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.zid.client_id'),
            'client_secret' => config('services.zid.client_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        $merchantTokens = $response->json();

        $managerToken = $merchantTokens['access_token'] ?? null;  // X-Manager-Token header
        $authToken = $merchantTokens['authorization'] ?? null;     // Bearer Authorization token
        $refreshToken = $merchantTokens['refresh_token'] ?? null;

        if (empty($authToken) && empty($managerToken)) {
            $errMsg = $merchantTokens['error_description'] ?? ($merchantTokens['error'] ?? 'Failed to obtain access token from Zid.');
            throw new \RuntimeException('Zid OAuth error: ' . $errMsg);
        }

        $headers = [
            'Authorization' => 'Bearer ' . ($authToken ?? $managerToken),
            'X-Manager-Token' => $managerToken ?? '',
            'Accept-Language' => 'ar',
            'Accept' => 'application/json',
        ];

        // 2. Fetch User Profile and Store Profile from Zid API
        $userHttpResponse = Http::withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/profile');
        if (! $userHttpResponse->successful()) {
            throw new \RuntimeException('Failed to fetch Zid user profile.');
        }

        $storeHttpResponse = Http::withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store');
        if (! $storeHttpResponse->successful()) {
            throw new \RuntimeException('Failed to fetch Zid store profile.');
        }

        $userProfile = $userHttpResponse->json();
        $storeProfile = $storeHttpResponse->json();

        $userData = $userProfile['user'] ?? ($userProfile['data']['user'] ?? []);
        $storeData = $storeProfile['store'] ?? ($storeProfile['data']['store'] ?? []);

        if (empty($userData['email'])) {
            throw new \RuntimeException('Zid user profile is missing real email field.');
        }

        if (empty($storeData['title']) || empty($storeData['id'])) {
            throw new \RuntimeException('Zid store profile is missing real store data.');
        }

        $merchantId = (string) $storeData['id'];
        $userEmail = (string) $userData['email'];
        $userName = (string) ($userData['name'] ?? $storeData['title']);
        $storeName = (string) $storeData['title'];

        // 3. Find or Create User (No password required)
        $user = User::firstOrCreate(
            ['email' => $userEmail],
            ['name' => $userName]
        );

        // 4. Save/Update OAuth Token in Database
        OauthToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'platform' => 'zid',
                'merchant' => $merchantId,
                'store_name' => $storeName,
                'access_token' => $managerToken,
                'authorization_token' => $authToken,
                'refresh_token' => $refreshToken,
                'expires_at' => isset($merchantTokens['expires_in']) ? now()->addSeconds((int) $merchantTokens['expires_in']) : null,
            ]
        );

        Auth::login($user);

        return $user;
    }

    public function refreshToken(OauthToken $oauthToken): OauthToken
    {
        if (empty($oauthToken->refresh_token)) {
            throw new \InvalidArgumentException('Refresh token is missing.');
        }

        $tokensUrl = self::OAUTH_URL . 'oauth/token';
        $response = Http::asForm()->post($tokensUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.zid.client_id'),
            'client_secret' => config('services.zid.client_secret'),
            'refresh_token' => $oauthToken->refresh_token,
        ]);

        $merchantTokens = $response->json();

        $managerToken = $merchantTokens['access_token'] ?? null;
        $authToken = $merchantTokens['authorization'] ?? null;
        $refreshToken = $merchantTokens['refresh_token'] ?? null;

        if (empty($managerToken)) {
            throw new \RuntimeException('Failed to refresh Zid access token.');
        }

        $oauthToken->update([
            'access_token' => $managerToken,
            'authorization_token' => $authToken ?? $oauthToken->authorization_token,
            'refresh_token' => $refreshToken ?? $oauthToken->refresh_token,
            'expires_at' => isset($merchantTokens['expires_in']) ? now()->addSeconds((int) $merchantTokens['expires_in']) : $oauthToken->expires_at,
        ]);

        return $oauthToken;
    }
}
