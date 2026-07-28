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

        $redirectUri = config('services.salla.redirect') ?: route('auth.callback', ['platform' => 'salla']);

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

        $redirectUri = config('services.salla.redirect') ?: route('auth.callback', ['platform' => 'salla']);

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
     * تنفيذ طلب البروكسي الديناميكي الشفاف لمنصة سلة
     */
    public function proxyRequest(OauthToken $oauthToken, string $method, string $path, array $queryParams, array $body = []): array
    {
        $cleanPath = '/' . ltrim($path, '/');

        // 1. حماية من اجتياز المسار (Path Traversal)
        if (str_contains($cleanPath, '..')) {
            return [
                'status' => 403,
                'body' => ['success' => false, 'message' => 'مسار غير صالح (Path Traversal)'],
            ];
        }

        // 2. حماية قائمة المسارات المسموحة (SSRF Whitelist)
        $allowedPrefixes = ['/products', '/categories', '/orders', '/customers', '/store', '/profile', '/attributes', '/badges', '/locations'];
        $isAllowed = $cleanPath === '/' || $cleanPath === '' || collect($allowedPrefixes)->contains(fn ($prefix) => $cleanPath === $prefix || str_starts_with($cleanPath, $prefix . '/'));

        if (! $isAllowed) {
            return [
                'status' => 403,
                'body' => ['success' => false, 'message' => 'مسار غير مصرح به (Proxy Whitelist)'],
            ];
        }

        // 3. اعتراض حالة نفاد المخزون لمنع أخطاء 422 وإرجاع مصفوفة فارغة منظمة
        if (($queryParams['status'] ?? '') === 'out_of_stock' && ($cleanPath === '/products' || $cleanPath === '/products/')) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => [],
                    'pagination' => [
                        'currentPage' => 1,
                        'totalPages' => 1,
                        'totalCount' => 0,
                        'perPage' => 15,
                        'hasNext' => false,
                        'hasPrev' => false,
                    ],
                ],
            ];
        }

        $isListPath = in_array($cleanPath, ['/products', '/products/', '/categories', '/categories/']);
        $normalizedQuery = $isListPath ? $this->normalizeQueryParams($queryParams, $cleanPath) : $queryParams;

        $client = $this->apiClient($oauthToken);
        $methodUpper = strtoupper($method);

        $timeout = in_array($methodUpper, ['POST', 'PUT', 'PATCH', 'DELETE']) ? 60 : 45;
        $client = $client->timeout($timeout);

        $response = match ($methodUpper) {
            'GET' => $client->get($cleanPath, $normalizedQuery),
            'POST' => $client->post($cleanPath . ($normalizedQuery ? '?' . http_build_query($normalizedQuery) : ''), $body),
            'PUT' => $client->put($cleanPath . ($normalizedQuery ? '?' . http_build_query($normalizedQuery) : ''), $body),
            'PATCH' => $client->patch($cleanPath . ($normalizedQuery ? '?' . http_build_query($normalizedQuery) : ''), $body),
            'DELETE' => $client->delete($cleanPath, $normalizedQuery),
            default => null,
        };

        if (! $response) {
            return [
                'status' => 405,
                'body' => ['success' => false, 'message' => 'طريقة الطلب غير مدعومة'],
            ];
        }

        $statusCode = $response->status();
        $jsonData = $response->json();

        if ($statusCode >= 200 && $statusCode < 300 && is_array($jsonData)) {
            if ($methodUpper === 'GET' && $isListPath) {
                $normalizedResponse = $this->normalizeProxyResponse($jsonData, $cleanPath, $queryParams);
                return ['status' => $statusCode, 'body' => $normalizedResponse];
            }
            return ['status' => $statusCode, 'body' => $jsonData];
        }

        return ['status' => $statusCode, 'body' => $jsonData ?? ['success' => false, 'message' => $response->body()]];
    }

    private function isSkuPattern(string $str): bool
    {
        $trimmed = trim($str);
        if (empty($trimmed)) {
            return false;
        }

        $hasDashesOrDots = preg_match('/[-.]/', $trimmed);
        $isAlphanumericAndLong = preg_match('/^[a-zA-Z0-9]{6,}$/', $trimmed);
        $isPureNumberAndLong = preg_match('/^[0-9]{8,}$/', $trimmed);

        return (bool) ($hasDashesOrDots || $isAlphanumericAndLong || $isPureNumberAndLong);
    }

    private function normalizeQueryParams(array $query, string $path): array
    {
        $page = (int) ($query['page'] ?? 1);
        $limit = (int) ($query['limit'] ?? 15);
        $normalized = [];

        $normalized['page'] = $page;
        $normalized['per_page'] = $limit;

        if (! empty($query['search'])) {
            $normalized['keyword'] = $query['search'];
        }

        if (! empty($query['category_id'])) {
            $normalized['categories'] = [$query['category_id']];
        }

        $finalQuery = array_merge($query, $normalized);
        unset($finalQuery['limit'], $finalQuery['search'], $finalQuery['category_id'], $finalQuery['is_published']);

        return $finalQuery;
    }

    private function normalizeProxyResponse(array $rawData, string $path, array $originalQuery): array
    {
        $page = (int) ($originalQuery['page'] ?? 1);
        $limit = (int) ($originalQuery['limit'] ?? 15);

        $unifiedData = $rawData['data'] ?? [];
        $pagination = [
            'currentPage' => $page,
            'totalPages' => 1,
            'totalCount' => count($unifiedData),
            'perPage' => $limit,
            'hasNext' => false,
            'hasPrev' => $page > 1,
        ];

        if (! empty($rawData['pagination'])) {
            $p = $rawData['pagination'];
            $currentPage = (int) ($p['currentPage'] ?? $p['current_page'] ?? $page);
            $totalPages = (int) ($p['totalPages'] ?? $p['total_pages'] ?? 1);
            $totalCount = (int) ($p['total'] ?? $p['count'] ?? count($unifiedData));
            $perPage = (int) ($p['perPage'] ?? $p['per_page'] ?? $limit);

            $pagination = [
                'currentPage' => $currentPage,
                'totalPages' => max(1, $totalPages),
                'totalCount' => $totalCount,
                'perPage' => $perPage,
                'hasNext' => $currentPage < $totalPages,
                'hasPrev' => $currentPage > 1,
            ];
        }

        return [
            'success' => true,
            'data' => $unifiedData,
            'pagination' => $pagination,
        ];
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
