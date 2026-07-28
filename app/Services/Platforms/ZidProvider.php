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
     * تنفيذ طلب البروكسي الديناميكي الشفاف لمنصة زد
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

        $isListPath = in_array($cleanPath, ['/products', '/products/', '/categories', '/categories/']);
        $normalizedQuery = $isListPath ? $this->normalizeQueryParams($queryParams, $cleanPath) : $queryParams;

        $targetPath = $this->mapZidPath($cleanPath);
        $client = $this->apiClient($oauthToken);

        $methodUpper = strtoupper($method);
        $timeout = in_array($methodUpper, ['POST', 'PUT', 'PATCH', 'DELETE']) ? 60 : 45;
        $client = $client->timeout($timeout);

        $response = match ($methodUpper) {
            'GET' => $client->get($targetPath, $normalizedQuery),
            'POST' => $client->post($targetPath . ($normalizedQuery ? '?' . http_build_query($normalizedQuery) : ''), $body),
            'PUT' => $client->put($targetPath . ($normalizedQuery ? '?' . http_build_query($normalizedQuery) : ''), $body),
            'PATCH' => $client->patch($targetPath . ($normalizedQuery ? '?' . http_build_query($normalizedQuery) : ''), $body),
            'DELETE' => $client->delete($targetPath, $normalizedQuery),
            default => null,
        };

        if (! $response) {
            return [
                'status' => 405,
                'body' => ['success' => false, 'message' => 'طريقة الطلب غير مدعومة'],
            ];
        }

        $statusCode = $response->status();
        $bodyText = $response->body();
        $jsonData = $response->json();

        // 3. اعتراض خطأ 404 لعدم تطابق المنتجات في زد وتحويله لرد ناجح فارغ
        if ($statusCode === 404 && str_contains($cleanPath, 'products')) {
            $errDetail = strtolower(is_array($jsonData) ? ($jsonData['detail'] ?? $bodyText) : $bodyText);
            if (str_contains($errDetail, 'no product matches') || str_contains($errDetail, 'not found') || str_contains($errDetail, 'صفحة غير صحيحة')) {
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
        }

        if ($statusCode >= 200 && $statusCode < 300 && is_array($jsonData)) {
            if ($methodUpper === 'GET' && $isListPath) {
                $normalizedResponse = $this->normalizeProxyResponse($jsonData, $cleanPath, $queryParams);
                return ['status' => $statusCode, 'body' => $normalizedResponse];
            }
            return ['status' => $statusCode, 'body' => $jsonData];
        }

        return ['status' => $statusCode, 'body' => $jsonData ?? ['success' => false, 'message' => $bodyText]];
    }

    private function mapZidPath(string $path): string
    {
        $parts = array_values(array_filter(explode('/', $path), fn ($p) => $p !== ''));
        $storeEntities = ['categories', 'orders', 'customers'];

        if (count($parts) > 0 && in_array($parts[0], $storeEntities)) {
            $entity = $parts[0];
            $newPath = "/managers/store/{$entity}";

            if (count($parts) > 1) {
                $id = $parts[1];
                $newPath .= "/{$id}";

                $restOfPath = implode('/', array_slice($parts, 2));

                if (empty($restOfPath) && in_array($entity, ['categories', 'orders'])) {
                    $newPath .= '/view';
                } elseif (! empty($restOfPath)) {
                    $newPath .= "/{$restOfPath}";
                }
            }

            if (! str_ends_with($newPath, '/')) {
                $newPath .= '/';
            }

            return $newPath;
        }

        $finalPath = '/' . ltrim($path, '/');
        if (! str_ends_with($finalPath, '/')) {
            $finalPath .= '/';
        }

        return $finalPath;
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
        $normalized['page_size'] = $limit;

        if (! empty($query['search'])) {
            $normalized['name'] = $query['search'];
        }

        if (! empty($query['category_id'])) {
            $normalized['categories'] = $query['category_id'];
        }

        if (isset($query['is_published'])) {
            $normalized['is_draft'] = $query['is_published'] === 'true' ? 'false' : 'true';
        }

        $finalQuery = array_merge($query, $normalized);
        unset($finalQuery['limit'], $finalQuery['search'], $finalQuery['category_id'], $finalQuery['is_published']);

        return $finalQuery;
    }

    private function normalizeProxyResponse(array $rawData, string $path, array $originalQuery): array
    {
        $page = (int) ($originalQuery['page'] ?? 1);
        $limit = (int) ($originalQuery['limit'] ?? 15);
        $pathLower = strtolower($path);

        $unifiedData = [];
        if (str_contains($pathLower, 'products')) {
            $unifiedData = $rawData['results'] ?? ($rawData['products'] ?? []);
        } elseif (str_contains($pathLower, 'categories')) {
            $unifiedData = $rawData['categories'] ?? ($rawData['results'] ?? []);
        } elseif (str_contains($pathLower, 'orders')) {
            $unifiedData = $rawData['orders'] ?? ($rawData['results'] ?? []);
        } elseif (str_contains($pathLower, 'customers')) {
            $unifiedData = $rawData['customers'] ?? ($rawData['results'] ?? []);
        } else {
            $unifiedData = $rawData['data'] ?? ($rawData['results'] ?? ($rawData['categories'] ?? ($rawData['orders'] ?? ($rawData['customers'] ?? []))));
            if (! is_array($unifiedData)) {
                $unifiedData = [$rawData];
            }
        }

        $totalCount = (int) ($rawData['count'] ?? ($rawData['total_categories_count'] ?? ($rawData['total_order_count'] ?? count($unifiedData))));
        $totalPages = max(1, (int) ceil($totalCount / $limit));

        $pagination = [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'perPage' => $limit,
            'hasNext' => $page < $totalPages,
            'hasPrev' => $page > 1,
        ];

        return [
            'success' => true,
            'data' => $unifiedData,
            'pagination' => $pagination,
        ];
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
        $storeId = $token->merchant;

        $headers = [
            'Authorization'   => 'Bearer '.$accessToken,
            'X-Manager-Token' => $managerToken ?? '',
            // مطلوب من زد للحصول على صلاحيات المدير وبياناته الكاملة
            'Role'            => 'Manager',
            'Accept-Language' => 'ar',
            'Accept'          => 'application/json',
        ];

        if (! empty($storeId)) {
            $headers['Store-Id'] = (string) $storeId;
        }

        return $headers;
    }
}
