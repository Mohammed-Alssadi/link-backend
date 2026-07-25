<?php

namespace App\Services\Platforms;

use App\Contracts\PlatformProvider;
use App\Data\Products\ProductData;
use App\Data\StoreProfileData;
use App\Data\UserProfileData;
use App\Models\OauthToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ZidProvider implements PlatformProvider
{
    public const OAUTH_URL = 'https://oauth.zid.sa/';

    public function getAuthorizationUrl(): string
    {
        $state = str()->random(40);
        Cache::put('oauth_zid_state_'.$state, true, now()->addMinutes(15));

        $redirectUri = config('services.zid.redirect') ?: route('auth.callback', ['platform' => 'zid']);

        $queries = http_build_query([
            'client_id' => config('services.zid.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
        ]);

        return self::OAUTH_URL.'oauth/authorize?'.$queries;
    }

    public function handleCallback(string $code, ?string $state = null): User
    {
        if ($state && ! Cache::pull('oauth_zid_state_'.$state)) {
            throw new InvalidArgumentException('المعلومات الأمنية للطلب غير صالحة أو منتهية الصلاحية (Invalid State).');
        }

        $redirectUri = config('services.zid.redirect') ?: route('auth.callback', ['platform' => 'zid']);

        // 1. Get OAuth Tokens from Zid
        $tokensUrl = self::OAUTH_URL.'oauth/token';
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
            throw new RuntimeException('Zid OAuth error: '.$errMsg);
        }

        $headers = [
            'Authorization' => 'Bearer '.($authToken ?? $managerToken),
            'X-Manager-Token' => $managerToken ?? '',
            'Accept-Language' => 'ar',
            'Accept' => 'application/json',
        ];

        // 2. Fetch User Profile and Store Profile from Zid API
        $userHttpResponse = Http::withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/profile');
        if (! $userHttpResponse->successful()) {
            throw new RuntimeException('Failed to fetch Zid user profile.');
        }

        $storeHttpResponse = Http::withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store');
        if (! $storeHttpResponse->successful()) {
            throw new RuntimeException('Failed to fetch Zid store profile.');
        }

        $userProfile = $userHttpResponse->json();
        $storeProfile = $storeHttpResponse->json();

        $userData = $userProfile['user'] ?? ($userProfile['data']['user'] ?? []);
        $storeData = $storeProfile['store'] ?? ($storeProfile['data']['store'] ?? []);

        if (empty($userData['email'])) {
            throw new RuntimeException('Zid user profile is missing real email field.');
        }

        if (empty($storeData['title']) || empty($storeData['id'])) {
            throw new RuntimeException('Zid store profile is missing real store data.');
        }

        $merchantId = (string) $storeData['id'];
        $userEmail = (string) $userData['email'];
        $userName = (string) ($userData['name'] ?? $storeData['title']);
        $storeName = (string) $storeData['title'];

        // 3. Find or Create User
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

        return $user;
    }

    public function refreshToken(OauthToken $oauthToken): OauthToken
    {
        if (empty($oauthToken->refresh_token)) {
            throw new InvalidArgumentException('Refresh token is missing for Zid.');
        }

        $tokensUrl = self::OAUTH_URL.'oauth/token';
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
            throw new RuntimeException('Failed to refresh Zid access token.');
        }

        $oauthToken->update([
            'access_token' => $managerToken,
            'authorization_token' => $authToken ?? $oauthToken->authorization_token,
            'refresh_token' => $refreshToken ?? $oauthToken->refresh_token,
            'expires_at' => isset($merchantTokens['expires_in']) ? now()->addSeconds((int) $merchantTokens['expires_in']) : $oauthToken->expires_at,
        ]);

        return $oauthToken;
    }

    public function getUserProfile(OauthToken $oauthToken): UserProfileData
    {
        $response = $this->apiClient($oauthToken)->get('/managers/account/profile');

        if (! $response->successful()) {
            throw new RuntimeException('فشل جلب بيانات التاجر من منصة زد');
        }

        return UserProfileData::fromZid($response->json());
    }

    public function getStoreProfile(OauthToken $oauthToken): StoreProfileData
    {
        $headers = $this->buildZidHeaders($oauthToken);

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
            throw new RuntimeException('فشل جلب البيانات الأساسية للمتجر من منصة زد');
        }

        return StoreProfileData::fromZid(
            $storeData,
            $brandingData,
            $socialData,
            $localizationData,
            $businessData
        );
    }

    /**
     * جلب قائمة المنتجات الحية من API زد وتغليفها في مصفوفة ProductData DTOs
     *
     * @return ProductData[]
     */
    public function getProducts(OauthToken $oauthToken, array $filters = []): array
    {
        $params = array_filter([
            'page' => $filters['page'] ?? 1,
            'page_size' => $filters['limit'] ?? 15,
            'search' => $filters['search'] ?? null,
            'category_id' => $filters['category_id'] ?? null,
        ]);

        $response = $this->apiClient($oauthToken)->get('/managers/store/products/', $params);
        if (! $response->successful()) {
            $response = $this->apiClient($oauthToken)->get('/products/', $params);
        }

        if (! $response->successful()) {
            $errBody = $response->body();
            if ($response->status() === 401 || $response->status() === 403 || str_contains($errBody, 'No such user')) {
                try {
                    $oauthToken = $this->refreshToken($oauthToken);
                    $response = $this->apiClient($oauthToken)->get('/managers/store/products/', $params);
                    if (! $response->successful()) {
                        $response = $this->apiClient($oauthToken)->get('/products/', $params);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("[Zid Token Refresh Error]: ".$e->getMessage());
                }
            }
            if (! $response->successful()) {
                $errDetail = $response->json('message') ?? ($response->json('error') ?? $response->body());
                $errStr = is_array($errDetail) ? json_encode($errDetail, JSON_UNESCAPED_UNICODE) : (string) $errDetail;
                \Illuminate\Support\Facades\Log::error("[ZidProvider getProducts Error]: status {$response->status()}, detail: {$errStr}");
                throw new RuntimeException('فشل جلب قائمة المنتجات من منصة زد: '.$errStr);
            }
        }

        $json = $response->json();
        $items = $json['results'] ?? ($json['products'] ?? ($json['data'] ?? []));
        $zidPaging = $json['paging'] ?? [];

        $products = [];
        foreach ($items as $item) {
            $products[] = ProductData::fromZid($item);
        }

        $currentPage = (int) ($zidPaging['page'] ?? 1);
        $totalPages = (int) ($zidPaging['total_pages'] ?? 1);

        return [
            'data' => $products,
            'pagination' => [
                'currentPage' => $currentPage,
                'totalPages'  => $totalPages,
                'totalCount'  => (int) ($zidPaging['count'] ?? 0),
                'perPage'     => (int) ($zidPaging['page_size'] ?? 15),
                'hasNext'     => $currentPage < $totalPages,
                'hasPrev'     => $currentPage > 1,
            ],
        ];
    }

    /**
     * جلب بيانات منتج محدد حية من API زد وتغليفها في ProductData DTO
     */
    public function getProduct(OauthToken $oauthToken, string $productId): ProductData
    {
        $response = $this->apiClient($oauthToken)->get("/managers/store/products/{$productId}/");
        if (! $response->successful()) {
            $response = $this->apiClient($oauthToken)->get("/products/{$productId}/");
        }

        if (! $response->successful()) {
            if ($response->status() === 401) {
                try {
                    $oauthToken = $this->refreshToken($oauthToken);
                    $response = $this->apiClient($oauthToken)->get("/managers/store/products/{$productId}/");
                    if (! $response->successful()) {
                        $response = $this->apiClient($oauthToken)->get("/products/{$productId}/");
                    }
                } catch (\Throwable $e) {}
            }
            if (! $response->successful()) {
                throw new RuntimeException("فشل جلب بيانات المنتج [{$productId}] من منصة زد");
            }
        }

        $rawProduct = $response->json('product') ?? ($response->json('data') ?? $response->json());

        return ProductData::fromZid($rawProduct);
    }

    /**
     * تحديث بيانات المنتج في منصة زد
     */
    public function updateProduct(OauthToken $oauthToken, string $productId, array $data): ProductData
    {
        $response = $this->apiClient($oauthToken)->patch("/managers/store/products/{$productId}/", $data);
        if (! $response->successful()) {
            $response = $this->apiClient($oauthToken)->patch("/products/{$productId}/", $data);
        }

        if (! $response->successful()) {
            $errMsg = $response->json('message') ?? "فشل تحديث المنتج رقم [{$productId}] في منصة زد";
            throw new RuntimeException(is_array($errMsg) ? json_encode($errMsg) : $errMsg);
        }

        $rawProduct = $response->json('product') ?? ($response->json('data') ?? $response->json());

        return ProductData::fromZid($rawProduct);
    }

    /**
     * حذف المنتج من منصة زد
     */
    public function deleteProduct(OauthToken $oauthToken, string $productId): bool
    {
        $response = $this->apiClient($oauthToken)->delete("/managers/store/products/{$productId}/");
        if (! $response->successful()) {
            $response = $this->apiClient($oauthToken)->delete("/products/{$productId}/");
        }

        if (! $response->successful()) {
            throw new RuntimeException("فشل حذف المنتج رقم [{$productId}] من منصة زد");
        }

        return true;
    }

    /**
     * جلب قائمة التصنيفات الحية من منصة زد
     */
    public function getCategories(OauthToken $oauthToken): array
    {
        $response = $this->apiClient($oauthToken)->get('/managers/store/categories/');
        if (! $response->successful()) {
            $response = $this->apiClient($oauthToken)->get('/categories/');
        }

        if (! $response->successful()) {
            if ($response->status() === 401) {
                try {
                    $oauthToken = $this->refreshToken($oauthToken);
                    $response = $this->apiClient($oauthToken)->get('/managers/store/categories/');
                    if (! $response->successful()) {
                        $response = $this->apiClient($oauthToken)->get('/categories/');
                    }
                } catch (\Throwable $e) {}
            }
            if (! $response->successful()) {
                return [];
            }
        }

        return $response->json('categories') ?? ($response->json('results') ?? ($response->json('data') ?? []));
    }

    /**
     * عميل HTTP الموحد والمبسط لطلبات API زد (HTTP Client Helper)
     */
    private function apiClient(OauthToken $token)
    {
        return Http::baseUrl('https://api.zid.sa/v1')
            ->withHeaders($this->buildZidHeaders($token));
    }

    private function buildZidHeaders(OauthToken $token): array
    {
        $accessToken = $token->authorization_token ?? $token->access_token;
        $managerToken = $token->access_token;

        return [
            'Authorization' => 'Bearer '.$accessToken,
            'X-Manager-Token' => $managerToken ?? '',
            'Accept-Language' => 'ar',
            'Accept' => 'application/json',
        ];
    }
}
