<?php

namespace App\Services;

use App\Models\OauthToken;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SallaService
{
    public const OAUTH_URL = 'https://accounts.salla.sa/oauth2/';

    public function getAuthorizationUrl(): string
    {
        $state = str()->random(40);
        Cache::put('oauth_salla_state_' . $state, true, now()->addMinutes(15));
        session(['oauth_salla_state' => $state]);

        $queries = http_build_query([
            'client_id' => config('services.salla.client_id'),
            'redirect_uri' => config('services.salla.redirect') ?: (url('/auth/salla/callback')),
            'response_type' => 'code',
            'scope' => 'offline_access',
            'state' => $state,
        ]);

        return self::OAUTH_URL . 'auth?' . $queries;
    }

    public function handleCallback(string $code, ?string $state = null): User
    {
        if ($state) {
            $cacheValid = Cache::pull('oauth_salla_state_' . $state);
            $savedState = session('oauth_salla_state');
            session()->forget('oauth_salla_state');

            if (!$cacheValid && ($savedState && !hash_equals($savedState, $state))) {
                throw new \InvalidArgumentException('Invalid or expired OAuth state parameter.');
            }
        }

        // 1. Get Access Token from Salla OAuth Endpoint via Direct HTTP
        $tokensUrl = self::OAUTH_URL . 'token';
        $response = Http::asForm()->post($tokensUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.salla.client_id'),
            'client_secret' => config('services.salla.client_secret'),
            'redirect_uri' => config('services.salla.redirect') ?: (url('/auth/salla/callback')),
            'code' => $code,
        ]);

        $tokens = $response->json();

        $accessToken = $tokens['access_token'] ?? null;
        $refreshToken = $tokens['refresh_token'] ?? null;
        $expiresIn = $tokens['expires_in'] ?? null;

        if (empty($accessToken)) {
            $errMsg = $tokens['error_description'] ?? ($tokens['error'] ?? 'Failed to obtain access token from Salla.');
            throw new \RuntimeException('Salla OAuth error: ' . $errMsg);
        }

        // 2. Fetch Store Profile details from Salla API
        $profileHttpResponse = Http::withToken($accessToken)
            ->get(self::OAUTH_URL . 'user/info');

        if (! $profileHttpResponse->successful()) {
            throw new \RuntimeException('Failed to fetch Salla merchant profile.');
        }

        $userProfileResponse = $profileHttpResponse->json();
        $profileData = $userProfileResponse['data'] ?? [];

        if (empty($profileData['email']) || empty($profileData['merchant']['id']) || empty($profileData['merchant']['name'])) {
            throw new \RuntimeException('Salla profile is missing required real merchant details.');
        }

        $merchantId = (string) $profileData['merchant']['id'];
        $userEmail = (string) $profileData['email'];
        $userName = (string) ($profileData['name'] ?? $profileData['merchant']['name']);
        $storeName = (string) $profileData['merchant']['name'];

        // 3. Find or Create User (No password required)
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

        Auth::login($user);

        return $user;
    }

    public function refreshToken(OauthToken $oauthToken): OauthToken
    {
        if (empty($oauthToken->refresh_token)) {
            throw new \InvalidArgumentException('Refresh token is missing.');
        }

        $tokensUrl = self::OAUTH_URL . 'token';
        $response = Http::asForm()->post($tokensUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.salla.client_id'),
            'client_secret' => config('services.salla.client_secret'),
            'refresh_token' => $oauthToken->refresh_token,
        ]);

        $tokens = $response->json();

        if (empty($tokens['access_token'])) {
            throw new \RuntimeException('Failed to refresh Salla access token.');
        }

        $expiresIn = $tokens['expires_in'] ?? null;

        $oauthToken->update([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $oauthToken->refresh_token,
            'expires_at' => $expiresIn ? now()->addSeconds((int) $expiresIn) : $oauthToken->expires_at,
        ]);

        return $oauthToken;
    }
}
