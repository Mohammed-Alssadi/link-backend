<?php

namespace App\Services;

use App\Data\StoreProfileData;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class StoreProfileService
{
    /**
     * جلب بيانات المتجر الحية مباشرة من منصة التاجر (سلة أو زد) وتحويلها فوريًا لـ StoreProfileData
     */
    public function getStoreProfile(User $user): StoreProfileData
    {
        $token = $user->token;
        if (! $token) {
            throw new \RuntimeException('لم يتم العثور على توكن المنصة المربوطة بالحساب');
        }

        if ($token->platform === 'salla') {
            return $this->fetchSallaStoreProfile($token->access_token);
        }

        if ($token->platform === 'zid') {
            return $this->fetchZidStoreProfile($token->authorization_token ?? $token->access_token, $token->access_token);
        }

        throw new \InvalidArgumentException('منصة التاجر غير مدعومة');
    }

    private function fetchSallaStoreProfile(string $accessToken): StoreProfileData
    {
        $apiBaseUrl = config('services.salla.api_base_url', 'https://api.salla.dev/admin/v2');
        $response = Http::withToken($accessToken)
            ->withHeaders(['Accept' => 'application/json'])
            ->get($apiBaseUrl . '/store/info');

        if (! $response->successful()) {
            throw new \RuntimeException('فشل جلب بيانات المتجر من منصة سلة');
        }

        $sallaData = $response->json('data') ?? [];
        return StoreProfileData::fromSalla($sallaData);
    }

    private function fetchZidStoreProfile(string $accessToken, ?string $managerToken): StoreProfileData
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'X-Manager-Token' => $managerToken ?? '',
            'Accept-Language' => 'ar',
            'Accept' => 'application/json',
        ];

        // ─── تنفيذ 5 طلبات متوازية عبر Http::pool ─────────────────────────────
        $responses = Http::pool(fn ($pool) => [
            $pool->as('store')->withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store'),
            $pool->as('branding')->withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store/branding'),
            $pool->as('social')->withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store/social'),
            $pool->as('localization')->withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store/localization'),
            $pool->as('business')->withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store/business'),
        ]);

        $storeData = $responses['store']->successful() ? $responses['store']->json() : [];
        $brandingData = $responses['branding']->successful() ? $responses['branding']->json('branding') : null;
        $socialData = $responses['social']->successful() ? $responses['social']->json('social') : null;
        $localizationData = $responses['localization']->successful() ? $responses['localization']->json('localization') : null;
        $businessData = $responses['business']->successful() ? $responses['business']->json('business') : null;

        if (empty($storeData)) {
            throw new \RuntimeException('فشل جلب البيانات الأساسية للمتجر من منصة زد');
        }

        return StoreProfileData::fromZid(
            $storeData,
            $brandingData,
            $socialData,
            $localizationData,
            $businessData
        );
    }
}
